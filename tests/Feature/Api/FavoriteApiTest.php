<?php

namespace Tests\Feature\Api;

use App\Models\Fleet;
use App\Models\UserFavorite;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\Concerns\CallsApi;
use Tests\Concerns\ChecksOpenApiContract;
use Tests\TestCase;

/**
 * /api/v1/favorites (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part B): add, list, duplicate, delete, reorder, scope and abilities.
 */
class FavoriteApiTest extends TestCase
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

    public function test_a_favorite_is_added_and_listed_with_a_default_label(): void
    {
        $fleet = Fleet::factory()->create(['company_id' => $this->world['company']->id, 'name' => 'Camiones']);
        $token = $this->apiToken($this->world['manager']);

        $created = $this->favorites('POST', '/favorites', '/favorites', $token, [
            'favoritable_type' => 'fleet',
            'favoritable_id' => $fleet->id,
        ])->assertCreated();

        $created->assertJsonPath('data.type', 'fleet')
            ->assertJsonPath('data.label', 'Flota: Camiones')
            ->assertJsonPath('data.url', route('fleets.show', $fleet));

        $this->favorites('GET', '/favorites', '/favorites', $token)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.favoritable_id', $fleet->id);
    }

    public function test_a_duplicate_is_422(): void
    {
        $vehicle = $this->world['vehicle'];
        $token = $this->apiToken($this->world['manager']);
        $body = ['favoritable_type' => 'vehicle', 'favoritable_id' => $vehicle->id];

        $this->favorites('POST', '/favorites', '/favorites', $token, $body)->assertCreated();
        $this->favorites('POST', '/favorites', '/favorites', $token, $body)->assertUnprocessable();
        $this->assertSame(1, UserFavorite::query()->count());
    }

    public function test_an_entity_of_another_company_is_404(): void
    {
        $foreign = Fleet::factory()->create(['company_id' => $this->world['otherCompany']->id]);

        $this->favorites('POST', '/favorites', '/favorites', $this->apiToken($this->world['manager']), [
            'favoritable_type' => 'fleet',
            'favoritable_id' => $foreign->id,
        ])->assertNotFound();

        $this->assertSame(0, UserFavorite::query()->count());
    }

    public function test_a_favorite_is_deleted(): void
    {
        $favorite = UserFavorite::factory()->forUser($this->world['manager'])->of('vehicle', $this->world['vehicle']->id)->create();

        $this->favorites('DELETE', '/favorites/'.$favorite->id, '/favorites/{id}', $this->apiToken($this->world['manager']))->assertNoContent();
        $this->assertSame(0, UserFavorite::query()->count());
    }

    public function test_favorites_are_reordered(): void
    {
        $token = $this->apiToken($this->world['manager']);
        $first = UserFavorite::factory()->forUser($this->world['manager'])->of('fleet', 1, '0')->create();
        $second = UserFavorite::factory()->forUser($this->world['manager'])->of('vehicle', 2, '1')->create();

        $this->favorites('PUT', '/favorites/reorder', '/favorites/reorder', $token, ['ids' => [$second->id, $first->id]])
            ->assertOk()->assertJsonPath('data.reordered', 2);

        $this->assertSame(0, $second->refresh()->position);
        $this->assertSame(1, $first->refresh()->position);

        $order = array_column($this->favorites('GET', '/favorites', '/favorites', $token)->json('data'), 'id');
        $this->assertSame([$second->id, $first->id], $order);
    }

    public function test_all_roles_may_use_favorites_but_a_read_only_token_cannot_write(): void
    {
        $operator = $this->apiToken($this->world['operator']);
        $this->favorites('POST', '/favorites', '/favorites', $operator, [
            'favoritable_type' => 'station', 'favoritable_id' => $this->world['station']->id,
        ])->assertCreated();

        $readOnly = $this->apiToken($this->world['manager'], ['favorites:read']);
        $this->favorites('POST', '/favorites', '/favorites', $readOnly, [
            'favoritable_type' => 'vehicle', 'favoritable_id' => $this->world['vehicle']->id,
        ])->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function favorites(string $method, string $path, string $template, ?string $token, array $body = []): TestResponse
    {
        return $this->assertMatchesOpenApi($this->api($method, '/api/v1'.$path, $token, $body), $method, $template);
    }
}
