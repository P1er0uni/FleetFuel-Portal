<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\FleetStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Fleet;
use App\Models\FuelCard;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to a fleet (docs/sdd/fase-1-flotas/spec.md): creation, edits,
 * status, deletion (members are ungrouped by the SET NULL foreign key) and
 * member assignment. Each change writes one audit row in the same database
 * transaction. The company is chosen by the server on creation and never
 * changes; an inactive company's fleets are read-only.
 */
class FleetService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{name: string, description?: string|null, monthly_quota_liters?: string|null, monthly_quota_amount?: string|null}  $data
     */
    public function create(Company $company, array $data, User $actor): Fleet
    {
        $this->ensureCompanyActive($company);

        return DB::transaction(function () use ($company, $data, $actor): Fleet {
            $fleet = new Fleet;
            $fleet->forceFill([
                'company_id' => $company->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'monthly_quota_liters' => Decimal::normalize($data['monthly_quota_liters'] ?? null, 3),
                'monthly_quota_amount' => Decimal::normalize($data['monthly_quota_amount'] ?? null),
                'status' => FleetStatus::Active,
            ])->save();

            $this->audit->record('fleet.created', $fleet, $actor, $company->id, null, $this->quotaValues($fleet));

            return $fleet;
        });
    }

    /**
     * Name, description and quotas. The company never changes.
     *
     * @param  array{name: string, description?: string|null, monthly_quota_liters?: string|null, monthly_quota_amount?: string|null}  $data
     */
    public function update(Fleet $fleet, array $data, User $actor): Fleet
    {
        return DB::transaction(function () use ($fleet, $data, $actor): Fleet {
            $fleet = $this->lock($fleet);
            $this->ensureCompanyActive($this->companyOf($fleet));

            $old = $this->quotaValues($fleet) + ['name' => $fleet->name];
            $fleet->forceFill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'monthly_quota_liters' => Decimal::normalize($data['monthly_quota_liters'] ?? null, 3),
                'monthly_quota_amount' => Decimal::normalize($data['monthly_quota_amount'] ?? null),
            ]);

            if (! $fleet->isDirty()) {
                return $fleet;
            }

            $fleet->save();
            $this->audit->record('fleet.updated', $fleet, $actor, $fleet->company_id, $old, $this->quotaValues($fleet) + ['name' => $fleet->name]);

            return $fleet;
        });
    }

    /** Activate or deactivate; both directions are always allowed. */
    public function changeStatus(Fleet $fleet, FleetStatus $status, User $actor): Fleet
    {
        return DB::transaction(function () use ($fleet, $status, $actor): Fleet {
            $fleet = $this->lock($fleet);

            if ($fleet->status === $status) {
                return $fleet;
            }

            $old = $fleet->status;
            $fleet->forceFill(['status' => $status])->save();

            $this->audit->record('fleet.status_changed', $fleet, $actor, $fleet->company_id,
                ['status' => $old->value],
                ['status' => $status->value],
            );

            return $fleet;
        });
    }

    /**
     * Delete the fleet. The SET NULL foreign keys ungroup its vehicles,
     * drivers and cards, which keep working exactly as before; the audit
     * row keeps the history (docs/sdd/fase-1-flotas/spec.md, D2).
     */
    public function delete(Fleet $fleet, User $actor): void
    {
        DB::transaction(function () use ($fleet, $actor): void {
            $fleet = $this->lock($fleet);
            $this->ensureCompanyActive($this->companyOf($fleet));

            $this->audit->record('fleet.deleted', $fleet, $actor, $fleet->company_id,
                $this->quotaValues($fleet) + ['name' => $fleet->name, 'status' => $fleet->status->value],
                null);

            $fleet->delete();
        });
    }

    /**
     * Assign vehicles, drivers and cards to the fleet. Every record must
     * belong to the fleet's company; anything else is refused and nothing
     * changes. Records already in another fleet move to this one.
     *
     * @param  array{vehicles?: list<int>, drivers?: list<int>, cards?: list<int>}  $members
     */
    public function assign(Fleet $fleet, array $members, User $actor): void
    {
        DB::transaction(function () use ($fleet, $members, $actor): void {
            $fleet = $this->lock($fleet);
            $this->ensureCompanyActive($this->companyOf($fleet));

            $changes = [
                'vehicles' => $this->moveMembers(Vehicle::query(), $fleet, $members['vehicles'] ?? []),
                'drivers' => $this->moveMembers(Driver::query(), $fleet, $members['drivers'] ?? []),
                'cards' => $this->moveMembers(FuelCard::query(), $fleet, $members['cards'] ?? []),
            ];

            if (array_sum(array_map('count', $changes)) === 0) {
                return;
            }

            $this->audit->record('fleet.assignment_changed', $fleet, $actor, $fleet->company_id, null, [
                'assigned' => $changes,
            ]);
        });
    }

    /**
     * Unassign members: their fleet_id becomes NULL and they keep working
     * without the fleet's grouping.
     *
     * @param  array{vehicles?: list<int>, drivers?: list<int>, cards?: list<int>}  $members
     */
    public function unassign(Fleet $fleet, array $members, User $actor): void
    {
        DB::transaction(function () use ($fleet, $members, $actor): void {
            $fleet = $this->lock($fleet);

            $changes = [
                'vehicles' => $this->removeMembers(Vehicle::query(), $fleet, $members['vehicles'] ?? []),
                'drivers' => $this->removeMembers(Driver::query(), $fleet, $members['drivers'] ?? []),
                'cards' => $this->removeMembers(FuelCard::query(), $fleet, $members['cards'] ?? []),
            ];

            if (array_sum(array_map('count', $changes)) === 0) {
                return;
            }

            $this->audit->record('fleet.assignment_changed', $fleet, $actor, $fleet->company_id,
                ['unassigned' => $changes],
                null);
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  list<int>  $ids
     * @return list<int> the IDs that were assigned
     */
    private function moveMembers(Builder $query, Fleet $fleet, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $found = $query->whereKey($ids)->where('company_id', $fleet->company_id)->pluck('id')->all();

        if (count($found) !== count($ids)) {
            throw ValidationException::withMessages(['members' => 'Every member must belong to the fleet\'s company.']);
        }

        $query->whereKey($found)->update(['fleet_id' => $fleet->id, 'updated_at' => now()]);

        return $found;
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  list<int>  $ids
     * @return list<int> the IDs that were unassigned
     */
    private function removeMembers(Builder $query, Fleet $fleet, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $found = $query->whereKey($ids)->where('fleet_id', $fleet->id)->pluck('id')->all();
        $query->whereKey($found)->update(['fleet_id' => null, 'updated_at' => now()]);

        return $found;
    }

    private function lock(Fleet $fleet): Fleet
    {
        return Fleet::query()->lockForUpdate()->findOrFail($fleet->id);
    }

    private function ensureCompanyActive(Company $company): void
    {
        if ($company->status !== CompanyStatus::Active) {
            throw BusinessRuleViolation::companyInactive();
        }
    }

    private function companyOf(Fleet $fleet): Company
    {
        return Company::query()->findOrFail($fleet->company_id);
    }

    /** @return array<string, string|null> */
    private function quotaValues(Fleet $fleet): array
    {
        return [
            'monthly_quota_liters' => $fleet->monthly_quota_liters,
            'monthly_quota_amount' => $fleet->monthly_quota_amount,
        ];
    }
}
