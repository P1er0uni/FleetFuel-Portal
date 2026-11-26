<?php

namespace Tests\Feature\Fleets;

use App\Enums\FleetRuleType;
use App\Enums\RuleEnforcement;
use App\Models\Fleet;
use App\Services\FleetService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * Fleet rules editor and create-with-rules (docs/sdd/fase-5-reglas-limites-flota):
 * clear per-type fields, initial rule on create, enforcement/validity, and
 * window validation.
 */
class FleetRuleEditorTest extends TestCase
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

    public function test_a_manager_creates_a_fleet_with_an_initial_allowed_stations_rule(): void
    {
        $this->actingAs($this->world['manager'])
            ->post('/fleets', [
                'name' => 'Flota con estación',
                'add_rule' => 1,
                'rule' => [
                    'rule_type' => 'allowed_stations',
                    'enforcement' => 'block',
                    'active' => '1',
                    'ids' => [(string) $this->world['station']->id],
                ],
            ])
            ->assertRedirect();

        $fleet = Fleet::query()->where('name', 'Flota con estación')->sole();
        $rule = $fleet->rules()->where('rule_type', FleetRuleType::AllowedStations->value)->sole();
        $this->assertSame(['ids' => [$this->world['station']->id]], $rule->rule_value);
        $this->assertSame(RuleEnforcement::Block, $rule->enforcement);
    }

    public function test_an_invalid_initial_rule_prevents_the_fleet(): void
    {
        $this->actingAs($this->world['manager'])
            ->post('/fleets', [
                'name' => 'Flota inválida',
                'add_rule' => 1,
                'rule' => ['rule_type' => 'allowed_products', 'active' => '1'],
            ])
            ->assertSessionHasErrors('rule.ids');

        $this->assertSame(0, Fleet::query()->where('name', 'Flota inválida')->count());
    }

    public function test_the_editor_shows_separate_product_and_station_fields(): void
    {
        $fleet = app(FleetService::class)->create($this->world['company'], ['name' => 'Editor'], $this->world['admin']);

        $this->actingAs($this->world['manager'])->get(route('fleets.edit', $fleet))
            ->assertOk()
            ->assertSee('Productos permitidos')
            ->assertSee('Estaciones permitidas')
            ->assertSee('Solo aviso');
    }

    public function test_a_rule_is_saved_with_enforcement_and_validity(): void
    {
        $fleet = app(FleetService::class)->create($this->world['company'], ['name' => 'Con vigencia'], $this->world['admin']);

        $this->actingAs($this->world['manager'])
            ->post(route('fleets.rules.store', $fleet), [
                'rule_type' => 'max_liters_per_day',
                'value' => '100',
                'enforcement' => 'warn',
                'valid_from' => '2026-09-01',
                'valid_to' => '2026-12-31',
                'active' => '1',
            ])
            ->assertRedirect(route('fleets.show', $fleet));

        $rule = $fleet->rules()->sole();
        $this->assertSame(RuleEnforcement::Warn, $rule->enforcement);
        $this->assertSame('2026-09-01', $rule->valid_from?->format('Y-m-d'));
        $this->assertSame('100', $rule->rule_value['value']);
    }

    public function test_a_window_rule_needs_at_least_one_window(): void
    {
        $fleet = app(FleetService::class)->create($this->world['company'], ['name' => 'Horarios'], $this->world['admin']);

        $this->actingAs($this->world['manager'])
            ->post(route('fleets.rules.store', $fleet), [
                'rule_type' => 'allowed_time_windows',
                'active' => '1',
            ])
            ->assertSessionHasErrors('windows');
    }
}
