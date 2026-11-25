<?php

namespace Tests\Feature\Favorites;

use App\Models\Fleet;
use App\Models\UserFavorite;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * Favourites over the web (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part B): the AJAX star toggle, the dashboard panel and the star on a fleet.
 */
class FavoriteScreensTest extends TestCase
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

    public function test_the_star_toggles_over_ajax(): void
    {
        $this->actingAs($this->world['manager'])
            ->postJson(route('favorites.toggle'), ['type' => 'vehicle', 'favoritable_id' => $this->world['vehicle']->id])
            ->assertOk()->assertJsonPath('favorited', true);

        $this->assertSame(1, UserFavorite::query()->count());

        $this->actingAs($this->world['manager'])
            ->postJson(route('favorites.toggle'), ['type' => 'vehicle', 'favoritable_id' => $this->world['vehicle']->id])
            ->assertOk()->assertJsonPath('favorited', false);

        $this->assertSame(0, UserFavorite::query()->count());
    }

    public function test_the_dashboard_shows_the_favorites_panel(): void
    {
        $fleet = Fleet::factory()->create(['company_id' => $this->world['company']->id, 'name' => 'Camiones']);
        UserFavorite::factory()->forUser($this->world['manager'])->of('fleet', $fleet->id)->create();

        $this->actingAs($this->world['manager'])->get('/dashboard')
            ->assertOk()
            ->assertSee('Favoritos')
            ->assertSee('Flota: Camiones');
    }

    public function test_the_fleet_page_renders_the_star(): void
    {
        $fleet = Fleet::factory()->create(['company_id' => $this->world['company']->id, 'name' => 'Camiones']);

        $this->actingAs($this->world['manager'])->get(route('fleets.show', $fleet))
            ->assertOk()
            ->assertSee('favorite-star', false);
    }

    public function test_a_favorite_is_removed_from_the_dashboard(): void
    {
        $favorite = UserFavorite::factory()->forUser($this->world['manager'])->of('vehicle', $this->world['vehicle']->id)->create();

        $this->actingAs($this->world['manager'])
            ->deleteJson(route('favorites.destroy', $favorite->id))
            ->assertOk()->assertJsonPath('removed', true);

        $this->assertSame(0, UserFavorite::query()->count());
    }
}
