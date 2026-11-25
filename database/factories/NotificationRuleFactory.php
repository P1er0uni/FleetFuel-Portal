<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\ThresholdOperator;
use App\Models\Company;
use App\Models\Fleet;
use App\Models\NotificationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationRule>
 */
class NotificationRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'fleet_id' => null,
            'event_type' => NotificationEventType::QuotaWarning,
            'channel' => NotificationChannel::InApp,
            'threshold_value' => null,
            'threshold_operator' => null,
            'active' => true,
        ];
    }

    public function forEvent(NotificationEventType $type): static
    {
        return $this->state(['event_type' => $type]);
    }

    public function onChannel(NotificationChannel $channel): static
    {
        return $this->state(['channel' => $channel]);
    }

    public function forFleet(Fleet $fleet): static
    {
        return $this->state(['fleet_id' => $fleet->id, 'company_id' => $fleet->company_id]);
    }

    public function threshold(ThresholdOperator $operator, string $value): static
    {
        return $this->state(['threshold_operator' => $operator, 'threshold_value' => $value]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
