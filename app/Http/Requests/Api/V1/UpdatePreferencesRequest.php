<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\PreferenceRequest as WebPreferenceRequest;

/**
 * PUT /api/v1/preferences: a partial map of preference keys. Only the keys
 * sent are changed; unknown fields are refused.
 */
class UpdatePreferencesRequest extends WebPreferenceRequest
{
    use RejectsUnknownFields;

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_fleet_id.exists' => 'Choose a fleet of your company.',
            'date_format.in' => 'Choose one of the allowed date formats.',
            'items_per_page.in' => 'Choose one of the allowed page sizes.',
            'language.in' => 'Only Spanish is available.',
        ];
    }
}
