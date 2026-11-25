<?php

namespace App\Http\Requests\Fleets;

use App\Enums\FleetStatus;
use App\Models\Fleet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeFleetStatusRequest extends FormRequest
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
        return [
            'status' => ['required', Rule::enum(FleetStatus::class)],
        ];
    }

    public function status(): FleetStatus
    {
        return FleetStatus::from((string) $this->validated('status'));
    }
}
