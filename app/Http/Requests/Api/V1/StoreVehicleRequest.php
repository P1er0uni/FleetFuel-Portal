<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Fleet\StoreVehicleRequest as WebStoreVehicleRequest;

/**
 * POST /api/v1/vehicles: the web form's rules (normalized unique plate,
 * fuel type, positive tank capacity; company_id required for admins and
 * refused for managers), plus the API conventions: unknown fields are
 * refused, and IDs and the odometer must be JSON integers.
 */
class StoreVehicleRequest extends WebStoreVehicleRequest
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
            'odometer_km' => ['nullable', 'integer:strict', 'min:0', 'max:9999999'],
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
            'plate_no.regex' => 'Use letters, digits, spaces and hyphens only.',
        ];
    }
}
