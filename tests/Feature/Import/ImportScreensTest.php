<?php

namespace Tests\Feature\Import;

use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The bulk import screen (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * A2): who may open it, the template download, an import with visible
 * results, the admin's company choice and the manager's server-side company.
 */
class ImportScreensTest extends TestCase
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

    public function test_the_page_opens_for_admins_and_managers_and_not_operators(): void
    {
        $this->actingAs($this->world['admin'])->get('/import')->assertOk()->assertSee('Importación masiva');
        $this->actingAs($this->world['manager'])->get('/import')->assertOk()->assertSee('Importación masiva');
        $this->actingAs($this->world['operator'])->get('/import')->assertForbidden();
    }

    public function test_the_template_downloads_with_the_exact_header(): void
    {
        $response = $this->actingAs($this->world['manager'])->get(route('import.template', 'vehicles'))->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith(
            'plate_no,fuel_type,tank_capacity_l,odometer_km,fleet_name',
            (string) $response->getContent(),
        );
    }

    public function test_a_manager_imports_and_sees_the_result(): void
    {
        $csv = "plate_no,fuel_type,tank_capacity_l,odometer_km,fleet_name\n"
            ."WEB-001,diesel,50,1000,\n"
            ."WEB-002,diesel,50,1000,\n";

        $this->actingAs($this->world['manager'])
            ->post(route('import.store', 'vehicles'), [
                'file' => UploadedFile::fake()->createWithContent('vehicles.csv', $csv),
            ])
            ->assertRedirect(route('import.index'));

        $this->assertSame(2, Vehicle::query()->where('plate_no', 'like', 'WEB-%')->count());

        $this->actingAs($this->world['manager'])
            ->get('/import')
            ->assertOk()
            ->assertSee('Resultado de la importación')
            ->assertSee('Guardadas');
    }

    public function test_an_admin_must_choose_an_active_company(): void
    {
        $csv = "plate_no,fuel_type,tank_capacity_l,odometer_km,fleet_name\nWEB-001,diesel,50,1000,\n";
        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csv);

        $this->actingAs($this->world['admin'])
            ->post(route('import.store', 'vehicles'), ['file' => $file])
            ->assertSessionHasErrors(['company_id']);

        $this->actingAs($this->world['admin'])
            ->post(route('import.store', 'vehicles'), [
                'company_id' => (string) $this->world['company']->id,
                'file' => UploadedFile::fake()->createWithContent('vehicles.csv', $csv),
            ])
            ->assertRedirect(route('import.index'));

        $this->assertSame(1, Vehicle::query()->where('plate_no', 'WEB-001')->count());
    }

    public function test_a_manager_cannot_choose_a_company(): void
    {
        $csv = "plate_no,fuel_type,tank_capacity_l,odometer_km,fleet_name\nWEB-001,diesel,50,1000,\n";

        $this->actingAs($this->world['manager'])
            ->post(route('import.store', 'vehicles'), [
                'company_id' => (string) $this->world['otherCompany']->id,
                'file' => UploadedFile::fake()->createWithContent('vehicles.csv', $csv),
            ])
            ->assertSessionHasErrors(['company_id']);

        $this->assertSame(0, Vehicle::query()->where('plate_no', 'WEB-001')->count());
    }

    public function test_a_wrong_header_is_shown_as_a_file_error(): void
    {
        $this->actingAs($this->world['manager'])
            ->post(route('import.store', 'vehicles'), [
                'file' => UploadedFile::fake()->createWithContent('vehicles.csv', "name,plate\nA,B\n"),
            ])
            ->assertSessionHasErrors(['file']);

        $this->assertSame(0, Vehicle::query()->where('plate_no', 'A')->count());
    }
}
