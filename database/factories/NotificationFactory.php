<?php

namespace Database\Factories;

use App\Enums\NotificationEventType;
use App\Models\Company;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'notification_rule_id' => NotificationRule::factory(),
            'user_id' => null,
            'event_type' => NotificationEventType::QuotaWarning,
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(10),
            'payload' => [],
            'entity_hash' => hash('sha256', (string) fake()->unique()->numerify('entity-#####')),
            'read_at' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id, 'company_id' => $user->company_id]);
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }
}
