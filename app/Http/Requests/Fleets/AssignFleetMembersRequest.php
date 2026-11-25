<?php

namespace App\Http\Requests\Fleets;

use App\Models\Fleet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assign or unassign vehicles, drivers and cards. Every ID must belong to
 * the fleet's own company: an ID from another company fails validation,
 * exactly like an ID that does not exist. (The service re-checks under the
 * fleet lock.)
 */
class AssignFleetMembersRequest extends FormRequest
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
        $companyId = $this->fleetCompanyId();

        return [
            'vehicles' => ['nullable', 'array', 'max:500'],
            'vehicles.*' => ['integer', Rule::exists('vehicles', 'id')->where('company_id', $companyId)],
            'drivers' => ['nullable', 'array', 'max:500'],
            'drivers.*' => ['integer', Rule::exists('drivers', 'id')->where('company_id', $companyId)],
            'cards' => ['nullable', 'array', 'max:500'],
            'cards.*' => ['integer', Rule::exists('fuel_cards', 'id')->where('company_id', $companyId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicles.*.exists' => 'Todos los vehículos deben pertenecer a la empresa de la flota.',
            'drivers.*.exists' => 'Todos los conductores deben pertenecer a la empresa de la flota.',
            'cards.*.exists' => 'Todas las tarjetas deben pertenecer a la empresa de la flota.',
        ];
    }

    /**
     * @return array{vehicles: list<int>, drivers: list<int>, cards: list<int>}
     */
    public function members(): array
    {
        $validated = $this->validated();

        return [
            'vehicles' => array_values(array_map('intval', (array) ($validated['vehicles'] ?? []))),
            'drivers' => array_values(array_map('intval', (array) ($validated['drivers'] ?? []))),
            'cards' => array_values(array_map('intval', (array) ($validated['cards'] ?? []))),
        ];
    }

    private function fleetCompanyId(): ?int
    {
        $fleet = $this->route('fleet');

        return $fleet instanceof Fleet ? $fleet->company_id : null;
    }
}
