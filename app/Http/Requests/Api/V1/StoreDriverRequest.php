<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Fleet\StoreDriverRequest as WebStoreDriverRequest;

/**
 * POST /api/v1/drivers: the web form's rules (license unique within the
 * company, optional phone; company_id required for admins and refused for
 * managers), plus the API conventions: unknown fields are refused and
 * company_id must be a JSON integer.
 */
class StoreDriverRequest extends WebStoreDriverRequest
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
            'phone.regex' => 'Use digits, spaces, +, - and brackets only.',
            'license_no.unique' => 'This company already has a driver with this license number.',
        ];
    }
}
