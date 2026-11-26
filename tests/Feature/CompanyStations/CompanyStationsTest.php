<?php

namespace Tests\Feature\CompanyStations;

use App\Enums\FleetRuleType;
use App\Enums\RuleEnforcement;
use App\Exceptions\FleetRuleViolation;
use App\Exceptions\PurchaseDeclined;
use App\Models\CompanyStation;
use App\Models\Fleet;
use App\Models\FuelCard;
use App\Models\FuelTransaction;
use App\Services\CompanyStationService;
use App\Services\FleetRuleService;
use App\Services\FleetService;
use App\Services\FuelTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * Company–station agreements at ingestion (docs/sdd/fase-6-estaciones-por-empresa):
 * default allow-all, restriction with a list, empty list blocks all, and the
 * combination with fleet rules.
 */
class CompanyStationsTest extends TestCase
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

    public function test_without_restriction_any_station_is_allowed(): void
    {
        $transaction = $this->purchase($this->world['card'], '20.00', 'NR1');
        $this->assertSame('20.00', $transaction->liters);
    }

    public function test_a_restriction_blocks_stations_not_in_the_list(): void
    {
        $service = app(CompanyStationService::class);
        $service->sync($this->world['company'], true, [(int) $this->world['otherStation']->id], $this->world['admin']);

        try {
            $this->purchase($this->world['card'], '20.00', 'R1');
            $this->fail('A purchase at a non-enabled station was accepted.');
        } catch (PurchaseDeclined $e) {
            $this->assertSame('station_not_allowed', $e->errorCode);
        }

        $this->assertSame(0, FuelTransaction::query()->count());
    }

    public function test_an_enabled_station_is_allowed(): void
    {
        app(CompanyStationService::class)->sync($this->world['company'], true, [(int) $this->world['station']->id], $this->world['admin']);

        $transaction = $this->purchase($this->world['card'], '20.00', 'R2');
        $this->assertSame('20.00', $transaction->liters);
    }

    public function test_an_empty_list_with_the_restriction_blocks_everything(): void
    {
        app(CompanyStationService::class)->sync($this->world['company'], true, [], $this->world['admin']);

        $this->expectException(PurchaseDeclined::class);
        $this->purchase($this->world['card'], '20.00', 'R3');
    }

    public function test_the_restriction_applies_to_every_card_of_the_company(): void
    {
        $second = FuelCard::factory()->create(['company_id' => $this->world['company']->id]);
        app(CompanyStationService::class)->sync($this->world['company'], true, [(int) $this->world['otherStation']->id], $this->world['admin']);

        foreach ([$this->world['card'], $second] as $index => $card) {
            try {
                $this->purchase($card, '10.00', 'TWO-'.$index);
                $this->fail('A card of a restricted company was accepted at a disabled station.');
            } catch (PurchaseDeclined $e) {
                $this->assertSame('station_not_allowed', $e->errorCode);
            }
        }
    }

    public function test_the_company_restriction_combines_with_fleet_rules(): void
    {
        // Company enables the station ...
        app(CompanyStationService::class)->sync($this->world['company'], true, [(int) $this->world['station']->id], $this->world['admin']);

        // ... but the card's fleet forbids it.
        $fleet = app(FleetService::class)->create($this->world['company'], ['name' => 'Flota combo'], $this->world['admin']);
        app(FleetService::class)->assign($fleet, ['cards' => [$this->world['card']->id]], $this->world['admin']);
        app(FleetRuleService::class)->save($fleet, FleetRuleType::AllowedStations, ['ids' => [(int) $this->world['otherStation']->id]], true, $this->world['admin'], RuleEnforcement::Block);

        $this->expectException(FleetRuleViolation::class);
        $this->purchase($this->world['card'], '20.00', 'COMBO');
    }

    public function test_sync_persists_the_agreement_and_audits(): void
    {
        app(CompanyStationService::class)->sync($this->world['company'], true, [(int) $this->world['station']->id], $this->world['admin']);

        $this->assertTrue($this->world['company']->refresh()->restrict_stations);
        $this->assertSame(1, CompanyStation::query()->where('company_id', $this->world['company']->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'company.stations_changed']);
    }

    private function purchase(FuelCard $card, string $liters, string $ref): FuelTransaction
    {
        return app(FuelTransactionService::class)->recordHistorical(
            $card, $this->world['station'], $this->world['operator'], $this->world['diesel'],
            $liters, CarbonImmutable::now()->subHour(), $ref, null, CarbonImmutable::now(),
        );
    }
}
