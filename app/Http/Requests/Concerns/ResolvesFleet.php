<?php

namespace App\Http\Requests\Concerns;

use App\Models\Fleet;
use Illuminate\Validation\Rule;

/**
 * The optional `fleet_id` of a vehicle, driver or card: an active fleet of
 * the owning company, or empty ("Sin flota"). The owning company is known by
 * the request (chosen by an admin, or the manager's own), so a fleet of
 * another company fails validation exactly like an unknown ID.
 */
trait ResolvesFleet
{
    /**
     * @return list<mixed>
     */
    protected function fleetRules(?int $companyId): array
    {
        return [
            'nullable', 'integer',
            Rule::exists('fleets', 'id')->where('company_id', $companyId)->where('status', 'active'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fleetMessages(): array
    {
        return [
            'fleet_id.exists' => 'Elige una flota activa de esta empresa.',
        ];
    }

    /** The chosen fleet, or null for "Sin flota"; null also when the ID is unknown. */
    public function fleet(): ?Fleet
    {
        $id = $this->validated('fleet_id');

        return $id === null ? null : Fleet::query()->findOrFail((int) $id);
    }
}
