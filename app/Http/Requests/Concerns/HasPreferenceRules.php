<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Services\UserPreferenceService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared rules for the preference writes (web form and API). Only the keys
 * the caller sends are changed; a manager's `default_fleet_id` must be one of
 * their own company's fleets.
 */
trait HasPreferenceRules
{
    /**
     * @return array<string, mixed>
     */
    protected function preferenceRules(): array
    {
        return [
            'default_fleet_id' => ['nullable', 'integer', 'min:1', $this->fleetRule()],
            'dashboard_widgets' => ['nullable', 'array'],
            'dashboard_widgets.*' => ['string', Rule::in(UserPreferenceService::DASHBOARD_WIDGETS)],
            'map_default_zoom' => ['nullable', 'integer', 'between:1,18'],
            'map_default_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'map_default_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'date_format' => ['nullable', Rule::in(UserPreferenceService::DATE_FORMATS)],
            'language' => ['nullable', Rule::in(UserPreferenceService::LANGUAGES)],
            'items_per_page' => ['nullable', 'integer', Rule::in(UserPreferenceService::ITEMS_PER_PAGE)],
            'theme' => ['nullable', Rule::in(array_keys(UserPreferenceService::THEMES))],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function normalizedPreferences(): array
    {
        $validated = $this->validated();
        $values = [];

        foreach (UserPreferenceService::keys() as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $value = $validated[$key];

            $values[$key] = match ($key) {
                'dashboard_widgets' => json_encode(array_values((array) $value)),
                default => $value === null || $value === '' ? null : (string) $value,
            };
        }

        return $values;
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
