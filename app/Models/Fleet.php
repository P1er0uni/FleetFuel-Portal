<?php

namespace App\Models;

use App\Enums\FleetStatus;
use App\Enums\UserRole;
use Database\Factories\FleetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'description', 'status', 'monthly_quota_liters', 'monthly_quota_amount'])]
class Fleet extends Model
{
    /** @use HasFactory<FleetFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FleetStatus::class,
            'monthly_quota_liters' => 'decimal:3',
            'monthly_quota_amount' => 'decimal:2',
        ];
    }

    /**
     * Admin: every fleet. Company manager: fleets of their own company only.
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

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** @return HasMany<Driver, $this> */
    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    /** @return HasMany<FuelCard, $this> */
    public function fuelCards(): HasMany
    {
        return $this->hasMany(FuelCard::class);
    }

    /** @return HasMany<FleetRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(FleetRule::class);
    }
}
