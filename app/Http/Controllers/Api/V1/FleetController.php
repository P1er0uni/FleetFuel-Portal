<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListFleetMembersRequest;
use App\Http\Requests\Api\V1\ListFleetsRequest;
use App\Http\Requests\Api\V1\StoreFleetRequest;
use App\Http\Requests\Api\V1\UpdateFleetRequest;
use App\Http\Resources\CardResource;
use App\Http\Resources\DriverResource;
use App\Http\Resources\FleetResource;
use App\Http\Resources\VehicleResource;
use App\Models\Fleet;
use App\Models\User;
use App\Services\FleetService;
use App\Support\FleetQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Fleets (docs/sdd/fase-1-flotas/spec.md) for admins (every company) and
 * company managers (their own): list, create, detail with rules and the
 * current quota, update, delete, the current usage, and the member
 * lists. {fleet} arrives already tenant-scoped (AppServiceProvider), so
 * another company's fleet is a 404 before any code here runs. Writes go
 * through FleetService, like the web screens.
 */
class FleetController extends Controller
{
    use RespondsWithPages;

    public function index(ListFleetsRequest $request): JsonResponse
    {
        $page = Fleet::query()
            ->visibleTo($this->user($request))
            ->when($request->companyFilter(), fn ($query, $company) => $query->where('company_id', $company))
            ->orderBy('name')
            ->paginate($request->perPage())
            ->withQueryString();

        return $this->pageResponse($page, FleetResource::class, $request);
    }

    public function store(StoreFleetRequest $request, FleetService $fleets): JsonResponse
    {
        $fleet = $fleets->create($request->company(), $request->fleetAttributes(), $this->user($request));

        return FleetResource::make($fleet)
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.v1.fleets.show', $fleet, absolute: false));
    }

    public function show(Request $request, Fleet $fleet): JsonResponse
    {
        Gate::authorize('view', $fleet);

        $resource = FleetResource::make($fleet->load(['rules' => fn ($query) => $query->orderBy('rule_type')]));
        $resource->usage = FleetQuota::for($fleet);

        return $resource->response();
    }

    public function update(UpdateFleetRequest $request, Fleet $fleet, FleetService $fleets): JsonResponse
    {
        return FleetResource::make(
            $fleets->update($fleet, $request->fleetAttributes(), $this->user($request)),
        )->response();
    }

    public function destroy(Request $request, Fleet $fleet, FleetService $fleets): Response
    {
        Gate::authorize('delete', $fleet);
        $fleets->delete($fleet, $this->user($request));

        return response()->noContent();
    }

    public function usage(Request $request, Fleet $fleet): JsonResponse
    {
        Gate::authorize('view', $fleet);

        return response()->json(['data' => FleetQuota::for($fleet)->toArray()]);
    }

    public function vehicles(ListFleetMembersRequest $request, Fleet $fleet): JsonResponse
    {
        return $this->pageResponse(
            $fleet->vehicles()->orderBy('plate_no')->paginate($request->perPage()),
            VehicleResource::class,
            $request,
        );
    }

    public function drivers(ListFleetMembersRequest $request, Fleet $fleet): JsonResponse
    {
        return $this->pageResponse(
            $fleet->drivers()->orderBy('name')->paginate($request->perPage()),
            DriverResource::class,
            $request,
        );
    }

    public function cards(ListFleetMembersRequest $request, Fleet $fleet): JsonResponse
    {
        return $this->pageResponse(
            $fleet->fuelCards()->orderBy('card_no')->paginate($request->perPage()),
            CardResource::class,
            $request,
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
