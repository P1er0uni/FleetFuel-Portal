<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Session-based favourites for the browser (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part B): the star toggle and the dashboard reorder. The token API lives in
 * Api\V1\FavoriteController; both share FavoriteService.
 */
class FavoriteController extends Controller
{
    public function toggle(Request $request, FavoriteService $favorites): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(FavoriteService::TYPES)],
            'favoritable_id' => ['required', 'integer', 'min:1'],
        ]);

        $favorited = $favorites->toggle($this->user($request), (string) $data['type'], (int) $data['favoritable_id']);

        return response()->json(['favorited' => $favorited]);
    }

    public function store(Request $request, FavoriteService $favorites): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(FavoriteService::TYPES)],
            'favoritable_id' => ['required', 'integer', 'min:1'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $favorite = $favorites->add(
            $this->user($request),
            (string) $data['type'],
            (int) $data['favoritable_id'],
            $data['label'] ?? null,
        );

        return response()->json(['data' => $favorites->present($favorite)], 201);
    }

    public function destroy(Request $request, int $favorite, FavoriteService $favorites): JsonResponse
    {
        $favorites->remove($this->user($request), $favorite);

        return response()->json(['removed' => true]);
    }

    public function reorder(Request $request, FavoriteService $favorites): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $favorites->reorder($this->user($request), array_map('intval', $data['ids']));

        return response()->json(['reordered' => count($data['ids'])]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
