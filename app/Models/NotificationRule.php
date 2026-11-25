<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\ThresholdOperator;
use App\Enums\UserRole;
use Database\Factories\NotificationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A notification rule: for one event of one company (optionally one fleet),
 * deliver through one channel, with an optional threshold for quota_warning
 * (docs/sdd/fase-2-importacion-notificaciones/spec.md, Part B).
 */
#[Fillable(['company_id', 'fleet_id', 'event_type', 'channel', 'threshold_value', 'threshold_operator', 'active'])]
class NotificationRule extends Model
{
    /** @use HasFactory<NotificationRuleFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => NotificationEventType::class,
            'channel' => NotificationChannel::class,
            'threshold_operator' => ThresholdOperator::class,
            'threshold_value' => 'decimal:3',
            'active' => 'boolean',
        ];
    }

    /**
     * Admin: every company. Company manager: own company only.
     * Station operator: none.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        match ($user->role) {
            UserRole::Admin => null,
            UserRole::CompanyManager => $query->where('company_id', $user->company_id),
            UserRole::StationOperator => $query->whereRaw('0 = 1'),
        };
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Fleet, $this> */
    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
