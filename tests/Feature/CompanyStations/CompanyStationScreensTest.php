<?php

namespace Tests\Feature\CompanyStations;

use App\Models\CompanyStation;
use App\Models\Station;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * Admin screen for company–station agreements (Fase 6, área 3).
 */
class CompanyStationScreensTest extends TestCase
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

    public function test_the_admin_opens_and_saves_the_agreement(): void
    {
        $this->actingAs($this->world['admin'])
            ->get(route('companies.stations.edit', $this->world['company']))
            ->assertOk()
            ->assertSee('Restringir estaciones')
            ->assertSee($this->world['station']->name);

        $this->actingAs($this->world['admin'])
            ->put(route('companies.stations.update', $this->world['company']), [
                'restrict_stations' => '1',
                'station_ids' => [(string) $this->world['station']->id],
            ])
            ->assertRedirect(route('companies.stations.edit', $this->world['company']));

        $this->assertTrue($this->world['company']->refresh()->restrict_stations);
        $this->assertSame(1, CompanyStation::query()->where('company_id', $this->world['company']->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'company.stations_changed']);
    }

    public function test_only_admins_manage_the_agreement(): void
    {
        $this->actingAs($this->world['manager'])->get(route('companies.stations.edit', $this->world['company']))->assertForbidden();
        $this->actingAs($this->world['manager'])->put(route('companies.stations.update', $this->world['company']), [])->assertForbidden();
        $this->actingAs($this->world['operator'])->get(route('companies.stations.edit', $this->world['company']))->assertForbidden();
    }

    public function test_an_inactive_station_is_rejected(): void
    {
        $inactive = Station::factory()->inactive()->create();

        $this->actingAs($this->world['admin'])
            ->put(route('companies.stations.update', $this->world['company']), [
                'restrict_stations' => '1',
                'station_ids' => [(string) $inactive->id],
            ])
            ->assertSessionHasErrors('station_ids.0');

        $this->assertFalse($this->world['company']->refresh()->restrict_stations);
    }
}
