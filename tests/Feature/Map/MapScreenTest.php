<?php

namespace Tests\Feature\Map;

use App\Models\Station;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The /map page (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md, Part A):
 * who may open it, the Leaflet shell, the session JSON endpoint and the
 * "no location" section.
 */
class MapScreenTest extends TestCase
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

    public function test_the_map_opens_for_admins_and_managers_and_not_operators(): void
    {
        $this->actingAs($this->world['manager'])->get('/map')->assertOk()
            ->assertSee('Mapa de estaciones')
            ->assertSee('station-map', false);
        $this->actingAs($this->world['admin'])->get('/map')->assertOk();
        $this->actingAs($this->world['operator'])->get('/map')->assertForbidden();
    }

    public function test_stations_without_coordinates_are_reported_below(): void
    {
        // The ledger fixture's station has no coordinates.
        $this->actingAs($this->world['manager'])->get('/map')
            ->assertOk()
            ->assertSee('No hay estaciones con ubicación configurada')
            ->assertSee('Estaciones sin ubicación');
    }

    public function test_the_session_data_endpoint_returns_the_markers(): void
    {
        Station::factory()->create(['name' => 'Con Coordenadas', 'latitude' => '33.9', 'longitude' => '35.5']);

        $this->actingAs($this->world['manager'])
            ->getJson('/map/stations')
            ->assertOk()
            ->assertJsonStructure(['stations' => [['id', 'name', 'address', 'latitude', 'longitude', 'status', 'products', 'transactions_today', 'liters_today', 'last_transaction_at']]]);
    }
}
