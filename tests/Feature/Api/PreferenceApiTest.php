<?php

namespace Tests\Feature\Api;

use App\Models\Fleet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\Concerns\CallsApi;
use Tests\Concerns\ChecksOpenApiContract;
use Tests\TestCase;

/**
 * /api/v1/preferences (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part C): read all, update a partial map, read/write one key, the manager's
 * fleet scope and the abilities.
 */
class PreferenceApiTest extends TestCase
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

    public function test_defaults_are_returned_then_a_partial_update_is_applied(): void
    {
        $token = $this->apiToken($this->world['manager']);

        $this->preferences('GET', '/preferences', '/preferences', $token)
            ->assertOk()
            ->assertJsonPath('data.items_per_page', 15)
            ->assertJsonPath('data.date_format', 'd/m/Y')
            ->assertJsonPath('data.default_fleet_id', null);

        $fleet = Fleet::factory()->create(['company_id' => $this->world['company']->id]);

        $this->preferences('PUT', '/preferences', '/preferences', $token, [
            'default_fleet_id' => $fleet->id,
            'items_per_page' => 10,
            'date_format' => 'Y-m-d',
        ])->assertOk()
            ->assertJsonPath('data.default_fleet_id', $fleet->id)
            ->assertJsonPath('data.items_per_page', 10)
            ->assertJsonPath('data.date_format', 'Y-m-d');
    }

    public function test_a_single_key_can_be_read_and_written(): void
    {
        $token = $this->apiToken($this->world['manager']);

        $this->preferences('PUT', '/preferences/map_default_zoom', '/preferences/{key}', $token, ['value' => 15])
            ->assertOk()->assertJsonPath('data.value', 15);

        $this->preferences('GET', '/preferences/map_default_zoom', '/preferences/{key}', $token)
            ->assertOk()->assertJsonPath('data.value', 15);
    }

    public function test_the_default_fleet_must_belong_to_the_managers_company(): void
    {
        $foreign = Fleet::factory()->create(['company_id' => $this->world['otherCompany']->id]);

        $this->preferences('PUT', '/preferences', '/preferences', $this->apiToken($this->world['manager']), [
            'default_fleet_id' => $foreign->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('default_fleet_id', 'error.details');
    }

    public function test_unknown_keys_and_fields_are_refused(): void
    {
        $token = $this->apiToken($this->world['manager']);

        $this->preferences('PUT', '/preferences', '/preferences', $token, ['bogus' => 1])->assertUnprocessable();
        $this->preferences('PUT', '/preferences/not_a_key', '/preferences/{key}', $token, ['value' => 1])->assertNotFound();
    }

    public function test_all_roles_read_preferences_and_a_read_only_token_cannot_write(): void
    {
        $this->preferences('GET', '/preferences', '/preferences', $this->apiToken($this->world['operator']))->assertOk();

        $this->preferences('PUT', '/preferences', '/preferences', $this->apiToken($this->world['manager'], ['preferences:read']), ['items_per_page' => 10])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function preferences(string $method, string $path, string $template, ?string $token, array $body = []): TestResponse
    {
        return $this->assertMatchesOpenApi($this->api($method, '/api/v1'.$path, $token, $body), $method, $template);
    }
}
