<?php

namespace Tests\Feature\Fleets;

use App\Enums\FleetRuleType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\RuleEnforcement;
use App\Exceptions\FleetRuleViolation;
use App\Models\Fleet;
use App\Models\FuelCard;
use App\Models\FuelTransaction;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Services\FleetRuleService;
use App\Services\FleetService;
use App\Services\FuelTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * New fleet limits and enforcement modes at ingestion
 * (docs/sdd/fase-5-reglas-limites-flota/spec.md): daily liters/amount limits,
 * per-transaction amount, days/hours, validity and warn-vs-block.
 */
class FleetRuleLimitsTest extends TestCase
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

    public function test_daily_liters_limit_blocks_the_second_purchase(): void
    {
        $fleet = $this->fleetWith($this->world['card']);
        $this->rule($fleet, FleetRuleType::MaxLitersPerDay, ['value' => '30.00']);

        $this->purchase('20.00', CarbonImmutable::now()->subHours(2), 'D1');

        $this->expectException(FleetRuleViolation::class);
        $this->purchase('20.00', CarbonImmutable::now()->subHour(), 'D2');
    }

    public function test_amount_per_transaction_blocks_an_expensive_purchase(): void
    {
        $fleet = $this->fleetWith($this->world['card']);
        // 20 L at 80000 LBP/L and 89500 LBP/USD ≈ 17.88 USD.
        $this->rule($fleet, FleetRuleType::MaxAmountPerTransactionUsd, ['value' => '10.00']);

        $this->expectException(FleetRuleViolation::class);
        $this->purchase('20.00', CarbonImmutable::now()->subHour(), 'A1');
    }

    public function test_amount_per_day_accumulates(): void
    {
        $fleet = $this->fleetWith($this->world['card']);
        $this->rule($fleet, FleetRuleType::MaxAmountPerDayUsd, ['value' => '20.00']);

        $this->purchase('20.00', CarbonImmutable::now()->subHours(2), 'M1'); // ~17.88

        $this->expectException(FleetRuleViolation::class);
        $this->purchase('20.00', CarbonImmutable::now()->subHour(), 'M2'); // total ~35.76
    }

    public function test_allowed_time_windows(): void
    {
        $fleet = $this->fleetWith($this->world['card']);
        $timezone = (string) config('fleetfuel.business_timezone');
        $weekday = CarbonImmutable::parse(self::FIXTURE_NOW)->setTimezone($timezone)->isoWeekday();
        $this->rule($fleet, FleetRuleType::AllowedTimeWindows, ['windows' => [[
            'days' => [$weekday], 'start' => '08:00', 'end' => '18:00',
        ]]]);

        // 12:00 Beirut (FIXTURE_NOW) is inside.
        $this->purchase('5.00', CarbonImmutable::parse(self::FIXTURE_NOW), 'W1');

        // 20:00 Beirut is outside.
        $this->expectException(FleetRuleViolation::class);
        $this->purchase('5.00', CarbonImmutable::parse(self::FIXTURE_NOW)->addHours(8), 'W2');
    }

    public function test_a_rule_outside_its_validity_is_ignored(): void
    {
        $fleet = $this->fleetWith($this->world['card']);
        $tomorrow = CarbonImmutable::now()->addDay()->toDateString();
        $this->rule($fleet, FleetRuleType::MaxLitersPerTransaction, ['value' => '1.00'], RuleEnforcement::Block, $tomorrow, null);

        // The rule starts tomorrow, so today it does not apply.
        $transaction = $this->purchase('20.00', CarbonImmutable::now()->subHour(), 'V1');
        $this->assertSame('20.00', $transaction->liters);
    }

    public function test_a_warn_rule_allows_the_purchase_and_notifies(): void
    {
        NotificationRule::factory()->forEvent(NotificationEventType::FleetRuleViolated)
            ->onChannel(NotificationChannel::InApp)
            ->create(['company_id' => $this->world['company']->id]);

        $fleet = $this->fleetWith($this->world['card']);
        $this->rule($fleet, FleetRuleType::MaxLitersPerTransaction, ['value' => '10.00'], RuleEnforcement::Warn);

        $transaction = $this->purchase('20.00', CarbonImmutable::now()->subHour(), 'WARN1');

        $this->assertSame('20.00', $transaction->liters);
        $this->assertSame(1, Notification::query()->where('user_id', $this->world['manager']->id)
            ->where('event_type', NotificationEventType::FleetRuleViolated->value)->count());
    }

    public function test_a_block_rule_still_rejects_the_purchase(): void
    {
        $fleet = $this->fleetWith($this->world['card']);
        $this->rule($fleet, FleetRuleType::MaxLitersPerTransaction, ['value' => '10.00']);

        try {
            $this->purchase('20.00', CarbonImmutable::now()->subHour(), 'B1');
            $this->fail('A blocking rule did not reject the purchase.');
        } catch (FleetRuleViolation) {
            $this->assertSame(0, FuelTransaction::query()->count());
        }
    }

    private function fleetWith(FuelCard $card): Fleet
    {
        $fleet = app(FleetService::class)->create($this->world['company'], ['name' => 'Flota QA '.uniqid()], $this->world['admin']);
        app(FleetService::class)->assign($fleet, ['cards' => [$card->id]], $this->world['admin']);

        return $fleet;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function rule(Fleet $fleet, FleetRuleType $type, array $value, RuleEnforcement $enforcement = RuleEnforcement::Block, ?string $from = null, ?string $to = null): void
    {
        app(FleetRuleService::class)->save($fleet, $type, $value, true, $this->world['admin'], $enforcement, $from, $to);
    }

    private function purchase(string $liters, CarbonImmutable $at, string $ref): FuelTransaction
    {
        return app(FuelTransactionService::class)->recordHistorical(
            $this->world['card'], $this->world['station'], $this->world['operator'], $this->world['diesel'],
            $liters, $at, $ref, null, CarbonImmutable::now(),
        );
    }
}
