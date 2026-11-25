<?php

namespace App\Http\Requests\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\ThresholdOperator;
use App\Models\NotificationRule;
use App\Rules\DecimalString;
use App\Services\NotificationRuleService;
use Illuminate\Validation\Rule;

/**
 * Edit a notification rule: the company is fixed, so company_id is refused;
 * the fleet must belong to the rule's company. The active toggle has its own
 * route, so it is not writable here.
 */
class UpdateNotificationRuleRequest extends StoreNotificationRuleRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('notificationRule');

        return $rule instanceof NotificationRule && $this->user()?->can('update', $rule) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rule = $this->route('notificationRule');
        $companyId = $rule instanceof NotificationRule ? $rule->company_id : null;

        return [
            'company_id' => ['prohibited'],
            'fleet_id' => [
                'nullable', 'integer',
                Rule::exists('fleets', 'id')->where('company_id', $companyId),
            ],
            'event_type' => ['required', Rule::enum(NotificationEventType::class)],
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'threshold_value' => ['nullable', new DecimalString(9, 3)],
            'threshold_operator' => ['nullable', Rule::enum(ThresholdOperator::class)],
        ];
    }

    /**
     * @return array{fleet_id: int|null, event_type: string, channel: string, threshold_value: string|null, threshold_operator: string|null, active: bool}
     */
    public function ruleAttributes(): array
    {
        return NotificationRuleService::attributes($this->validated(), withActive: false);
    }
}
