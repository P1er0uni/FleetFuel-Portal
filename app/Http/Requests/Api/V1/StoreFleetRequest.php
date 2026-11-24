<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Fleets\StoreFleetRequest as WebStoreFleetRequest;

/**
 * POST /api/v1/fleets: the web form's rules (name unique within the
 * company, optional description and quota decimal strings; company_id
 * required for admins and refused for managers), plus the API
 * conventions: unknown fields are refused, and company_id must be a
 * JSON integer.
 */
class StoreFleetRequest extends WebStoreFleetRequest
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
     * The API keeps the documented English messages (SetApiLocale).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.required' => 'Choose a company.',
            'company_id.exists' => 'Choose an active company.',
            'company_id.prohibited' => 'The company is set from your account and cannot be chosen.',
            'name.required' => 'The fleet name is required.',
            'name.unique' => 'This company already has a fleet with this name.',
        ];
    }
}
