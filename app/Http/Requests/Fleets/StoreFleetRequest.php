<?php

namespace App\Http\Requests\Fleets;

use App\Http\Requests\Fleet\ResolvesCompany;
use App\Models\Fleet;
use App\Rules\DecimalString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a fleet. The company is chosen by the server like every other
 * fleet member: an admin must pick an active company, a manager's own
 * company is used. The name is unique within that company.
 */
class StoreFleetRequest extends FormRequest
{
    use ResolvesCompany;

    public function authorize(): bool
    {
        return $this->actor()->can('create', Fleet::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => $this->companyRules(),
            'name' => ['required', 'string', 'max:100',
                Rule::unique('fleets', 'name')->where('company_id', $this->targetCompanyId())],
            'description' => ['nullable', 'string', 'max:255'],
            // DECIMAL(12,3) and DECIMAL(18,2); empty means unlimited.
            'monthly_quota_liters' => ['nullable', new DecimalString(9, 3)],
            'monthly_quota_amount' => ['nullable', new DecimalString(16)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->companyMessages() + [
            'name.required' => 'El nombre de la flota es obligatorio.',
            'name.unique' => 'Esta empresa ya tiene una flota con ese nombre.',
        ];
    }

    /**
     * The validated attributes for FleetService::create().
     *
     * @return array{name: string, description: string|null, monthly_quota_liters: string|null, monthly_quota_amount: string|null}
     */
    public function fleetAttributes(): array
    {
        /** @var array{name: string, description?: string|null, monthly_quota_liters?: string|null, monthly_quota_amount?: string|null} $validated */
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'monthly_quota_liters' => $validated['monthly_quota_liters'] ?? null,
            'monthly_quota_amount' => $validated['monthly_quota_amount'] ?? null,
        ];
    }
}
