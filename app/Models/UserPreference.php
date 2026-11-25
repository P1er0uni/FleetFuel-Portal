<?php

namespace App\Models;

use Database\Factories\UserPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One key/value preference of a user (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part C). Values stay strings; complex values such as `dashboard_widgets`
 * are JSON-encoded in the service.
 */
#[Fillable(['user_id', 'key', 'value'])]
class UserPreference extends Model
{
    /** @use HasFactory<UserPreferenceFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
