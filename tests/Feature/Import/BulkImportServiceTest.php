<?php

namespace Tests\Feature\Import;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Fleet;
use App\Models\FuelCard;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BulkImportService;
use App\Support\CsvImport\CsvTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The bulk import service (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * A1): per-row writing, per-row errors, dry runs, fleet and member resolution,
 * the size limit and the single audit row per import.
 */
class BulkImportServiceTest extends TestCase
{
    use BuildsLedgerFixtures;
    use RefreshDatabase;

    private BulkImportService $imports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FIXTURE_NOW));
        $this->imports = app(BulkImportService::class);
    }

    public function test_ten_valid_vehicles_are_all_inserted(): void
    {
        $company = Company::factory()->create();
        $admin = $this->admin();
        $fleet = Fleet::factory()->create(['company_id' => $company->id, 'name' => 'Camiones']);

        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [sprintf('IMP-%03d', $i), 'diesel', '50', '1000', 'Camiones'];
        }

        $result = $this->imports->import(CsvTemplate::VEHICLES, $company, $admin, false, csv: $this->csv(CsvTemplate::VEHICLES, $rows));

        $this->assertSame(10, $result->totalRows);
        $this->assertSame(10, $result->successful);
        $this->assertSame(0, $result->failed);
        $this->assertSame(10, Vehicle::query()->where('company_id', $company->id)->count());
        $this->assertSame($fleet->id, Vehicle::query()->where('plate_no', 'IMP-001')->sole()->fleet_id);
        $this->assertSame(1, AuditLog::query()->where('action', 'import.completed')->count());
    }

    public function test_a_duplicate_row_is_skipped_with_an_error(): void
    {
        $company = Company::factory()->create();

        $rows = [
            ['IMP-001', 'diesel', '50', '', ''],
            ['IMP-002', 'diesel', '50', '', ''],
            ['IMP-001', 'diesel', '50', '', ''],
        ];

        $result = $this->imports->import(CsvTemplate::VEHICLES, $company, $this->admin(), false, csv: $this->csv(CsvTemplate::VEHICLES, $rows));

        $this->assertSame(3, $result->totalRows);
        $this->assertSame(2, $result->successful);
        $this->assertSame(1, $result->failed);
        $this->assertSame(3, $result->errors[0]['row']);
        $this->assertSame('plate_no', $result->errors[0]['field']);
        $this->assertSame(2, Vehicle::query()->count());
    }

    public function test_an_unknown_fleet_is_a_row_error_and_an_empty_fleet_is_null(): void
    {
        $company = Company::factory()->create();
        $fleet = Fleet::factory()->create(['company_id' => $company->id, 'name' => 'Camiones']);

        $rows = [
            ['IMP-001', 'diesel', '50', '', 'Camiones'],
            ['IMP-002', 'diesel', '50', '', 'NoExiste'],
        ];

        $result = $this->imports->import(CsvTemplate::VEHICLES, $company, $this->admin(), false, csv: $this->csv(CsvTemplate::VEHICLES, $rows));

        $this->assertSame(1, $result->successful);
        $this->assertSame(1, $result->failed);
        $this->assertSame('fleet_name', $result->errors[0]['field']);
        $this->assertSame($fleet->id, Vehicle::query()->where('plate_no', 'IMP-001')->sole()->fleet_id);

        // An empty fleet_name resolves to NULL.
        $this->imports->import(CsvTemplate::VEHICLES, $company, $this->admin(), false,
            csv: $this->csv(CsvTemplate::VEHICLES, [['IMP-009', 'diesel', '50', '', '']]));
        $this->assertNull(Vehicle::query()->where('plate_no', 'IMP-009')->sole()->fleet_id);
    }

    public function test_a_card_pointing_at_a_missing_vehicle_is_a_row_error(): void
    {
        $company = Company::factory()->create();

        $rows = [
            ['FC-1', 'ZZZ-999', '', '500', 'active', ''],
        ];

        $result = $this->imports->import(CsvTemplate::CARDS, $company, $this->admin(), false, csv: $this->csv(CsvTemplate::CARDS, $rows));

        $this->assertSame(0, $result->successful);
        $this->assertSame(1, $result->failed);
        $this->assertSame('vehicle_plate', $result->errors[0]['field']);
        $this->assertSame(0, FuelCard::query()->count());
    }

    public function test_a_card_is_linked_to_its_vehicle_and_driver_of_the_same_company(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'plate_no' => 'CAR-100']);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'license_no' => 'LIC-9']);

        $rows = [['FC-AAA-001', 'car-100', 'lic-9', '250.5', 'blocked', '']];

        $result = $this->imports->import(CsvTemplate::CARDS, $company, $this->admin(), false, csv: $this->csv(CsvTemplate::CARDS, $rows));

        $this->assertSame(1, $result->successful);
        $card = FuelCard::query()->sole();
        $this->assertSame($vehicle->id, $card->vehicle_id);
        $this->assertSame($driver->id, $card->driver_id);
        $this->assertSame('blocked', $card->status->value);
        $this->assertSame('250.50', $card->monthly_limit_l);
        $this->assertNull($card->monthly_limit_usd);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $company = Company::factory()->create();
        $rows = [
            ['IMP-001', 'diesel', '50', '', ''],
            ['IMP-002', 'gasoline', '50', '', ''],
        ];

        $result = $this->imports->import(CsvTemplate::VEHICLES, $company, $this->admin(), true, csv: $this->csv(CsvTemplate::VEHICLES, $rows));

        $this->assertTrue($result->dryRun);
        $this->assertSame(2, $result->successful);
        $this->assertSame(0, Vehicle::query()->count());
    }

    public function test_more_than_five_thousand_rows_is_refused(): void
    {
        $company = Company::factory()->create();
        $rows = [];
        for ($i = 1; $i <= 5001; $i++) {
            $rows[] = [sprintf('IMP-%05d', $i), 'diesel', '50', '', ''];
        }

        $this->expectException(ValidationException::class);
        $this->imports->import(CsvTemplate::VEHICLES, $company, $this->admin(), true, csv: $this->csv(CsvTemplate::VEHICLES, $rows));
    }

    public function test_a_duplicate_against_the_database_is_a_row_error(): void
    {
        $company = Company::factory()->create();
        Vehicle::factory()->create(['company_id' => $company->id, 'plate_no' => 'EXIST-1']);

        $result = $this->imports->import(CsvTemplate::VEHICLES, $company, $this->admin(), false,
            csv: $this->csv(CsvTemplate::VEHICLES, [['EXIST-1', 'diesel', '50', '', '']]));

        $this->assertSame(0, $result->successful);
        $this->assertSame(1, $result->failed);
        $this->assertSame('plate_no', $result->errors[0]['field']);
    }

    public function test_a_wrong_header_is_refused(): void
    {
        $company = Company::factory()->create();
        $csv = "name,plate\nA,B\n";

        $this->expectException(ValidationException::class);
        $this->imports->import(CsvTemplate::VEHICLES, $company, $this->admin(), false, csv: $csv);
    }

    public function test_rows_can_be_sent_as_an_array(): void
    {
        $company = Company::factory()->create();

        $result = $this->imports->import(CsvTemplate::DRIVERS, $company, $this->admin(), false, rows: [
            ['name' => 'Juan Perez', 'phone' => '+56912345678', 'license_no' => 'ABC1', 'fleet_name' => ''],
        ]);

        $this->assertSame(1, $result->successful);
        $this->assertSame(1, Driver::query()->count());
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(string $type, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        assert($stream !== false);
        fputcsv($stream, CsvTemplate::header($type), ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '\\');
        }
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
