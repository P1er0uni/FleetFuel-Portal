<?php

namespace App\Http\Requests\Fleet;

use App\Http\Requests\Concerns\ResolvesFleet;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    use ResolvesFleet;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('update', $this->driver());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('license_no'))) {
            $this->merge(['license_no' => Driver::normalizeLicense($this->input('license_no'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $driver = $this->driver();

        return [
            'company_id' => ['prohibited'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() -]+$/'],
            'license_no' => ['required', 'string', 'max:50',
                Rule::unique('drivers', 'license_no')->where('company_id', $driver->company_id)->ignore($driver->id)],
            'fleet_id' => $this->fleetRules($driver->company_id),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->fleetMessages() + [
            'company_id.prohibited' => 'Un conductor no puede cambiar a otra empresa.',
            'phone.regex' => 'Usa solo dígitos, espacios, +, - y paréntesis.',
            'license_no.unique' => 'Esta empresa ya tiene un conductor con este número de licencia.',
        ];
    }

    public function driver(): Driver
    {
        $driver = $this->route('driver');
        assert($driver instanceof Driver);

        return $driver;
    }
}
