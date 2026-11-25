<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MapStationsRequest;
use App\Services\StationMapService;
use Illuminate\Http\JsonResponse;

/**
 * Stations with coordinates for the interactive map
 * (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md, Part A). The row
 * building lives in StationMapService, shared with the web map page.
 */
class MapController extends Controller
{
    public function stations(MapStationsRequest $request, StationMapService $stations): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        return response()->json([
            'stations' => $stations->forUser($user, $request->status(), $request->product(), $request->term()),
        ]);
    }
}
