<?php

namespace Database\Factories;

use App\Enums\FleetRuleType;
use App\Models\Fleet;
use App\Models\FleetRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetRule>
 */
class FleetRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fleet_id' => Fleet::factory(),
            'rule_type' => FleetRuleType::MaxLitersPerTransaction,
            'rule_value' => ['value' => '100.00'],
            'active' => true,
        ];
    }

    /**
     * A list rule (allowed_products / allowed_stations) with the given IDs.
     *
     * @param  list<int>  $ids
     */
    public function list(FleetRuleType $type, array $ids): static
    {
        return $this->state(['rule_type' => $type, 'rule_value' => ['ids' => $ids]]);
    }

    /** A numeric rule (max_liters_per_transaction / max_transactions_per_day). */
    public function numeric(FleetRuleType $type, string $value): static
    {
        return $this->state(['rule_type' => $type, 'rule_value' => ['value' => $value]]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
