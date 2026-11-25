<?php

namespace Tests\Feature\Preferences;

use App\Services\UserPreferenceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The dashboard honours the user's `dashboard_widgets` (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part C): it renders only the chosen widgets and silently skips unknown ones.
 */
class DashboardPreferenceTest extends TestCase
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

    public function test_only_the_chosen_widgets_are_rendered(): void
    {
        app(UserPreferenceService::class)->set((int) $this->world['manager']->id, 'dashboard_widgets', json_encode(['summary']));

        $page = $this->actingAs($this->world['manager'])->get('/dashboard')->assertOk();

        $this->assertSame(['summary'], $page->viewData('widgets'));
        $page->assertSee('Litros este mes')
            ->assertDontSee('Alertas de cuota')
            ->assertDontSee('Compras recientes')
            ->assertDontSee('Consumo por flota');
    }

    public function test_an_unknown_widget_is_skipped_without_an_error(): void
    {
        app(UserPreferenceService::class)->set((int) $this->world['manager']->id, 'dashboard_widgets', json_encode(['summary', 'ghost_widget']));

        $this->actingAs($this->world['manager'])->get('/dashboard')
            ->assertOk()
            ->assertSee('Litros este mes');
    }
}
