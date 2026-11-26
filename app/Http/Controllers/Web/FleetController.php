<?php

namespace App\Http\Controllers\Web;

use App\Enums\FleetStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FiltersLists;
use App\Http\Requests\Fleets\AssignFleetMembersRequest;
use App\Http\Requests\Fleets\ChangeFleetStatusRequest;
use App\Http\Requests\Fleets\StoreFleetRequest;
use App\Http\Requests\Fleets\StoreFleetRuleRequest;
use App\Http\Requests\Fleets\UpdateFleetRequest;
use App\Models\Driver;
use App\Models\Fleet;
use App\Models\FleetRule;
use App\Models\FuelCard;
use App\Models\Product;
use App\Models\Station;
use App\Models\Vehicle;
use App\Services\FleetRuleService;
use App\Services\FleetService;
use App\Support\FleetQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Fleets (docs/sdd/fase-1-flotas/spec.md): a company's sub-grouping of
 * vehicles, drivers and cards with an aggregated monthly quota and basic
 * fuel rules. Thin controller: the Form Requests validate and authorize,
 * FleetService and FleetRuleService apply the business rules, and {fleet}
 * arrives already tenant-scoped (AppServiceProvider), so another
 * company's fleet is a 404 before any code here runs.
 */
class FleetController extends Controller
{
    use FiltersLists;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Fleet::class);
        $user = $this->actor($request);

        $query = Fleet::query()->visibleTo($user)->with('company');
        $this->applySearch($query, $this->searchTerm($request), ['name', 'description']);
        $this->applyCompanyFilter($query, $request, $user);

        $status = $request->query('status');
        if ($status === 'active' || $status === 'inactive') {
            $query->where('status', $status);
        }

        $fleets = $query->withCount(['vehicles', 'drivers', 'fuelCards as cards_count'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // The current month's consumption for the quota columns, one query
        // for the whole page.
        $quotas = FleetQuota::many($fleets);

        return view('fleets.index', [
            'fleets' => $fleets,
            'quotas' => $quotas,
            'companies' => $this->companyOptions($user),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Fleet::class);
        $user = $this->actor($request);

        return view('fleets.form', [
            'fleet' => null,
            'companies' => $this->companyOptions($user, activeOnly: true),
            'ownCompany' => $user->isAdmin() ? null : $user->loadMissing('company')->company,
            'ruleOptions' => $this->ruleOptions(),
        ]);
    }

    public function store(StoreFleetRequest $request, FleetService $fleets): RedirectResponse
    {
        $fleet = $fleets->createWithRules($request->company(), $request->fleetAttributes(), $request->rulesInput(), $this->actor($request));

        return redirect()->route('fleets.show', $fleet)->with('status', "Flota {$fleet->name} creada.");
    }

    public function show(Request $request, Fleet $fleet): View
    {
        Gate::authorize('view', $fleet);
        $user = $this->actor($request);

        $fleet->loadMissing('company')
            ->loadCount(['vehicles', 'drivers', 'fuelCards']);

        return view('fleets.show', [
            'fleet' => $fleet,
            'quota' => FleetQuota::for($fleet),
            'vehicles' => $fleet->vehicles()->orderBy('plate_no')->paginate(10, ['*'], 'vehicles')->withQueryString(),
            'drivers' => $fleet->drivers()->orderBy('name')->paginate(10, ['*'], 'drivers')->withQueryString(),
            'cards' => $fleet->fuelCards()->orderBy('card_no')->paginate(10, ['*'], 'cards')->withQueryString(),
            'rules' => $fleet->rules()->orderBy('rule_type')->get(),
            'canUpdate' => $user->can('update', $fleet),
            'ruleOptions' => $this->ruleOptions(),
            'assignOptions' => $this->assignOptions($fleet),
        ]);
    }

    /**
     * Active products and stations for the rule form's list selector.
     *
     * @return array<string, Collection<int, string>>
     */
    private function ruleOptions(): array
    {
        return [
            'products' => Product::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'stations' => Station::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    /**
     * The company's records not yet in this fleet, for the assign form.
     *
     * @return array<string, Collection<int, string>>
     */
    private function assignOptions(Fleet $fleet): array
    {
        return [
            'vehicles' => Vehicle::query()
                ->where('company_id', $fleet->company_id)
                ->where(fn ($query) => $query->whereNull('fleet_id')->orWhere('fleet_id', '!=', $fleet->id))
                ->orderBy('plate_no')->pluck('plate_no', 'id'),
            'drivers' => Driver::query()
                ->where('company_id', $fleet->company_id)
                ->where(fn ($query) => $query->whereNull('fleet_id')->orWhere('fleet_id', '!=', $fleet->id))
                ->orderBy('name')->pluck('name', 'id'),
            'cards' => FuelCard::query()
                ->where('company_id', $fleet->company_id)
                ->where(fn ($query) => $query->whereNull('fleet_id')->orWhere('fleet_id', '!=', $fleet->id))
                ->orderBy('card_no')->pluck('card_no', 'id'),
        ];
    }

    public function edit(Fleet $fleet): View
    {
        Gate::authorize('update', $fleet);

        return view('fleets.form', [
            'fleet' => $fleet->loadMissing('company'),
            'companies' => null,
            'ownCompany' => $fleet->company,
            'ruleOptions' => $this->ruleOptions(),
            'rules' => $fleet->rules()->orderBy('rule_type')->get(),
            'canUpdate' => true,
        ]);
    }

    public function update(UpdateFleetRequest $request, Fleet $fleet, FleetService $fleets): RedirectResponse
    {
        $fleet = $fleets->update($fleet, $request->fleetAttributes(), $this->actor($request));

        return redirect()->route('fleets.show', $fleet)->with('status', "Flota {$fleet->name} guardada.");
    }

    public function destroy(Request $request, Fleet $fleet, FleetService $fleets): RedirectResponse
    {
        Gate::authorize('delete', $fleet);
        $name = $fleet->name;
        $fleets->delete($fleet, $this->actor($request));

        return redirect()->route('fleets.index')->with('status', "Flota {$name} eliminada. Sus vehículos, conductores y tarjetas quedaron sin flota.");
    }

    public function updateStatus(ChangeFleetStatusRequest $request, Fleet $fleet, FleetService $fleets): RedirectResponse
    {
        $fleet = $fleets->changeStatus($fleet, $request->status(), $this->actor($request));

        return redirect()->route('fleets.show', $fleet)
            ->with('status', "La flota {$fleet->name} ahora está ".($fleet->status === FleetStatus::Active ? 'activa.' : 'inactiva.'));
    }

    public function storeRule(StoreFleetRuleRequest $request, Fleet $fleet, FleetRuleService $rules): RedirectResponse
    {
        $rule = $request->ruleAttributes();

        $rules->save(
            $fleet,
            $rule['type'],
            $rule['value'],
            $rule['active'],
            $this->actor($request),
            $rule['enforcement'],
            $rule['valid_from'],
            $rule['valid_to'],
        );

        return redirect()->route('fleets.show', $fleet)->with('status', 'Regla guardada.');
    }

    public function destroyRule(Request $request, Fleet $fleet, FleetRule $rule, FleetRuleService $rules): RedirectResponse
    {
        Gate::authorize('update', $fleet);
        $rules->delete($fleet, $rule, $this->actor($request));

        return redirect()->route('fleets.show', $fleet)->with('status', 'Regla eliminada.');
    }

    public function assign(AssignFleetMembersRequest $request, Fleet $fleet, FleetService $fleets): RedirectResponse
    {
        $fleets->assign($fleet, $request->members(), $this->actor($request));

        return redirect()->route('fleets.show', $fleet)->with('status', 'Miembros asignados a la flota.');
    }

    public function unassign(AssignFleetMembersRequest $request, Fleet $fleet, FleetService $fleets): RedirectResponse
    {
        $fleets->unassign($fleet, $request->members(), $this->actor($request));

        return redirect()->route('fleets.show', $fleet)->with('status', 'Miembros desasignados de la flota.');
    }
}
