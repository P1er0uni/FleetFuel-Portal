<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Company;
use App\Models\CompanyStation;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Which stations a company may use (Fase 6 / área 3). A company without the
 * restriction may use any active station; with restrict_stations on, only the
 * stations stored in company_stations.
 */
class CompanyStationService
{
    public function __construct(private readonly AuditService $audit) {}

    public function allowed(Company $company, Station $station): bool
    {
        if (! $company->restrict_stations) {
            return true;
        }

        return CompanyStation::query()
            ->where('company_id', $company->id)
            ->where('station_id', $station->id)
            ->exists();
    }

    /**
     * Replace the company's station agreement and toggle the restriction.
     *
     * @param  list<int>  $stationIds
     */
    public function sync(Company $company, bool $restrict, array $stationIds, User $actor): void
    {
        DB::transaction(function () use ($company, $restrict, $stationIds, $actor): void {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);

            if ($company->status !== CompanyStatus::Active) {
                throw BusinessRuleViolation::companyInactive();
            }

            $ids = array_values(array_unique(array_map('intval', $stationIds)));

            if ($ids !== []) {
                $valid = Station::query()->whereKey($ids)->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();

                if (count($valid) !== count($ids)) {
                    throw ValidationException::withMessages(['station_ids' => 'Todas las estaciones deben existir y estar activas.']);
                }
            } else {
                $valid = [];
            }

            $oldIds = CompanyStation::query()->where('company_id', $company->id)
                ->pluck('station_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $newIds = collect($valid)->sort()->values()->all();

            $old = ['restrict_stations' => $company->restrict_stations, 'station_ids' => $oldIds];

            $company->forceFill(['restrict_stations' => $restrict])->save();

            CompanyStation::query()->where('company_id', $company->id)->delete();
            foreach ($valid as $stationId) {
                CompanyStation::query()->create(['company_id' => $company->id, 'station_id' => $stationId]);
            }

            $this->audit->record('company.stations_changed', $company, $actor, $company->id, $old, [
                'restrict_stations' => $restrict,
                'station_ids' => $newIds,
            ]);
        });
    }
}
