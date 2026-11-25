<?php

namespace App\Services;

use App\Models\Fleet;
use App\Models\Station;
use App\Models\User;
use App\Models\UserFavorite;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Per-user favourites (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part B): quick links to a fleet, vehicle or station the user can see.
 *
 * `report` is deferred (D7): there is no saved-reports table to point at.
 * Every operation checks the entity exists inside the caller's scope, so a
 * foreign record is a 404 and never becomes a favourite.
 */
class FavoriteService
{
    public const TYPES = ['fleet', 'vehicle', 'station'];

    /**
     * @return list<array{id: int, type: string, favoritable_id: int, label: string, url: string, position: int}>
     */
    public function list(User $user): array
    {
        return UserFavorite::query()
            ->forUser($user)
            ->with('favoritable')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (UserFavorite $favorite): array => $this->present($favorite))
            ->all();
    }

    public function add(User $user, string $type, int $id, ?string $label = null): UserFavorite
    {
        $this->findVisibleEntity($user, $type, $id);

        $exists = UserFavorite::query()
            ->forUser($user)
            ->where('favoritable_type', $type)
            ->where('favoritable_id', $id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['favoritable_id' => 'Esta entidad ya está en tus favoritos.']);
        }

        return DB::transaction(function () use ($user, $type, $id, $label): UserFavorite {
            $next = (int) UserFavorite::query()->forUser($user)->max('position') + 1;

            return UserFavorite::query()->create([
                'user_id' => $user->id,
                'favoritable_type' => $type,
                'favoritable_id' => $id,
                'label' => $label,
                'position' => $next,
            ]);
        });
    }

    /** Add if absent, remove if present. Returns whether it is now a favourite. */
    public function toggle(User $user, string $type, int $id): bool
    {
        $existing = UserFavorite::query()
            ->forUser($user)
            ->where('favoritable_type', $type)
            ->where('favoritable_id', $id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        $this->add($user, $type, $id);

        return true;
    }

    public function remove(User $user, int $favoriteId): void
    {
        $favorite = UserFavorite::query()->forUser($user)->findOrFail($favoriteId);
        $favorite->delete();
    }

    public function isFavorited(User $user, string $type, int $id): bool
    {
        return UserFavorite::query()
            ->forUser($user)
            ->where('favoritable_type', $type)
            ->where('favoritable_id', $id)
            ->exists();
    }

    /**
     * @param  list<int>  $ids
     */
    public function reorder(User $user, array $ids): void
    {
        DB::transaction(function () use ($user, $ids): void {
            $own = UserFavorite::query()->forUser($user)->pluck('id')->all();

            foreach ($ids as $position => $id) {
                if (in_array($id, $own, true)) {
                    UserFavorite::query()->whereKey($id)->update(['position' => $position]);
                }
            }
        });
    }

    /**
     * @return array{id: int, type: string, favoritable_id: int, label: string, url: string, position: int}
     */
    public function present(UserFavorite $favorite): array
    {
        $entity = $favorite->favoritable;

        return [
            'id' => (int) $favorite->id,
            'type' => (string) $favorite->favoritable_type,
            'favoritable_id' => (int) $favorite->favoritable_id,
            'label' => $favorite->label ?? $this->defaultLabel($favorite->favoritable_type, $entity),
            'url' => $this->url($favorite->favoritable_type, (int) $favorite->favoritable_id),
            'position' => (int) $favorite->position,
        ];
    }

    private function findVisibleEntity(User $user, string $type, int $id): Model
    {
        return match ($type) {
            'fleet' => Fleet::query()->visibleTo($user)->findOrFail($id),
            'vehicle' => Vehicle::query()->visibleTo($user)->findOrFail($id),
            'station' => Station::query()->visibleTo($user)->findOrFail($id),
            default => throw ValidationException::withMessages(['favoritable_type' => 'Tipo de favorito desconocido.']),
        };
    }

    private function defaultLabel(string $type, ?Model $entity): string
    {
        return match ($type) {
            'fleet' => 'Flota: '.($entity instanceof Fleet ? $entity->name : '#'.$entity?->getKey()),
            'vehicle' => 'Vehículo: '.($entity instanceof Vehicle ? $entity->plate_no : '#'.$entity?->getKey()),
            'station' => 'Estación: '.($entity instanceof Station ? $entity->name : '#'.$entity?->getKey()),
            default => 'Favorito',
        };
    }

    private function url(string $type, int $id): string
    {
        return match ($type) {
            'fleet' => route('fleets.show', $id),
            'vehicle' => route('vehicles.edit', $id),
            // No station detail route and the map is manager-only, so the
            // station list is the one target every role can open (D7).
            default => route('stations.index'),
        };
    }
}
