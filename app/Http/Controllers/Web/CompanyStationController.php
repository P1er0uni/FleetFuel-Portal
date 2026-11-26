<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reference\UpdateCompanyStationsRequest;
use App\Models\Company;
use App\Models\Station;
use App\Models\User;
use App\Services\CompanyStationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Which stations a company may use (Fase 6, área 3). Admin only.
 */
class CompanyStationController extends Controller
{
    public function edit(Company $company): View
    {
        Gate::authorize('update', $company);

        return view('companies.stations', [
            'company' => $company,
            'stations' => Station::query()->where('is_active', true)->orderBy('name')->get(),
            'selected' => $company->stations()->pluck('stations.id')->map(fn ($id): int => (int) $id)->all(),
        ]);
    }

    public function update(UpdateCompanyStationsRequest $request, Company $company, CompanyStationService $service): RedirectResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $service->sync($company, $request->boolean('restrict_stations'), $request->stationIds(), $actor);

        return redirect()->route('companies.stations.edit', $company)->with('status', 'Estaciones habilitadas guardadas.');
    }
}
