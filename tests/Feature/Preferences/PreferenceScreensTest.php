<?php

namespace Tests\Feature\Preferences;

use App\Models\Fleet;
use App\Models\Notification;
use App\Models\UserPreference;
use App\Services\UserPreferenceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The preferences page and its effects (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part C): the form, items per page, and the default fleet preselection.
 */
class PreferenceScreensTest extends TestCase
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

    public function test_the_preferences_page_opens_for_every_role(): void
    {
        $this->actingAs($this->world['manager'])->get('/preferences')->assertOk()->assertSee('Preferencias');
        $this->actingAs($this->world['operator'])->get('/preferences')->assertOk();
    }

    public function test_the_form_saves_the_values(): void
    {
        $this->actingAs($this->world['manager'])
            ->put(route('preferences.update'), [
                'items_per_page' => 10,
                'date_format' => 'Y-m-d',
                'language' => 'es',
                'map_default_zoom' => 14,
                'map_default_lat' => '34.10',
                'map_default_lng' => '35.20',
                'dashboard_widgets' => ['summary', 'recent_transactions'],
            ])
            ->assertRedirect(route('preferences.edit'));

        $stored = UserPreference::query()->where('user_id', $this->world['manager']->id)->pluck('value', 'key');
        $this->assertSame('10', $stored['items_per_page']);
        $this->assertSame('Y-m-d', $stored['date_format']);
        $this->assertSame('["summary","recent_transactions"]', $stored['dashboard_widgets']);
    }

    public function test_items_per_page_is_used_by_the_notifications_list(): void
    {
        app(UserPreferenceService::class)->set((int) $this->world['manager']->id, 'items_per_page', '10');
        Notification::factory()->forUser($this->world['manager'])->count(12)->create(['event_type' => 'quota_warning']);

        $page = $this->actingAs($this->world['manager'])->get('/notifications')->assertOk();

        $this->assertSame(10, $page->viewData('notifications')->perPage());
        $this->assertSame(10, $page->viewData('notifications')->count());
    }

    public function test_the_default_fleet_is_preselected_in_reports(): void
    {
        $fleet = Fleet::factory()->create(['company_id' => $this->world['company']->id, 'name' => 'Camiones']);
        app(UserPreferenceService::class)->set((int) $this->world['manager']->id, 'default_fleet_id', (string) $fleet->id);

        $page = $this->actingAs($this->world['manager'])->get('/reports/consumption')->assertOk();

        $this->assertSame($fleet->id, $page->viewData('defaultFleetId'));
        $content = (string) $page->getContent();
        $this->assertMatchesRegularExpression('/value="'.$fleet->id.'"[^>]*selected/', $content);
    }
}
