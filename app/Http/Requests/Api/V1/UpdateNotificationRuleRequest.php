<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Notifications\UpdateNotificationRuleRequest as WebUpdateNotificationRuleRequest;

/**
 * PUT /api/v1/notifications/rules/{id}: the web update rules plus the API
 * conventions. The company is fixed, so company_id is refused.
 */
class UpdateNotificationRuleRequest extends WebUpdateNotificationRuleRequest
{
    use RejectsUnknownFields;

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.prohibited' => 'The company cannot be changed.',
            'event_type.required' => 'Choose an event.',
            'channel.required' => 'Choose a channel.',
            'fleet_id.exists' => 'Choose a fleet of this company.',
        ];
    }
}
