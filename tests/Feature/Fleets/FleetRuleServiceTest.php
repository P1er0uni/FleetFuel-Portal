<?php

namespace Tests\Feature\Fleets;

use App\Enums\FleetRuleType;
use App\Models\ExchangeRate;
use App\Models\Fleet;
use App\Models\FleetRule;
use App\Models\FuelCard;
use App\Models\Product;
use App\Services\FleetRuleService;
use App\Services\FleetService;
use App\Services\FuelTransactionService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The fleet rules (docs/sdd/fase-1-flotas/spec.md, rules 4-7): every rule
 * type lets a valid purchase through and refuses a violating one with a
 * 422 ValidationException, through the same ledger service the POS uses.
 */
class FleetRuleServiceTest extends TestCase
{
    use BuildsLedgerFixtures;
    use RefreshDatabase;

    private FleetService $fleets;

    private FleetRuleService $rules;

    private FuelTransactionService $ledger;

    /** @var array{company: mixed, otherCompany: mixed, station: mixed, otherStation: mixed, operator: mixed, otherOperator: mixed, admin: mixed, manager: mixed, diesel: mixed, petrol: mixed, vehicle: mixed, driver: mixed, card: mixed} */
    private array $world;

    private Fleet $fleet;

    private FuelCard $fleetCard;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FIXTURE_NOW));
        $this->fleets = app(FleetService::class);
        $this->rules = app(FleetRuleService::class);
        $this->ledger = app(FuelTransactionService::class);
        $this->world = $this->ledgerWorld();

        $this->fleet = $this->fleets->create($this->world['company'], ['name' => 'Camiones'], $this->world['admin']);
        $this->fleetCard = FuelCard::factory()->create(['company_id' => $this->world['company']->id]);
        $this->fleets->assign($this->fleet, ['cards' => [$this->fleetCard->id]], $this->world['admin']);
    }

    public function test_a_card_without_a_fleet_ignores_all_rules(): void
    {
        $freeCard = FuelCard::factory()->create(['company_id' => $this->world['company']->id]);

        $this->purchase($freeCard, '40.00');
        $this->assertTrue($freeCard->transactions()->exists());
    }

    public function test_allowed_products_lets_a_listed_product_through_and_refuses_the_rest(): void
    {
        $this->rules->save($this->fleet, FleetRuleType::AllowedProducts,
            ['ids' => [$this->world['diesel']->id]], true, $this->world['admin']);

        $this->purchase($this->fleetCard, '20.00', $this->world['diesel']);

        $this->assertRefused('Product not allowed for this fleet.', fn () => $this->purchase($this->fleetCard, '20.00', $this->world['petrol']));
    }

    public function test_an_inactive_rule_is_ignored(): void
    {
        $this->rules->save($this->fleet, FleetRuleType::AllowedProducts,
            ['ids' => [$this->world['petrol']->id]], false, $this->world['admin']);

        $this->purchase($this->fleetCard, '20.00', $this->world['diesel']);
        $this->assertSame(1, $this->fleetCard->refresh()->transactions()->count());
    }

    public function test_allowed_stations_lets_a_listed_station_through_and_refuses_the_rest(): void
    {
        $this->rules->save($this->fleet, FleetRuleType::AllowedStations,
            ['ids' => [$this->world['otherStation']->id]], true, $this->world['admin']);

        $this->assertRefused('This station is not allowed for this fleet.', fn () => $this->purchase($this->fleetCard, '20.00'));

        $this->ledger->recordHistorical(
            $this->fleetCard, $this->world['otherStation'], $this->world['otherOperator'], $this->world['diesel'],
            '20.00', CarbonImmutable::now()->subHour(), 'RULE-ST-1', null, CarbonImmutable::now(),
        );
    }

    public function test_max_liters_per_transaction_accepts_within_and_refuses_above(): void
    {
        $this->rules->save($this->fleet, FleetRuleType::MaxLitersPerTransaction,
            ['value' => '50.00'], true, $this->world['admin']);

        $this->purchase($this->fleetCard, '50.00');

        $this->assertRefused("This purchase exceeds the fleet's per-transaction limit of 50.00 L.",
            fn () => $this->purchase($this->fleetCard, '50.01'));
    }

    public function test_max_transactions_per_day_counts_the_cards_beirut_day(): void
    {
        $this->rules->save($this->fleet, FleetRuleType::MaxTransactionsPerDay,
            ['value' => '2'], true, $this->world['admin']);

        $this->purchase($this->fleetCard, '10.00');
        $this->purchase($this->fleetCard, '10.00');

        $this->assertRefused("This card reached the fleet's daily limit of 2 transactions.",
            fn () => $this->purchase($this->fleetCard, '10.00'));

        // Yesterday does not count against today. (A rate covering that day
        // too, since ledgerWorld()'s rate starts today.)
        ExchangeRate::factory()->create(['effective_at' => CarbonImmutable::now()->subDay()->startOfDay()]);
        $this->ledger->recordHistorical(
            $this->fleetCard, $this->world['station'], $this->world['operator'], $this->world['diesel'],
            '10.00', CarbonImmutable::now()->subDay(), 'RULE-DAY-Y', null, CarbonImmutable::now(),
        );
    }

    public function test_saving_a_rule_replaces_the_fleets_rule_of_that_type(): void
    {
        $this->rules->save($this->fleet, FleetRuleType::MaxLitersPerTransaction, ['value' => '10.00'], true, $this->world['admin']);
        $this->rules->save($this->fleet, FleetRuleType::MaxLitersPerTransaction, ['value' => '20.00'], true, $this->world['admin']);

        $this->assertSame(1, FleetRule::query()->where('fleet_id', $this->fleet->id)->count());
        $this->assertSame('20.00', (string) FleetRule::query()->where('fleet_id', $this->fleet->id)->sole()->rule_value['value']);

        $this->rules->delete($this->fleet, FleetRule::query()->where('fleet_id', $this->fleet->id)->sole(), $this->world['admin']);
        $this->assertSame(0, FleetRule::query()->where('fleet_id', $this->fleet->id)->count());
    }

    /**
     * A purchase at the default station with the default product.
     */
    private function purchase(FuelCard $card, string $liters, ?Product $product = null): void
    {
        $this->ledger->recordHistorical(
            $card, $this->world['station'], $this->world['operator'], $product ?? $this->world['diesel'],
            $liters, CarbonImmutable::now()->subMinutes(30 - $this->sequence), 'RULE-'.$this->sequence++, null, CarbonImmutable::now(),
        );
    }

    private function assertRefused(string $message, Closure $attempt): void
    {
        try {
            $attempt();
            $this->fail('The purchase was accepted: '.$message);
        } catch (ValidationException $e) {
            $this->assertContains($message, $e->errors()['liters'] ?? $e->errors()['product_code'] ?? $e->errors()['station'] ?? []);
        }
    }
}
