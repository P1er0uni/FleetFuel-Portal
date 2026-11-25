<?php

namespace Tests\Feature\Api;

use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\Concerns\CallsApi;
use Tests\Concerns\ChecksOpenApiContract;
use Tests\TestCase;

/**
 * /api/v1/import (docs/sdd/fase-2-importacion-notificaciones/spec.md, A3):
 * multipart and JSON imports, the documented result, scope, abilities and the
 * row/size limits. Every response is checked against openapi.json.
 */
class ImportApiTest extends TestCase
{
    use BuildsLedgerFixtures;
    use CallsApi;
    use ChecksOpenApiContract;
    use RefreshDatabase;

    /** @var array{company: mixed, otherCompany: mixed, station: mixed, otherStation: mixed, operator: mixed, otherOperator: mixed, admin: mixed, manager: mixed, diesel: mixed, petrol: mixed, vehicle: mixed, driver: mixed, card: mixed} */
    private array $world;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FIXTURE_NOW));
        $this->world = $this->ledgerWorld();
    }

    public function test_a_manager_imports_a_csv_file_by_multipart(): void
    {
        $csv = "plate_no,fuel_type,tank_capacity_l,odometer_km,fleet_name\nAPI-001,diesel,50,1000,\nAPI-002,diesel,50,1000,\n";

        $response = $this->upload('vehicles', $csv, $this->apiToken($this->world['manager']));

        $response->assertOk()
            ->assertJsonPath('data.type', 'vehicles')
            ->assertJsonPath('data.dry_run', false)
            ->assertJsonPath('data.total_rows', 2)
            ->assertJsonPath('data.successful', 2)
            ->assertJsonPath('data.failed', 0);
        $this->assertSame(2, Vehicle::query()->where('plate_no', 'like', 'API-%')->count());
    }

    public function test_an_admin_imports_json_rows_with_an_integer_company_id(): void
    {
        $token = $this->apiToken($this->world['admin']);
        $companyId = $this->world['company']->id;

        $this->importJson('drivers', $token, [
            'company_id' => $companyId,
            'rows' => [['name' => 'Ana', 'phone' => '', 'license_no' => 'LIC-API-1', 'fleet_name' => '']],
        ])->assertOk()->assertJsonPath('data.successful', 1);

        // company_id as a JSON string is refused.
        $this->importJson('drivers', $token, [
            'company_id' => (string) $companyId,
            'rows' => [['name' => 'Bea', 'license_no' => 'LIC-API-2', 'fleet_name' => '']],
        ])->assertUnprocessable()->assertJsonValidationErrors('company_id', 'error.details');

        // A manager may not choose a company.
        $this->importJson('drivers', $this->apiToken($this->world['manager']), [
            'company_id' => $companyId,
            'rows' => [['name' => 'Cid', 'license_no' => 'LIC-API-3', 'fleet_name' => '']],
        ])->assertUnprocessable()->assertJsonValidationErrors('company_id', 'error.details');
    }

    public function test_operators_and_read_only_tokens_are_refused(): void
    {
        $rows = ['rows' => [['name' => 'Ana', 'license_no' => 'LIC-1', 'fleet_name' => '']]];

        $this->importJson('drivers', $this->apiToken($this->world['operator']), $rows)->assertForbidden();
        $this->importJson('drivers', $this->apiToken($this->world['manager'], ['fleet:read']), $rows)->assertForbidden();
        $this->importJson('drivers', null, $rows)->assertUnauthorized();
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $response = $this->importJson('vehicles', $this->apiToken($this->world['manager']), [
            'dry_run' => true,
            'rows' => [['plate_no' => 'API-001', 'fuel_type' => 'diesel', 'tank_capacity_l' => '50', 'fleet_name' => '']],
        ]);

        $response->assertOk()->assertJsonPath('data.dry_run', true)->assertJsonPath('data.successful', 1);
        $this->assertSame(0, Vehicle::query()->where('plate_no', 'API-001')->count());
    }

    public function test_a_wrong_header_and_too_many_rows_are_422(): void
    {
        $token = $this->apiToken($this->world['manager']);

        $this->upload('vehicles', "name,plate\nA,B\n", $token)->assertUnprocessable();

        $rows = [];
        for ($i = 1; $i <= 5001; $i++) {
            $rows[] = ['name' => 'X'.$i, 'license_no' => 'L'.$i, 'fleet_name' => ''];
        }
        $this->importJson('drivers', $token, ['rows' => $rows])->assertUnprocessable();
    }

    public function test_the_template_downloads(): void
    {
        $response = $this->api('GET', '/api/v1/import/templates/vehicles', $this->apiToken($this->world['manager']));

        $response = $this->assertMatchesOpenApi($response, 'GET', '/import/templates/{type}');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('plate_no,fuel_type,tank_capacity_l,odometer_km,fleet_name', (string) $response->getContent());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function importJson(string $type, ?string $token, array $body): TestResponse
    {
        return $this->assertMatchesOpenApi(
            $this->api('POST', '/api/v1/import/'.$type, $token, $body),
            'POST',
            '/import/{type}',
        );
    }

    /**
     * @return TestResponse<Response>
     */
    private function upload(string $type, string $csv, ?string $token): TestResponse
    {
        $this->startNewRequestCycle();

        return $this->assertMatchesOpenApi(
            $this->withToken((string) $token)->post('/api/v1/import/'.$type, [
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ], ['Accept' => 'application/json']),
            'POST',
            '/import/{type}',
        );
    }
}
