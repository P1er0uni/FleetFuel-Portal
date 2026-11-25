<?php

namespace Tests\Feature\Api;

use App\Enums\CompanyStatus;
use App\Enums\FleetRuleType;
use App\Enums\ProductCode;
use App\Models\CardMonthlyUsage;
use App\Models\Driver;
use App\Models\Fleet;
use App\Models\FleetRule;
use App\Models\FuelCard;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\Concerns\CallsApi;
use Tests\Concerns\ChecksOpenApiContract;
use Tests\Concerns\SignsInDemoAccounts;
use Tests\TestCase;

/**
 * /api/v1/fleets on the demo seed (docs/sdd/fase-1-flotas/spec.md, API
 * table): tenant scope, server-chosen company, roles and abilities,
 * strict validation, the documented shapes, the rules and usage on the
 * detail, deletion that releases the members, and the paginated member
 * lists. Every response is checked against openapi.json.
 */
class FleetsApiTest extends TestCase
{
    use BuildsLedgerFixtures;
    use CallsApi;
    use ChecksOpenApiContract;
    use RefreshDatabase;
    use SignsInDemoAccounts;

    /** The Beirut month of FIXTURE_NOW (2026-09-28T09:00Z). */
    private const MONTH = '2026-09-01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FIXTURE_NOW));
        $this->seedDemo();
    }

    public function test_a_manager_lists_only_their_companys_fleets(): void
    {
        $atlas = $this->atlas();
        $camiones = Fleet::factory()->create([
            'company_id' => $atlas->id,
            'name' => 'Camiones',
            'description' => 'Carga pesada',
            'monthly_quota_liters' => '100.000',
        ]);
        Fleet::factory()->create(['company_id' => $atlas->id, 'name' => 'Furgonetas']);
        Fleet::factory()->create(['company_id' => $this->cedar()->id, 'name' => 'Catering']);

        $response = $this->fleets('GET', '/fleets', '', $this->apiToken($this->atlasManager()))->assertOk();

        $response->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0', [
                'id' => $camiones->id,
                'company_id' => $atlas->id,
                'name' => 'Camiones',
                'description' => 'Carga pesada',
                'status' => 'active',
                'monthly_quota_liters' => '100.000',
                'monthly_quota_amount' => null,
            ]);
    }

    public function test_an_admin_lists_every_company_and_filters_by_company(): void
    {
        $token = $this->apiToken($this->admin());
        $atlas = $this->atlas();
        Fleet::factory()->count(2)->create(['company_id' => $atlas->id]);
        Fleet::factory()->create(['company_id' => $this->cedar()->id]);

        $this->fleets('GET', '/fleets', '', $token)->assertOk()->assertJsonPath('meta.total', 3);
        $this->fleets('GET', '/fleets', '?company_id='.$this->cedar()->id, $token)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.company_id', $this->cedar()->id);

        $page = $this->fleets('GET', '/fleets', '?per_page=2&page=2', $token)->assertOk();
        $page->assertJsonPath('meta.last_page', 2)->assertJsonPath('links.next', null);
    }

    public function test_managers_cannot_choose_a_company_and_operators_are_refused(): void
    {
        $manager = $this->apiToken($this->atlasManager());
        $operator = $this->apiToken($this->operator());

        $this->fleets('GET', '/fleets', '?company_id='.$this->atlas()->id, $manager)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id', 'error.details');
        $this->fleets('GET', '/fleets', '', $operator)->assertForbidden();
        $this->fleets('POST', '/fleets', '', $operator, ['name' => 'X'])->assertForbidden();
        $this->fleets('GET', '/fleets', '', null)->assertUnauthorized();
        // A read-only fleet token cannot create.
        $this->fleets('POST', '/fleets', '', $this->apiToken($this->atlasManager(), ['fleet:read']), ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_a_manager_creates_a_fleet_in_their_own_company(): void
    {
        $response = $this->fleets('POST', '/fleets', '', $this->apiToken($this->atlasManager()), [
            'name' => 'Camiones',
            'description' => 'Carga pesada',
            'monthly_quota_liters' => '100.5',
            'monthly_quota_amount' => '500.00',
        ])->assertCreated();

        $fleet = Fleet::query()->where('name', 'Camiones')->sole();
        $this->assertSame($this->atlas()->id, $fleet->company_id);
        $response->assertExactJson(['data' => [
            'id' => $fleet->id,
            'company_id' => $this->atlas()->id,
            'name' => 'Camiones',
            'description' => 'Carga pesada',
            'status' => 'active',
            'monthly_quota_liters' => '100.500',
            'monthly_quota_amount' => '500.00',
        ]]);
        $this->assertSame('/api/v1/fleets/'.$fleet->id, $response->headers->get('Location'));
    }

    public function test_an_admin_must_name_an_active_company_as_a_json_integer(): void
    {
        $token = $this->apiToken($this->admin());
        $valid = ['name' => 'Catering'];
        $cedar = $this->cedar();

        $this->fleets('POST', '/fleets', '', $token, $valid)->assertUnprocessable()->assertJsonValidationErrors('company_id', 'error.details');
        $this->fleets('POST', '/fleets', '', $token, ['company_id' => (string) $cedar->id] + $valid)->assertUnprocessable();

        $cedar->forceFill(['status' => CompanyStatus::Inactive])->save();
        $this->fleets('POST', '/fleets', '', $token, ['company_id' => $cedar->id] + $valid)->assertUnprocessable();

        $cedar->forceFill(['status' => CompanyStatus::Active])->save();
        $this->fleets('POST', '/fleets', '', $token, ['company_id' => $cedar->id] + $valid)
            ->assertCreated()
            ->assertJsonPath('data.company_id', $cedar->id);
    }

    public function test_fleet_input_is_validated_strictly(): void
    {
        $token = $this->apiToken($this->atlasManager());
        Fleet::factory()->create(['company_id' => $this->atlas()->id, 'name' => 'Camiones']);
        $valid = ['name' => 'Furgonetas'];
        $before = Fleet::query()->count();

        $refused = [
            'name' => ['name' => 'Camiones'], // taken within the company
            'monthly_quota_liters' => ['monthly_quota_liters' => 100],
            'monthly_quota_amount' => ['monthly_quota_amount' => '50.555'],
            'status' => ['status' => 'inactive'], // never writable: the status route owns it
            'company_id' => ['company_id' => $this->atlas()->id], // a manager may not choose
        ];

        foreach ($refused as $field => $change) {
            $this->fleets('POST', '/fleets', '', $token, $change + $valid)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field, 'error.details');
        }

        $this->fleets('POST', '/fleets', '', $token, [])->assertUnprocessable()->assertJsonValidationErrors('name', 'error.details');
        $this->assertSame($before, Fleet::query()->count());

        // The same name in another company is a different fleet.
        $this->fleets('POST', '/fleets', '', $this->apiToken($this->admin()), ['name' => 'Furgonetas', 'company_id' => $this->cedar()->id])
            ->assertCreated();
    }

    public function test_a_fleet_shows_its_rules_and_current_usage(): void
    {
        $fleet = Fleet::factory()->create([
            'company_id' => $this->atlas()->id,
            'name' => 'Camiones',
            'monthly_quota_liters' => '100.000',
        ]);
        FleetRule::factory()->list(FleetRuleType::AllowedProducts, [$this->product(ProductCode::Diesel)->id])
            ->create(['fleet_id' => $fleet->id]);
        FleetRule::factory()->numeric(FleetRuleType::MaxLitersPerTransaction, '60.00')
            ->inactive()
            ->create(['fleet_id' => $fleet->id]);
        $this->addUsage($fleet, '20.50', '18.00');

        $response = $this->fleets('GET', '/fleets/'.$fleet->id, '', $this->apiToken($this->atlasManager()))->assertOk();

        $response->assertJsonPath('data.name', 'Camiones')
            ->assertJsonPath('data.rules.0.rule_type', 'allowed_products')
            ->assertJsonPath('data.rules.0.rule_value', ['ids' => [$this->product(ProductCode::Diesel)->id]])
            ->assertJsonPath('data.rules.1.rule_type', 'max_liters_per_transaction')
            ->assertJsonPath('data.rules.1.active', false)
            ->assertJsonPath('data.usage', [
                'month' => self::MONTH,
                'used_liters' => '20.50',
                'used_usd' => '18.00',
                'quota_liters' => '100.000',
                'quota_usd' => null,
                'liters_percent' => '20.5',
                'amount_percent' => null,
                'exceeded' => false,
            ]);

        // Another company's fleet is a 404, not a 403.
        $cedarFleet = Fleet::factory()->create(['company_id' => $this->cedar()->id]);
        $this->fleets('GET', '/fleets/'.$cedarFleet->id, '', $this->apiToken($this->atlasManager()))->assertNotFound();
    }

    public function test_a_fleet_is_updated_and_its_company_cannot_change(): void
    {
        $fleet = Fleet::factory()->create(['company_id' => $this->atlas()->id, 'name' => 'Camiones']);
        $token = $this->apiToken($this->atlasManager());

        $this->fleets('PUT', '/fleets/'.$fleet->id, '', $token, [
            'name' => 'Camiones pesados',
            'description' => 'Carga larga',
            'monthly_quota_liters' => '200.000',
        ])->assertOk()->assertExactJson(['data' => [
            'id' => $fleet->id,
            'company_id' => $this->atlas()->id,
            'name' => 'Camiones pesados',
            'description' => 'Carga larga',
            'status' => 'active',
            'monthly_quota_liters' => '200.000',
            'monthly_quota_amount' => null,
        ]]);

        $this->fleets('PUT', '/fleets/'.$fleet->id, '', $token, ['company_id' => $this->cedar()->id, 'name' => 'X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id', 'error.details');
        $this->fleets('PUT', '/fleets/'.$fleet->id, '', $token, ['name' => 'Furgonetas', 'monthly_quota_liters' => 200])
            ->assertUnprocessable();

        $fleet->refresh();
        $this->assertSame('Camiones pesados', $fleet->name);
        $this->assertSame($this->atlas()->id, $fleet->company_id);
    }

    public function test_a_fleet_is_deleted_and_its_members_are_released(): void
    {
        $atlas = $this->atlas();
        $fleet = Fleet::factory()->create(['company_id' => $atlas->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $atlas->id, 'fleet_id' => $fleet->id]);
        $driver = Driver::factory()->create(['company_id' => $atlas->id, 'fleet_id' => $fleet->id]);
        $card = FuelCard::factory()->create(['company_id' => $atlas->id, 'fleet_id' => $fleet->id]);

        $this->fleets('DELETE', '/fleets/'.$fleet->id, '', $this->apiToken($this->atlasManager()))->assertNoContent();

        $this->assertSame(0, Fleet::query()->whereKey($fleet->id)->count());
        $this->assertSame(null, $vehicle->refresh()->fleet_id);
        $this->assertSame(null, $driver->refresh()->fleet_id);
        $this->assertSame(null, $card->refresh()->fleet_id);

        // Another company's fleet is a 404, and nothing is deleted.
        $cedarFleet = Fleet::factory()->create(['company_id' => $this->cedar()->id]);
        $this->fleets('DELETE', '/fleets/'.$cedarFleet->id, '', $this->apiToken($this->atlasManager()))->assertNotFound();
        $this->assertSame(1, Fleet::query()->whereKey($cedarFleet->id)->count());
    }

    public function test_the_usage_endpoint_aggregates_the_fleets_cards(): void
    {
        $within = Fleet::factory()->quota('100.000')->create(['company_id' => $this->atlas()->id]);
        $this->addUsage($within, '20.50', '18.00');
        $this->addUsage($within, '30.00', '27.00');

        $this->fleets('GET', '/fleets/'.$within->id.'/usage', '', $this->apiToken($this->atlasManager()))
            ->assertOk()
            ->assertJsonPath('data', [
                'month' => self::MONTH,
                'used_liters' => '50.50',
                'used_usd' => '45.00',
                'quota_liters' => '100.000',
                'quota_usd' => null,
                'liters_percent' => '50.5',
                'amount_percent' => null,
                'exceeded' => false,
            ]);

        // A fleet without consumption still answers, with zero usage.
        $empty = Fleet::factory()->create(['company_id' => $this->atlas()->id]);
        $this->fleets('GET', '/fleets/'.$empty->id.'/usage', '', $this->apiToken($this->atlasManager()))
            ->assertOk()
            ->assertJsonPath('data.used_liters', '0.00')
            ->assertJsonPath('data.liters_percent', null)
            ->assertJsonPath('data.exceeded', false);
    }

    public function test_member_lists_are_paginated_and_scoped(): void
    {
        $atlas = $this->atlas();
        $fleet = Fleet::factory()->create(['company_id' => $atlas->id]);
        $vehicles = Vehicle::factory()->count(3)->sequence(
            ['plate_no' => 'ATL-901', 'fleet_id' => $fleet->id],
            ['plate_no' => 'ATL-902', 'fleet_id' => $fleet->id],
            ['plate_no' => 'ATL-903', 'fleet_id' => $fleet->id],
        )->create(['company_id' => $atlas->id]);
        Driver::factory()->count(2)->create(['company_id' => $atlas->id, 'fleet_id' => $fleet->id]);
        FuelCard::factory()->count(2)->create(['company_id' => $atlas->id, 'fleet_id' => $fleet->id]);
        $token = $this->apiToken($this->atlasManager());

        $this->fleets('GET', '/fleets/'.$fleet->id.'/vehicles', '', $token)
            ->assertOk()
            ->assertJsonPath('data.*.plate_no', ['ATL-901', 'ATL-902', 'ATL-903'])
            ->assertJsonPath('meta.total', 3);
        $this->fleets('GET', '/fleets/'.$fleet->id.'/drivers', '?per_page=1', $token)
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
        $this->fleets('GET', '/fleets/'.$fleet->id.'/cards', '', $token)
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.company_id', $atlas->id);

        // Unknown query parameters are refused, not ignored.
        $this->fleets('GET', '/fleets/'.$fleet->id.'/vehicles', '?company_id='.$atlas->id, $token)->assertUnprocessable();

        // Another company's fleet is a 404; an operator has no access.
        $cedarFleet = Fleet::factory()->create(['company_id' => $this->cedar()->id]);
        $this->fleets('GET', '/fleets/'.$cedarFleet->id.'/vehicles', '', $token)->assertNotFound();
        $this->fleets('GET', '/fleets/'.$fleet->id.'/vehicles', '', $this->apiToken($this->operator()))->assertForbidden();
    }

    /**
     * A card's monthly counter row for the current Beirut month, as the
     * ledger keeps it reconciled; FleetQuota aggregates these.
     */
    private function addUsage(Fleet $fleet, string $liters, string $usd): void
    {
        $card = FuelCard::factory()->create(['company_id' => $fleet->company_id, 'fleet_id' => $fleet->id]);

        $usage = new CardMonthlyUsage;
        $usage->forceFill([
            'fuel_card_id' => $card->id,
            'month_start' => self::MONTH,
            'used_l' => $liters,
            'used_usd' => $usd,
        ])->save();
    }

    /**
     * A request to /api/v1{$path}{$query}, checked against openapi.json.
     * The concrete fleet ID becomes the documented {id} template.
     *
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function fleets(string $method, string $path, string $query, ?string $token, array $body = []): TestResponse
    {
        $template = (string) preg_replace('#^/fleets/\d+#', '/fleets/{id}', $path);

        return $this->assertMatchesOpenApi($this->api($method, '/api/v1'.$path.$query, $token, $body), $method, $template);
    }
}
