<?php

namespace Database\Factories;

use App\Enums\FleetStatus;
use App\Models\Company;
use App\Models\Fleet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fleet>
 */
class FleetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'status' => FleetStatus::Active,
            'monthly_quota_liters' => null,
            'monthly_quota_amount' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => FleetStatus::Inactive]);
    }

    public function quota(string $liters, ?string $amount = null): static
    {
        return $this->state(['monthly_quota_liters' => $liters, 'monthly_quota_amount' => $amount]);
    }
}
