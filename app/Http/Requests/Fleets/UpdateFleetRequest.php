<?php

namespace App\Http\Requests\Fleets;

use App\Models\Fleet;
use App\Rules\DecimalString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit a fleet's name, description and quotas. The company never changes.
 */
class UpdateFleetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fleet = $this->route('fleet');

        return $fleet instanceof Fleet && $this->user()->can('update', $fleet);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fleet = $this->route('fleet');
        assert($fleet instanceof Fleet);

        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('fleets', 'name')->where('company_id', $fleet->company_id)->ignore($fleet->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'monthly_quota_liters' => ['nullable', new DecimalString(9, 3)],
            'monthly_quota_amount' => ['nullable', new DecimalString(16)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la flota es obligatorio.',
            'name.unique' => 'Esta empresa ya tiene una flota con ese nombre.',
        ];
    }

    /**
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
