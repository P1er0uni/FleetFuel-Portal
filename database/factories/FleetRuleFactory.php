<?php

namespace Database\Factories;

use App\Enums\FleetRuleType;
use App\Enums\RuleEnforcement;
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
            'enforcement' => RuleEnforcement::Block,
            'valid_from' => null,
            'valid_to' => null,
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

    /** A numeric rule (max liters/amount or transactions). */
    public function numeric(FleetRuleType $type, string $value): static
    {
        return $this->state(['rule_type' => $type, 'rule_value' => ['value' => $value]]);
    }

    /**
     * A days/hours rule.
     *
     * @param  array<int, array{days: list<int>, start: string, end: string}>  $windows
     */
    public function windows(array $windows): static
    {
        return $this->state(['rule_type' => FleetRuleType::AllowedTimeWindows, 'rule_value' => ['windows' => $windows]]);
    }

    public function warning(): static
    {
        return $this->state(['enforcement' => RuleEnforcement::Warn]);
    }

    public function validBetween(string $from, string $to): static
    {
        return $this->state(['valid_from' => $from, 'valid_to' => $to]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
