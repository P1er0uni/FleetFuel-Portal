<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Fleets\UpdateFleetRequest as WebUpdateFleetRequest;

/**
 * PUT /api/v1/fleets/{fleet}: the web form's rules (name unique within the
 * company, optional description and quota decimal strings), plus the API
 * conventions: unknown fields are refused — so company_id, which never
 * changes, is a 422 — and the messages stay English (SetApiLocale).
 */
class UpdateFleetRequest extends WebUpdateFleetRequest
{
    use RejectsUnknownFields;

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The fleet name is required.',
            'name.unique' => 'This company already has a fleet with this name.',
        ];
    }
}
