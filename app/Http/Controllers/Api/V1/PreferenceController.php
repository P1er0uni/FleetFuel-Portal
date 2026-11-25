<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePreferenceRequest;
use App\Http\Requests\Api\V1\UpdatePreferencesRequest;
use App\Models\User;
use App\Services\UserPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The caller's preferences (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part C): read all, update a partial map, and read/write one key.
 */
class PreferenceController extends Controller
{
    public function index(Request $request, UserPreferenceService $preferences): JsonResponse
    {
        return response()->json(['data' => $preferences->toArray((int) $this->user($request)->id)]);
    }

    public function update(UpdatePreferencesRequest $request, UserPreferenceService $preferences): JsonResponse
    {
        $userId = (int) $this->user($request)->id;

        foreach ($request->preferences() as $key => $value) {
            $preferences->set($userId, $key, $value);
        }

        return response()->json(['data' => $preferences->toArray($userId)]);
    }

    public function showKey(Request $request, string $key, UserPreferenceService $preferences): JsonResponse
    {
        abort_unless(in_array($key, UserPreferenceService::keys(), true), 404);

        $userId = (int) $this->user($request)->id;

        return response()->json(['data' => ['key' => $key, 'value' => $preferences->typed($userId, $key)]]);
    }

    public function updateKey(UpdatePreferenceRequest $request, string $key, UserPreferenceService $preferences): JsonResponse
    {
        abort_unless(in_array($key, UserPreferenceService::keys(), true), 404);

        $userId = (int) $this->user($request)->id;
        $preferences->set($userId, $key, $request->storedValue());

        return response()->json(['data' => ['key' => $key, 'value' => $preferences->typed($userId, $key)]]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
