<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MapStationsRequest;
use App\Models\Product;
use App\Models\Station;
use App\Models\User;
use App\Services\StationMapService;
use App\Services\UserPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The interactive station map (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part A). The page renders the Leaflet shell; the markers come from a
 * session JSON endpoint (`/map/stations`) that shares StationMapService with
 * the token API.
 */
class MapController extends Controller
{
    public function index(Request $request, UserPreferenceService $preferences): View
    {
        $user = $request->user();
        assert($user instanceof User);

        $withoutLocation = Station::query()
            ->visibleTo($user)
            ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
            ->orderBy('name')
            ->get();

        return view('map.index', [
            'mapCenter' => $preferences->mapCenter((int) $user->id),
            'products' => Product::query()->where('is_active', true)->orderBy('code')->pluck('code', 'code'),
            'withoutLocation' => $withoutLocation,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function data(MapStationsRequest $request, StationMapService $stations): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return response()->json([
            'stations' => $stations->forUser($user, $request->status(), $request->product(), $request->term()),
        ]);
    }
}
