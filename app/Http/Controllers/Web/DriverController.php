<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FiltersLists;
use App\Http\Requests\Fleet\StoreDriverRequest;
use App\Http\Requests\Fleet\UpdateDriverRequest;
use App\Http\Requests\SetActiveRequest;
use App\Models\Driver;
use App\Services\VehicleAndDriverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DriverController extends Controller
{
    use FiltersLists;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Driver::class);
        $user = $this->actor($request);

        $query = Driver::query()->visibleTo($user)->with('company');
        $this->applySearch($query, $this->searchTerm($request), ['name', 'license_no']);
        $this->applyActiveFilter($query, $request);
        $this->applyCompanyFilter($query, $request, $user);

        return view('drivers.index', [
            'drivers' => $query->orderBy('name')->orderBy('id')->paginate(25)->withQueryString(),
            'companies' => $this->companyOptions($user),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Driver::class);
        $user = $this->actor($request);

        return view('drivers.form', [
            'driver' => null,
            'companies' => $this->companyOptions($user, activeOnly: true),
            'ownCompany' => $user->isAdmin() ? null : $user->loadMissing('company')->company,
            'fleets' => $this->fleetOptions($user->isAdmin() ? null : (int) $user->company_id),
        ]);
    }

    public function store(StoreDriverRequest $request, VehicleAndDriverService $fleet): RedirectResponse
    {
        $driver = $fleet->createDriver($request->company(), $request->validated(), $this->actor($request));

        return redirect()->route('drivers.index')->with('status', "Conductor {$driver->name} agregado.");
    }

    public function edit(Driver $driver): View
    {
        Gate::authorize('update', $driver);

        return view('drivers.form', [
            'driver' => $driver->loadMissing('company'),
            'companies' => null,
            'ownCompany' => $driver->company,
            'fleets' => $this->fleetOptions($driver->company_id),
        ]);
    }

    public function update(UpdateDriverRequest $request, Driver $driver, VehicleAndDriverService $fleet): RedirectResponse
    {
        $fleet->updateDriver($driver, $request->validated(), $this->actor($request));

        return redirect()->route('drivers.index')->with('status', "Conductor {$driver->name} guardado.");
    }

    public function updateActive(SetActiveRequest $request, Driver $driver, VehicleAndDriverService $fleet): RedirectResponse
    {
        $driver = $fleet->setDriverActive($driver, $request->boolean('is_active'), $this->actor($request));

        return back()->with('status', "El conductor {$driver->name} ahora está ".($driver->is_active ? 'activo.' : 'inactivo.'));
    }
}
