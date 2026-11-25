<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\User;
use App\Services\UserPreferenceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * PUT /api/v1/preferences/{key}: a single key and its value, validated by the
 * key's type. Unknown fields are refused; the key is constrained by the route.
 */
class UpdatePreferenceRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->key()) {
            'default_fleet_id' => ['value' => ['nullable', 'integer', 'min:1', $this->fleetRule()]],
            'dashboard_widgets' => [
                'value' => ['nullable', 'array'],
                'value.*' => ['string', Rule::in(UserPreferenceService::DASHBOARD_WIDGETS)],
            ],
            'map_default_zoom' => ['value' => ['nullable', 'integer', 'between:1,18']],
            'map_default_lat' => ['value' => ['nullable', 'numeric', 'between:-90,90']],
            'map_default_lng' => ['value' => ['nullable', 'numeric', 'between:-180,180']],
            'date_format' => ['value' => ['nullable', Rule::in(UserPreferenceService::DATE_FORMATS)]],
            'language' => ['value' => ['nullable', Rule::in(UserPreferenceService::LANGUAGES)]],
            'items_per_page' => ['value' => ['nullable', 'integer', Rule::in(UserPreferenceService::ITEMS_PER_PAGE)]],
            'theme' => ['value' => ['nullable', Rule::in(array_keys(UserPreferenceService::THEMES))]],
            // Unknown keys are a 404 in the controller (after auth), not a 422.
            default => ['value' => ['nullable']],
        };
    }

    public function key(): string
    {
        return (string) $this->route('key');
    }

    /** The value ready to store as a string (dashboard_widgets is JSON-encoded). */
    public function storedValue(): ?string
    {
        $value = $this->input('value');

        return match ($this->key()) {
            'dashboard_widgets' => $value === null ? null : json_encode(array_values((array) $value)),
            default => $value === null || $value === '' ? null : (string) $value,
        };
    }

    private function fleetRule(): Exists
    {
        $user = $this->user();
        $rule = Rule::exists('fleets', 'id');

        return $user instanceof User && $user->isCompanyManager()
            ? $rule->where('company_id', $user->company_id)
            : $rule;
    }
}
