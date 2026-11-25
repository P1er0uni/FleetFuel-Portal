<?php

namespace Tests\Feature\Notifications;

use App\Enums\CardStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\ThresholdOperator;
use App\Events\CardBlocked;
use App\Events\FleetRuleViolated;
use App\Events\QuotaExceeded;
use App\Events\QuotaWarning;
use App\Mail\NotificationMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Fleet;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Services\FuelCardService;
use App\Services\FuelTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The notification listener and its triggers (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * B2): company and fleet scope, inactive rules, the quota_warning threshold,
 * the 1-hour deduplication and the three channels.
 */
class NotificationDispatchTest extends TestCase
{
    use BuildsLedgerFixtures;
    use RefreshDatabase;

    /** @var array{company: mixed, otherCompany: mixed, station: mixed, otherStation: mixed, operator: mixed, otherOperator: mixed, admin: mixed, manager: mixed, diesel: mixed, petrol: mixed, vehicle: mixed, driver: mixed, card: mixed} */
    private array $world;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FIXTURE_NOW));
        $this->world = $this->ledgerWorld();
    }

    public function test_a_rule_fires_for_its_own_company_and_fleet_only(): void
    {
        $company = $this->world['company'];
        $card = $this->world['card'];

        NotificationRule::factory()->forEvent(NotificationEventType::QuotaExceeded)->create(['company_id' => $company->id]);
        event(new QuotaExceeded($card->id, $company->id, null));
        $this->assertSame(1, $this->inbox()->count());

        // Another company's event does not reach this company's rule.
        $other = Company::factory()->create();
        event(new QuotaExceeded($card->id, $other->id, null));
        $this->assertDatabaseCount('notifications', 1);

        // An inactive rule is ignored.
        NotificationRule::query()->update(['active' => false]);
        event(new QuotaExceeded($card->id, $company->id, null));
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_a_fleet_scoped_rule_ignores_events_of_other_fleets(): void
    {
        $company = $this->world['company'];
        $fleet = Fleet::factory()->create(['company_id' => $company->id]);
        NotificationRule::factory()
            ->forEvent(NotificationEventType::QuotaExceeded)
            ->forFleet($fleet)
            ->create();

        // An event with no fleet, or of another fleet, does not match.
        event(new QuotaExceeded($this->world['card']->id, $company->id, null));
        $this->assertSame(0, $this->inbox()->count());

        $otherFleet = Fleet::factory()->create(['company_id' => $company->id]);
        event(new QuotaExceeded($this->world['card']->id, $company->id, $otherFleet->id));
        $this->assertDatabaseCount('notifications', 0);

        event(new QuotaExceeded($this->world['card']->id, $company->id, $fleet->id));
        $this->assertSame(1, $this->inbox()->count());
    }

    public function test_the_quota_warning_threshold_is_respected(): void
    {
        $company = $this->world['company'];
        $card = $this->world['card'];
        NotificationRule::factory()
            ->forEvent(NotificationEventType::QuotaWarning)
            ->threshold(ThresholdOperator::Gte, '80.000')
            ->create(['company_id' => $company->id]);

        event(new QuotaWarning($card->id, $company->id, null, '79.0000', '79.00', '100.00'));
        $this->assertSame(0, $this->inbox()->count(), '79% is below the 80% threshold');

        event(new QuotaWarning($card->id, $company->id, null, '80.0000', '80.00', '100.00'));
        $this->assertSame(1, $this->inbox()->count(), '80% meets the threshold');
    }

    public function test_the_same_event_notifies_once_per_hour(): void
    {
        $company = $this->world['company'];
        $card = $this->world['card'];
        NotificationRule::factory()->forEvent(NotificationEventType::QuotaExceeded)->create(['company_id' => $company->id]);

        event(new QuotaExceeded($card->id, $company->id, null));
        $this->assertSame(1, $this->inbox()->count());

        $this->travel(30)->minutes();
        event(new QuotaExceeded($card->id, $company->id, null));
        $this->assertSame(1, $this->inbox()->count(), 'deduplicated within the hour');

        $this->travel(61)->minutes();
        event(new QuotaExceeded($card->id, $company->id, null));
        $this->assertSame(2, $this->inbox()->count(), 'a later event notifies again');
    }

    public function test_the_email_and_log_channels(): void
    {
        Mail::fake();
        $company = $this->world['company'];
        $card = $this->world['card'];

        NotificationRule::factory()->forEvent(NotificationEventType::CardBlocked)
            ->onChannel(NotificationChannel::Email)->create(['company_id' => $company->id]);
        NotificationRule::factory()->forEvent(NotificationEventType::QuotaExceeded)
            ->onChannel(NotificationChannel::Log)->create(['company_id' => $company->id]);

        event(new CardBlocked($card->id, $company->id, null, 'FF-TEST-0001'));
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->world['manager']->email));

        event(new QuotaExceeded($card->id, $company->id, null));
        $this->assertSame(1, AuditLog::query()->where('action', 'notification.sent')->count());
        $this->assertSame(0, $this->inbox()->count());
    }

    public function test_blocking_a_card_fires_a_notification(): void
    {
        $company = $this->world['company'];
        $card = $this->world['card'];
        NotificationRule::factory()->forEvent(NotificationEventType::CardBlocked)->create(['company_id' => $company->id]);

        app(FuelCardService::class)->changeStatus($card, CardStatus::Blocked, $this->world['admin']);

        $this->assertSame(1, $this->inbox()->count());
    }

    public function test_a_purchase_fires_a_quota_warning_and_a_rapid_fill_anomaly(): void
    {
        $company = $this->world['company'];
        $card = $this->world['card'];
        NotificationRule::factory()->forEvent(NotificationEventType::QuotaWarning)->create(['company_id' => $company->id]);
        NotificationRule::factory()->forEvent(NotificationEventType::AnomalyDetected)->create(['company_id' => $company->id]);

        $ledger = app(FuelTransactionService::class);
        $ledger->recordHistorical($card, $this->world['station'], $this->world['operator'], $this->world['diesel'],
            '50.00', CarbonImmutable::now()->subMinutes(20), 'NOTIF-1', null, CarbonImmutable::now());
        $ledger->recordHistorical($card, $this->world['station'], $this->world['operator'], $this->world['diesel'],
            '10.00', CarbonImmutable::now(), 'NOTIF-2', null, CarbonImmutable::now());

        // quota_warning fired (threshold unset) and the second fill is a rapid fill.
        $this->assertSame(1, $this->inbox()->where('event_type', 'quota_warning')->count());
        $this->assertSame(1, $this->inbox()->where('event_type', 'anomaly_detected')->count());
    }

    public function test_a_fleet_rule_violation_event_reaches_the_listener(): void
    {
        $company = $this->world['company'];
        NotificationRule::factory()->forEvent(NotificationEventType::FleetRuleViolated)->create(['company_id' => $company->id]);

        event(new FleetRuleViolated($this->world['card']->id, $company->id, null, 'max_liters_per_transaction'));

        $this->assertSame(1, $this->inbox()->count());
    }

    /** @return Builder<Notification> */
    private function inbox()
    {
        return Notification::query()->where('user_id', $this->world['manager']->id);
    }
}
