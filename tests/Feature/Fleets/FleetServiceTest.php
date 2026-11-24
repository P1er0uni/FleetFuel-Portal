<?php

namespace Tests\Feature\Fleets;

use App\Enums\FleetStatus;
use App\Models\AuditLog;
use App\Models\Fleet;
use App\Models\FuelCard;
use App\Services\FleetService;
use App\Services\FuelTransactionService;
use App\Support\FleetQuota;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The fleet service (docs/sdd/fase-1-flotas/spec.md): creation, members,
 * deletion and the aggregated monthly quota.
 */
class FleetServiceTest extends TestCase
{
    use BuildsLedgerFixtures;
    use RefreshDatabase;

    private FleetService $fleets;

    /** @var array{company: mixed, otherCompany: mixed, station: mixed, otherStation: mixed, operator: mixed, otherOperator: mixed, admin: mixed, manager: mixed, diesel: mixed, petrol: mixed, vehicle: mixed, driver: mixed, card: mixed} */
    private array $world;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FIXTURE_NOW));
        $this->fleets = app(FleetService::class);
        $this->world = $this->ledgerWorld();
    }

    public function test_a_fleet_is_created_active_with_its_quotas_and_audited(): void
    {
        $fleet = $this->fleets->create($this->world['company'], [
            'name' => 'Camiones de carga',
            'description' => 'Tráileros de larga distancia',
            'monthly_quota_liters' => '500.000',
            'monthly_quota_amount' => '450.00',
        ], $this->world['admin']);

        $this->assertSame('Camiones de carga', $fleet->name);
        $this->assertSame(FleetStatus::Active, $fleet->status);
        $this->assertSame('500.000', $fleet->monthly_quota_liters);
        $this->assertSame('450.00', $fleet->monthly_quota_amount);

        $audit = AuditLog::query()->where('action', 'fleet.created')->sole();
        $this->assertSame('fleet', $audit->auditable_type);
        $this->assertSame($fleet->id, $audit->auditable_id);
        $this->assertSame($this->world['company']->id, $audit->company_id);
    }

    public function test_two_fleets_cannot_share_a_name_within_one_company(): void
    {
        $this->fleets->create($this->world['company'], ['name' => 'Camiones'], $this->world['admin']);

        // The Form Request refuses a duplicate first; the database unique
        // key is the backstop for every other caller.
        $this->expectException(UniqueConstraintViolationException::class);
        Fleet::query()->forceCreate(['company_id' => $this->world['company']->id, 'name' => 'Camiones']);
    }

    public function test_the_same_name_is_allowed_in_another_company(): void
    {
        $this->fleets->create($this->world['company'], ['name' => 'Camiones'], $this->world['admin']);
        $other = $this->fleets->create($this->world['otherCompany'], ['name' => 'Camiones'], $this->world['admin']);

        $this->assertSame($this->world['otherCompany']->id, $other->company_id);
    }

    public function test_assigning_a_member_of_another_company_is_refused_and_changes_nothing(): void
    {
        $fleet = $this->fleets->create($this->world['company'], ['name' => 'Camiones'], $this->world['admin']);
        $foreign = FuelCard::factory()->create(['company_id' => $this->world['otherCompany']->id]);

        try {
            $this->fleets->assign($fleet, ['cards' => [$foreign->id]], $this->world['admin']);
            $this->fail('A cross-company assignment was accepted.');
        } catch (ValidationException) {
            $this->assertNull($foreign->refresh()->fleet_id);
        }

        $this->assertSame(0, AuditLog::query()->where('action', 'fleet.assignment_changed')->count());
    }

    public function test_assign_and_unassign_move_members_of_the_own_company(): void
    {
        $fleet = $this->fleets->create($this->world['company'], ['name' => 'Camiones'], $this->world['admin']);
        $card = FuelCard::factory()->create(['company_id' => $this->world['company']->id]);

        $this->fleets->assign($fleet, [
            'vehicles' => [$this->world['vehicle']->id],
            'drivers' => [$this->world['driver']->id],
            'cards' => [$card->id],
        ], $this->world['admin']);

        $this->assertSame($fleet->id, $this->world['vehicle']->refresh()->fleet_id);
        $this->assertSame($fleet->id, $this->world['driver']->refresh()->fleet_id);
        $this->assertSame($fleet->id, $card->refresh()->fleet_id);

        $this->fleets->unassign($fleet, ['cards' => [$card->id]], $this->world['admin']);
        $this->assertNull($card->refresh()->fleet_id);
        $this->assertSame($fleet->id, $this->world['vehicle']->refresh()->fleet_id);

        $this->assertSame(2, AuditLog::query()->where('action', 'fleet.assignment_changed')->count());
    }

    public function test_deleting_a_fleet_ungroups_its_members_which_keep_working(): void
    {
        $fleet = $this->fleets->create($this->world['company'], ['name' => 'Camiones'], $this->world['admin']);
        $card = FuelCard::factory()->create(['company_id' => $this->world['company']->id]);
        $this->fleets->assign($fleet, ['cards' => [$card->id]], $this->world['admin']);

        $this->fleets->delete($fleet, $this->world['admin']);

        $this->assertNull($card->refresh()->fleet_id);
        $this->assertSame(0, Fleet::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'fleet.deleted')->count());

        // The ungrouped card still accepts a purchase.
        $transaction = app(FuelTransactionService::class)->recordHistorical(
            $card, $this->world['station'], $this->world['operator'], $this->world['diesel'],
            '20.00', CarbonImmutable::now()->subHour(), 'POST-DELETE-1', null, CarbonImmutable::now(),
        );
        $this->assertSame('20.00', $transaction->liters);
    }

    public function test_the_fleet_quota_sums_the_monthly_usage_of_its_cards(): void
    {
        $fleet = $this->fleets->create($this->world['company'], [
            'name' => 'Camiones', 'monthly_quota_liters' => '50.00', 'monthly_quota_amount' => '50.00',
        ], $this->world['admin']);

        $cards = FuelCard::factory()->count(3)->create(['company_id' => $this->world['company']->id]);
        $this->fleets->assign($fleet, ['cards' => $cards->pluck('id')->all()], $this->world['admin']);

        $ledger = app(FuelTransactionService::class);
        foreach ([$cards[0], $cards[1], $cards[2]] as $i => $card) {
            $ledger->recordHistorical(
                $card, $this->world['station'], $this->world['operator'], $this->world['diesel'],
                (string) (10 * ($i + 1)).'.00', CarbonImmutable::now()->subHours(3 - $i),
                "QUOTA-{$i}", null, CarbonImmutable::now(),
            );
        }

        $quota = FleetQuota::for($fleet);
        $this->assertSame('60.00', $quota->usedL);
        $this->assertTrue($quota->litersExceeded());
        $this->assertTrue($quota->exceeded());
        $this->assertSame('120.0', $quota->litersPercent());
    }

    public function test_a_fleet_without_quota_is_never_exceeded(): void
    {
        $fleet = $this->fleets->create($this->world['company'], ['name' => 'Camiones'], $this->world['admin']);

        $quota = FleetQuota::for($fleet);
        $this->assertSame('0.00', $quota->usedL);
        $this->assertFalse($quota->exceeded());
        $this->assertNull($quota->litersPercent());
    }

    public function test_toggling_the_status_is_audited(): void
    {
        $fleet = $this->fleets->create($this->world['company'], ['name' => 'Camiones'], $this->world['admin']);

        $this->fleets->changeStatus($fleet, FleetStatus::Inactive, $this->world['admin']);
        $this->assertSame(FleetStatus::Inactive, $fleet->refresh()->status);

        $audit = AuditLog::query()->where('action', 'fleet.status_changed')->sole();
        $this->assertSame(['status' => 'active'], (array) $audit->old_values);
        $this->assertSame(['status' => 'inactive'], (array) $audit->new_values);
    }
}
