<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FiltersLists;
use App\Http\Requests\Fleet\StoreVehicleRequest;
use App\Http\Requests\Fleet\UpdateVehicleRequest;
use App\Http\Requests\SetActiveRequest;
use App\Models\Vehicle;
use App\Services\VehicleAndDriverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Thin controller: the Form Request validates and authorizes, VehicleAndDriverService
 * applies the business rules, and {vehicle} arrives already tenant-scoped
 * (AppServiceProvider::bindTenantScopedModels), so another company's
 * vehicle is a 404 before any code here runs.
 */
class VehicleController extends Controller
{
    use FiltersLists;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vehicle::class);
        $user = $this->actor($request);

        $query = Vehicle::query()->visibleTo($user)->with('company');
        $term = $this->searchTerm($request);
        $this->applySearch($query, $term === null ? null : Vehicle::normalizePlate($term), ['plate_no']);
        $this->applyActiveFilter($query, $request);
        $this->applyCompanyFilter($query, $request, $user);

        return view('vehicles.index', [
            'vehicles' => $query->orderBy('plate_no')->paginate(25)->withQueryString(),
            'companies' => $this->companyOptions($user),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Vehicle::class);
        $user = $this->actor($request);

        return view('vehicles.form', [
            'vehicle' => null,
            'companies' => $this->companyOptions($user, activeOnly: true),
            'ownCompany' => $user->isAdmin() ? null : $user->loadMissing('company')->company,
            'fleets' => $this->fleetOptions($user->isAdmin() ? null : (int) $user->company_id),
        ]);
    }

    public function store(StoreVehicleRequest $request, VehicleAndDriverService $fleet): RedirectResponse
    {
        $vehicle = $fleet->createVehicle($request->company(), $request->validated(), $this->actor($request));

        return redirect()->route('vehicles.index')->with('status', "Vehículo {$vehicle->plate_no} agregado.");
    }

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('update', $vehicle);

        return view('vehicles.form', [
            'vehicle' => $vehicle->loadMissing('company'),
            'companies' => null,
            'ownCompany' => $vehicle->company,
            'fleets' => $this->fleetOptions($vehicle->company_id),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle, VehicleAndDriverService $fleet): RedirectResponse
    {
        $fleet->updateVehicle($vehicle, $request->validated(), $this->actor($request));

        return redirect()->route('vehicles.index')->with('status', "Vehículo {$vehicle->plate_no} guardado.");
    }

    public function updateActive(SetActiveRequest $request, Vehicle $vehicle, VehicleAndDriverService $fleet): RedirectResponse
    {
        $vehicle = $fleet->setVehicleActive($vehicle, $request->boolean('is_active'), $this->actor($request));

        return back()->with('status', "El vehículo {$vehicle->plate_no} ahora está ".($vehicle->is_active ? 'activo.' : 'inactivo.'));
    }
}
