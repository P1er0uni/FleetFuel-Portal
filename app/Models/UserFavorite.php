<?php

namespace App\Models;

use Database\Factories\UserFavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A manager's or admin's shortcut to a fleet, vehicle or station it can see
 * (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md, Part B). The
 * `favoritable_type` holds a morph-map alias, never a class name (D7).
 */
#[Fillable(['user_id', 'favoritable_type', 'favoritable_id', 'label', 'position'])]
class UserFavorite extends Model
{
    /** @use HasFactory<UserFavoriteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }
}
