<?php

namespace Tests\Feature\Api;

use App\Models\Station;
use App\Services\FuelTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\Concerns\CallsApi;
use Tests\Concerns\ChecksOpenApiContract;
use Tests\TestCase;

/**
 * GET /api/v1/map/stations (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part A): coordinates only, status, scope, filters, the day's metrics and the
 * products actually sold there.
 */
class MapApiTest extends TestCase
{
    use BuildsLedgerFixtures;
    use CallsApi;
    use ChecksOpenApiContract;
    use RefreshDatabase;

    /** @var array{company: mixed, otherCompany: mixed, station: mixed, otherStation: mixed, operator: mixed, otherOperator: mixed, admin: mixed, manager: mixed, diesel: mixed, petrol: mixed, vehicle: mixed, driver: mixed, card: mixed} */
    private array $world;

    private Station $harbor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FIXTURE_NOW));
        $this->world = $this->ledgerWorld();

        $this->harbor = Station::factory()->create([
            'name' => 'Grifo Central', 'district' => 'Centro', 'governorate' => 'Beirut',
            'latitude' => '33.9010000', 'longitude' => '35.5190000',
        ]);
        Station::factory()->create([
            'name' => 'Grifo Norte', 'district' => 'Trípoli', 'governorate' => 'Norte',
            'latitude' => '34.4367000', 'longitude' => '35.8497000',
        ]);
        Station::factory()->create([
            'name' => 'Grifo Cerrado', 'district' => 'Sidón', 'governorate' => 'Sur',
            'latitude' => '33.5571000', 'longitude' => '35.3729000', 'is_active' => false,
        ]);
        Station::factory()->create(['name' => 'Sin Coordenadas', 'district' => 'Interior', 'governorate' => 'Bekaa']);

        // One diesel purchase today at the harbor station.
        app(FuelTransactionService::class)->recordHistorical(
            $this->world['card'], $this->harbor, $this->world['operator'], $this->world['diesel'],
            '20.00', CarbonImmutable::now()->subHour(), 'MAP-1', null, CarbonImmutable::now(),
        );
    }

    public function test_only_stations_with_coordinates_are_listed(): void
    {
        $response = $this->stations($this->apiToken($this->world['manager']))->assertOk();

        $names = array_column($response->json('stations'), 'name');
        $this->assertContains('Grifo Central', $names);
        $this->assertContains('Grifo Norte', $names);
        $this->assertNotContains('Sin Coordenadas', $names);
    }

    public function test_the_day_metrics_and_products_come_from_the_transactions(): void
    {
        $response = $this->stations($this->apiToken($this->world['manager']))->assertOk();

        $harbor = $this->station($response, 'Grifo Central');
        $this->assertSame('Centro, Beirut', $harbor['address']);
        $this->assertSame('active', $harbor['status']);
        $this->assertSame(['DIESEL'], $harbor['products']);
        $this->assertSame(1, $harbor['transactions_today']);
        $this->assertSame('20.00', $harbor['liters_today']);
        $this->assertNotNull($harbor['last_transaction_at']);

        $north = $this->station($response, 'Grifo Norte');
        $this->assertSame(0, $north['transactions_today']);
        $this->assertSame('0.00', $north['liters_today']);
        $this->assertSame([], $north['products']);
    }

    public function test_a_manager_sees_active_stations_and_an_admin_also_the_inactive_one(): void
    {
        $this->assertNotContains('Grifo Cerrado', array_column($this->stations($this->apiToken($this->world['manager']))->json('stations'), 'name'));

        $admin = $this->stations($this->apiToken($this->world['admin']))->assertOk();
        $this->assertContains('Grifo Cerrado', array_column($admin->json('stations'), 'name'));
        $this->assertSame('inactive', $this->station($admin, 'Grifo Cerrado')['status']);
    }

    public function test_filters_by_status_product_and_name(): void
    {
        $admin = $this->apiToken($this->world['admin']);

        $inactive = $this->stations($admin, '?status=inactive')->json('stations');
        $this->assertSame(['Grifo Cerrado'], array_column($inactive, 'name'));

        $diesel = $this->stations($admin, '?product=DIESEL')->json('stations');
        $this->assertSame(['Grifo Central'], array_column($diesel, 'name'));

        $search = $this->stations($admin, '?q=Norte')->json('stations');
        $this->assertSame(['Grifo Norte'], array_column($search, 'name'));
    }

    public function test_unknown_filters_and_operators_are_refused(): void
    {
        $this->stations($this->apiToken($this->world['admin']), '?bogus=1')->assertUnprocessable();
        $this->stations($this->apiToken($this->world['operator']))->assertForbidden();
        $this->stations(null)->assertUnauthorized();
    }

    /**
     * @return TestResponse<Response>
     */
    private function stations(?string $token, string $query = ''): TestResponse
    {
        return $this->assertMatchesOpenApi(
            $this->api('GET', '/api/v1/map/stations'.$query, $token),
            'GET',
            '/map/stations',
        );
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>
     */
    private function station(TestResponse $response, string $name): array
    {
        foreach ($response->json('stations') as $station) {
            if ($station['name'] === $name) {
                return $station;
            }
        }

        $this->fail("Station {$name} not found in the response.");
    }
}
