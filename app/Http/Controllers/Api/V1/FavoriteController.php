<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReorderFavoritesRequest;
use App\Http\Requests\Api\V1\StoreFavoriteRequest;
use App\Models\User;
use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The caller's favourites (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part B): list, add, remove and reorder. Every row is the caller's own; an
 * entity outside their scope cannot be added (404).
 */
class FavoriteController extends Controller
{
    public function index(Request $request, FavoriteService $favorites): JsonResponse
    {
        return response()->json(['data' => $favorites->list($this->user($request))]);
    }

    public function store(StoreFavoriteRequest $request, FavoriteService $favorites): JsonResponse
    {
        $favorite = $favorites->add($this->user($request), $request->type(), $request->favoritableId(), $request->label());

        return response()->json(['data' => $favorites->present($favorite)], 201);
    }

    public function destroy(Request $request, int $favorite, FavoriteService $favorites): Response
    {
        $favorites->remove($this->user($request), $favorite);

        return response()->noContent();
    }

    public function reorder(ReorderFavoritesRequest $request, FavoriteService $favorites): JsonResponse
    {
        $favorites->reorder($this->user($request), $request->ids());

        return response()->json(['data' => ['reordered' => count($request->ids())]]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
