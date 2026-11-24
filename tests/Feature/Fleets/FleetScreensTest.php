<?php

namespace Tests\Feature\Fleets;

use App\Models\AuditLog;
use App\Models\Fleet;
use App\Models\FuelCard;
use App\Services\FleetService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The fleet screens (docs/sdd/fase-1-flotas/spec.md): the full CRUD over
 * HTTP, member assignment, rules, status and the tenant isolation a manager
 * gets through the {fleet} route binding.
 */
class FleetScreensTest extends TestCase
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

    public function test_the_list_shows_each_companys_fleets_only(): void
    {
        $this->createFleet('Camiones');
        Fleet::factory()->create(['company_id' => $this->world['otherCompany']->id, 'name' => 'Flota ajena']);

        $this->actingAs($this->world['manager'])
            ->get('/fleets')
            ->assertOk()
            ->assertSee('Camiones')
            ->assertDontSee('Flota ajena');

        // An admin sees every fleet and may narrow by company.
        $this->actingAs($this->world['admin'])
            ->get('/fleets')
            ->assertOk()
            ->assertSee('Camiones')
            ->assertSee('Flota ajena');

        // Station operators have no fleet screens at all.
        $this->actingAs($this->world['operator'])->get('/fleets')->assertForbidden();
    }

    public function test_a_manager_creates_a_fleet_for_their_own_company(): void
    {
        $response = $this->actingAs($this->world['manager'])
            ->from('/fleets/create')
            ->post('/fleets', [
                'name' => 'Camiones de carga',
                'description' => 'Tráileres de larga distancia',
                'monthly_quota_liters' => '500.000',
                'monthly_quota_amount' => '450.00',
            ]);

        $fleet = Fleet::query()->sole();
        $response->assertRedirect(route('fleets.show', $fleet));

        $this->assertSame($this->world['company']->id, $fleet->company_id);
        $this->assertSame('500.000', $fleet->monthly_quota_liters);
        $this->assertSame(1, AuditLog::query()->where('action', 'fleet.created')->count());
    }

    public function test_an_admin_must_pick_an_active_company_when_creating(): void
    {
        $this->actingAs($this->world['admin'])
            ->post('/fleets', ['name' => 'Camiones'])
            ->assertSessionHasErrors(['company_id' => 'Elige una empresa.']);

        $this->actingAs($this->world['admin'])
            ->post('/fleets', ['name' => 'Camiones', 'company_id' => (string) $this->world['company']->id])
            ->assertRedirect(route('fleets.show', Fleet::query()->sole()));
    }

    public function test_a_duplicate_name_within_a_company_is_refused(): void
    {
        $this->createFleet('Camiones');

        $this->actingAs($this->world['manager'])
            ->post('/fleets', ['name' => 'Camiones'])
            ->assertSessionHasErrors(['name' => 'Esta empresa ya tiene una flota con ese nombre.']);

        $this->assertSame(1, Fleet::query()->count());
    }

    public function test_the_detail_page_shows_quota_members_and_rules(): void
    {
        $fleet = $this->createFleet('Camiones');
        $card = FuelCard::factory()->create(['company_id' => $this->world['company']->id]);
        app(FleetService::class)->assign($fleet, [
            'vehicles' => [$this->world['vehicle']->id],
            'cards' => [$card->id],
        ], $this->world['admin']);

        $this->actingAs($this->world['manager'])
            ->get(route('fleets.show', $fleet))
            ->assertOk()
            ->assertSee('Camiones')
            ->assertSee($this->world['vehicle']->plate_no)
            ->assertSee('••••'.substr($card->card_no, -4))
            ->assertSee('Dentro de cuota')
            ->assertSee('Agregar o reemplazar una regla');
    }

    public function test_a_fleet_is_updated_and_its_name_must_stay_unique(): void
    {
        $fleet = $this->createFleet('Camiones');
        $this->createFleet('Furgonetas');

        $this->actingAs($this->world['manager'])
            ->put(route('fleets.update', $fleet), [
                'name' => 'Furgonetas',
                'monthly_quota_liters' => '100.000',
            ])
            ->assertSessionHasErrors(['name' => 'Esta empresa ya tiene una flota con ese nombre.']);

        $this->actingAs($this->world['manager'])
            ->put(route('fleets.update', $fleet), [
                'name' => 'Camiones pesados',
                'monthly_quota_liters' => '100.000',
                'monthly_quota_amount' => '',
            ])
            ->assertRedirect(route('fleets.show', $fleet));

        $fleet->refresh();
        $this->assertSame('Camiones pesados', $fleet->name);
        $this->assertSame('100.000', $fleet->monthly_quota_liters);
        $this->assertSame(1, AuditLog::query()->where('action', 'fleet.updated')->count());
    }

    public function test_the_status_is_toggled_over_http(): void
    {
        $fleet = $this->createFleet('Camiones');

        $this->actingAs($this->world['manager'])
            ->patch(route('fleets.status', $fleet), ['status' => 'inactive'])
            ->assertRedirect(route('fleets.show', $fleet));

        $this->assertSame('inactive', $fleet->refresh()->status->value);
        $this->assertSame(1, AuditLog::query()->where('action', 'fleet.status_changed')->count());
    }

    public function test_deleting_a_fleet_ungroups_its_members(): void
    {
        $fleet = $this->createFleet('Camiones');
        $card = FuelCard::factory()->create(['company_id' => $this->world['company']->id]);
        app(FleetService::class)->assign($fleet, ['cards' => [$card->id]], $this->world['admin']);

        $this->actingAs($this->world['manager'])
            ->delete(route('fleets.destroy', $fleet))
            ->assertRedirect(route('fleets.index'));

        $this->assertSame(0, Fleet::query()->count());
        $this->assertNull($card->refresh()->fleet_id);
        $this->assertSame(1, AuditLog::query()->where('action', 'fleet.deleted')->count());
    }

    public function test_members_are_assigned_and_unassigned_over_http(): void
    {
        $fleet = $this->createFleet('Camiones');
        $card = FuelCard::factory()->create(['company_id' => $this->world['company']->id]);

        $this->actingAs($this->world['manager'])
            ->post(route('fleets.assign', $fleet), ['cards' => [(string) $card->id]])
            ->assertRedirect(route('fleets.show', $fleet));
        $this->assertSame($fleet->id, $card->refresh()->fleet_id);

        // A card of another company is refused and changes nothing.
        $foreign = FuelCard::factory()->create(['company_id' => $this->world['otherCompany']->id]);
        $this->actingAs($this->world['manager'])
            ->post(route('fleets.assign', $fleet), ['cards' => [(string) $foreign->id]])
            ->assertSessionHasErrors(['cards.0']);
        $this->assertNull($foreign->refresh()->fleet_id);

        $this->actingAs($this->world['manager'])
            ->post(route('fleets.unassign', $fleet), ['cards' => [(string) $card->id]])
            ->assertRedirect(route('fleets.show', $fleet));
        $this->assertNull($card->refresh()->fleet_id);
    }

    public function test_rules_are_saved_and_deleted_over_http(): void
    {
        $fleet = $this->createFleet('Camiones');

        $this->actingAs($this->world['manager'])
            ->post(route('fleets.rules.store', $fleet), [
                'rule_type' => 'max_liters_per_transaction',
                'value' => '50.00',
                'active' => '1',
            ])
            ->assertRedirect(route('fleets.show', $fleet));

        $rule = $fleet->rules()->sole();
        $this->assertSame(['value' => '50.00'], $rule->rule_value);
        $this->assertTrue($rule->active);

        // Saving the same type again replaces it.
        $this->actingAs($this->world['manager'])
            ->post(route('fleets.rules.store', $fleet), [
                'rule_type' => 'max_liters_per_transaction',
                'value' => '60.00',
                'active' => '1',
            ]);
        $this->assertSame(1, $fleet->rules()->count());

        $this->actingAs($this->world['manager'])
            ->delete(route('fleets.rules.destroy', [$fleet, $rule]))
            ->assertRedirect(route('fleets.show', $fleet));
        $this->assertSame(0, $fleet->rules()->count());
    }

    public function test_a_list_rule_needs_ids_and_a_numeric_rule_needs_a_value(): void
    {
        $fleet = $this->createFleet('Camiones');

        $this->actingAs($this->world['manager'])
            ->post(route('fleets.rules.store', $fleet), ['rule_type' => 'allowed_products', 'active' => '1'])
            ->assertSessionHasErrors(['ids']);

        $this->actingAs($this->world['manager'])
            ->post(route('fleets.rules.store', $fleet), ['rule_type' => 'max_liters_per_transaction', 'active' => '1'])
            ->assertSessionHasErrors(['value']);
    }

    public function test_a_manager_cannot_touch_another_companys_fleet(): void
    {
        $foreign = Fleet::factory()->create(['company_id' => $this->world['otherCompany']->id, 'name' => 'Flota ajena']);

        $this->actingAs($this->world['manager'])->get(route('fleets.show', $foreign))->assertNotFound();
        $this->actingAs($this->world['manager'])->get(route('fleets.edit', $foreign))->assertNotFound();
        $this->actingAs($this->world['manager'])
            ->put(route('fleets.update', $foreign), ['name' => 'Robo'])
            ->assertNotFound();
        $this->actingAs($this->world['manager'])->delete(route('fleets.destroy', $foreign))->assertNotFound();
        $this->actingAs($this->world['manager'])
            ->post(route('fleets.assign', $foreign), ['cards' => []])
            ->assertNotFound();

        $this->assertSame('Flota ajena', $foreign->refresh()->name);
    }

    private function createFleet(string $name): Fleet
    {
        return app(FleetService::class)->create($this->world['company'], ['name' => $name], $this->world['admin']);
    }
}
