<?php

namespace App\Http\Requests\Fleet;

use App\Http\Requests\Concerns\ResolvesFleet;
use App\Models\User;
use App\Models\Vehicle;
use App\Rules\DecimalString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Company and fuel type are fixed after creation, so sending them is an error. */
class UpdateVehicleRequest extends FormRequest
{
    use ResolvesFleet;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('update', $this->vehicle());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('plate_no'))) {
            $this->merge(['plate_no' => Vehicle::normalizePlate($this->input('plate_no'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['prohibited'],
            'fuel_type' => ['prohibited'],
            'plate_no' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9 -]*$/',
                Rule::unique('vehicles', 'plate_no')->ignore($this->vehicle()->id)],
            'tank_capacity_l' => ['required', new DecimalString(8, allowZero: false)],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'fleet_id' => $this->fleetRules($this->vehicle()->company_id),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->fleetMessages() + [
            'plate_no.regex' => 'Usa solo letras, dígitos, espacios y guiones.',
            'company_id.prohibited' => 'Un vehículo no puede cambiar a otra empresa.',
            'fuel_type.prohibited' => 'El tipo de combustible queda fijo al crear el vehículo.',
        ];
    }

    public function vehicle(): Vehicle
    {
        $vehicle = $this->route('vehicle');
        assert($vehicle instanceof Vehicle);

        return $vehicle;
    }
}
