<?php

namespace App\Services;

use App\Models\UserPreference;

/**
 * Per-user preferences (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part C). Values live as strings in `user_preferences`; typed getters decode
 * the ones that matter. One instance caches a user's rows for the request, so
 * repeated reads cost one query (registered as a singleton).
 */
class UserPreferenceService
{
    /** The dashboard sections a user may show and reorder. */
    public const DASHBOARD_WIDGETS = [
        'summary',
        'quota_alerts',
        'open_deliveries',
        'fleet_stats',
        'fleet_consumption',
        'recent_transactions',
    ];

    public const DATE_FORMATS = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'];

    public const ITEMS_PER_PAGE = [10, 15, 25, 50];

    public const LANGUAGES = ['es'];

    /** @var array<string, string|null> */
    public const DEFAULTS = [
        'default_fleet_id' => null,
        'dashboard_widgets' => null,
        'map_default_zoom' => '12',
        'map_default_lat' => '-33.4489',
        'map_default_lng' => '-70.6693',
        'date_format' => 'd/m/Y',
        'language' => 'es',
        'items_per_page' => null,
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /** @var array<int, array<string, string|null>> */
    private array $cache = [];

    /**
     * @return array<string, string|null>
     */
    public function all(int $userId): array
    {
        return $this->cache[$userId] ??= UserPreference::query()
            ->where('user_id', $userId)
            ->pluck('value', 'key')
            ->all();
    }

    public function get(int $userId, string $key, ?string $default = null): ?string
    {
        $stored = $this->all($userId)[$key] ?? null;

        if ($stored !== null) {
            return $stored;
        }

        return $default ?? self::DEFAULTS[$key] ?? null;
    }

    public function set(int $userId, string $key, ?string $value): void
    {
        UserPreference::query()->updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value],
        );

        $this->cache[$userId][$key] = $value;
    }

    public function forget(int $userId): void
    {
        unset($this->cache[$userId]);
    }

    /** The stored page size, or the screen's own fallback when the user has not set one. */
    public function itemsPerPage(int $userId, int $fallback = 25): int
    {
        $value = $this->all($userId)['items_per_page'] ?? null;

        return $value === null || $value === '' ? $fallback : (int) $value;
    }

    public function dateFormat(int $userId): string
    {
        return (string) $this->get($userId, 'date_format', 'd/m/Y');
    }

    /** @return list<string>|null Null means "every widget, in the default order". */
    public function dashboardWidgets(int $userId): ?array
    {
        $raw = $this->all($userId)['dashboard_widgets'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : null;
    }

    public function defaultFleetId(int $userId): ?int
    {
        $raw = $this->all($userId)['default_fleet_id'] ?? null;

        return $raw === null || $raw === '' ? null : (int) $raw;
    }

    /** @return array{lat: float, lng: float, zoom: int} */
    public function mapCenter(int $userId): array
    {
        return [
            'lat' => (float) $this->get($userId, 'map_default_lat', '-33.4489'),
            'lng' => (float) $this->get($userId, 'map_default_lng', '-70.6693'),
            'zoom' => (int) $this->get($userId, 'map_default_zoom', '12'),
        ];
    }

    /** One preference in its natural type, for the API and the preferences page. */
    public function typed(int $userId, string $key): mixed
    {
        return match ($key) {
            'default_fleet_id' => $this->defaultFleetId($userId),
            'dashboard_widgets' => $this->dashboardWidgets($userId) ?? self::DASHBOARD_WIDGETS,
            'map_default_zoom' => (int) $this->get($userId, 'map_default_zoom', '12'),
            'map_default_lat' => (string) $this->get($userId, 'map_default_lat', '-33.4489'),
            'map_default_lng' => (string) $this->get($userId, 'map_default_lng', '-70.6693'),
            'items_per_page' => (int) $this->get($userId, 'items_per_page', '15'),
            'date_format' => $this->dateFormat($userId),
            'language' => (string) $this->get($userId, 'language', 'es'),
            default => $this->get($userId, $key),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $userId): array
    {
        $values = [];

        foreach (self::keys() as $key) {
            $values[$key] = $this->typed($userId, $key);
        }

        return $values;
    }
}
