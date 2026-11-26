<?php

namespace App\Http\Requests\Reference;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Configure which stations a company may use (Fase 6, área 3). Admin only.
 */
class UpdateCompanyStationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company && $this->user()?->can('update', $company) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'restrict_stations' => ['nullable', 'boolean'],
            'station_ids' => ['nullable', 'array', 'max:500'],
            'station_ids.*' => ['integer', Rule::exists('stations', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'station_ids.*.exists' => 'Todas las estaciones deben existir y estar activas.',
        ];
    }

    /**
     * @return list<int>
     */
    public function stationIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('station_ids', [])));
    }
}
