<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreferenceRequest;
use App\Models\Fleet;
use App\Models\User;
use App\Services\UserPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The preferences page (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part C) for every signed-in user: general display, dashboard widgets and
 * the map's starting point. Saved values apply on the next page load.
 */
class PreferenceController extends Controller
{
    private const WIDGET_LABELS = [
        'summary' => 'Resumen del mes',
        'quota_alerts' => 'Alertas de cuota',
        'open_deliveries' => 'Entregas abiertas',
        'fleet_stats' => 'Datos de flota y tasa',
        'fleet_consumption' => 'Consumo por flota',
        'recent_transactions' => 'Compras recientes',
    ];

    public function edit(Request $request, UserPreferenceService $preferences): View
    {
        $user = $this->user($request);

        return view('preferences.edit', [
            'values' => $preferences->toArray((int) $user->id),
            'fleets' => Fleet::query()->visibleTo($user)->orderBy('name')->pluck('name', 'id'),
            'widgets' => array_map(
                fn (string $key): array => ['key' => $key, 'label' => self::WIDGET_LABELS[$key]],
                UserPreferenceService::DASHBOARD_WIDGETS,
            ),
            'dateFormats' => UserPreferenceService::DATE_FORMATS,
            'itemsPerPageOptions' => UserPreferenceService::ITEMS_PER_PAGE,
            'languages' => ['es' => 'Español'],
            'themes' => UserPreferenceService::THEMES,
        ]);
    }

    public function update(PreferenceRequest $request, UserPreferenceService $preferences): RedirectResponse
    {
        $user = $this->user($request);

        foreach ($request->preferences() as $key => $value) {
            $preferences->set((int) $user->id, $key, $value);
        }

        return redirect()->route('preferences.edit')->with('status', 'Preferencias guardadas.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
