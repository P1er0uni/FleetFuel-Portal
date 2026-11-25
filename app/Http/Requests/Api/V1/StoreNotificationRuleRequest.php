<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Notifications\StoreNotificationRuleRequest as WebStoreNotificationRuleRequest;

/**
 * POST /api/v1/notifications/rules: the web rules plus the API conventions
 * (unknown fields refused, company_id a JSON integer for admins).
 */
class StoreNotificationRuleRequest extends WebStoreNotificationRuleRequest
{
    use RejectsUnknownFields;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'company_id' => $this->companyRules(jsonInteger: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.required' => 'Choose a company.',
            'company_id.exists' => 'Choose an active company.',
            'company_id.prohibited' => 'The company is set from your account and cannot be chosen.',
            'event_type.required' => 'Choose an event.',
            'channel.required' => 'Choose a channel.',
            'fleet_id.exists' => 'Choose a fleet of this company.',
        ];
    }
}
