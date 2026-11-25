@extends('layouts.app')

@use('App\Enums\FleetStatus')
@use('App\Support\Display')
@use('App\Support\Redact')

@section('title', 'Flota '.$fleet->name)

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
        <h1 class="h3 mb-0">Flota {{ $fleet->name }}
            @if ($fleet->status === FleetStatus::Active)
                <span class="badge text-bg-success">Activa</span>
            @else
                <span class="badge text-bg-secondary">Inactiva</span>
            @endif
        </h1>
        <span class="text-body-secondary d-inline-flex align-items-center gap-2">
            {{ $fleet->company->name }}
            @include('partials.favorite-star', ['type' => 'fleet', 'id' => $fleet->id])
        </span>
    </div>

    @if ($fleet->description)
        <p class="text-body-secondary">{{ $fleet->description }}</p>
    @endif

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#fleet-summary" type="button" role="tab">Resumen</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#fleet-vehicles" type="button" role="tab">Vehículos ({{ $fleet->vehicles_count }})</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#fleet-drivers" type="button" role="tab">Conductores ({{ $fleet->drivers_count }})</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#fleet-cards" type="button" role="tab">Tarjetas ({{ $fleet->fuel_cards_count }})</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#fleet-rules" type="button" role="tab">Reglas ({{ $rules->count() }})</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="fleet-summary" role="tabpanel">
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="h5">Cuota del mes (hora de Beirut)</h2>
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th scope="row">Litros usados / cuota</th>
                                        <td class="text-end">{{ Display::decimal($quota->usedL) }} / {{ $quota->quotaL === null ? 'sin límite' : Display::decimal($quota->quotaL) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">USD usados / cuota</th>
                                        <td class="text-end">{{ Display::decimal($quota->usedUsd) }} / {{ $quota->quotaUsd === null ? 'sin límite' : Display::decimal($quota->quotaUsd) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Estado</th>
                                        <td class="text-end">
                                            @if ($quota->exceeded())
                                                <span class="badge text-bg-danger">Sobre cuota</span>
                                            @else
                                                <span class="badge text-bg-success">Dentro de cuota</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            @if ($quota->exceeded())
                                <p class="small text-warning-emphasis mb-0">La cuota excedida es una señal del panel: no bloquea las compras por sí sola.</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($canUpdate)
                    <div class="col-lg-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h2 class="h5">Acciones</h2>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-outline-primary" href="{{ route('fleets.edit', $fleet) }}">Editar</a>
                                    <form method="POST" action="{{ route('fleets.status', $fleet) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $fleet->status === FleetStatus::Active ? 'inactive' : 'active' }}">
                                        <button type="submit" class="btn {{ $fleet->status === FleetStatus::Active ? 'btn-outline-secondary' : 'btn-outline-success' }}">
                                            {{ $fleet->status === FleetStatus::Active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('fleets.destroy', $fleet) }}"
                                          data-confirm="¿Eliminar la flota {{ $fleet->name }}? Sus vehículos, conductores y tarjetas quedarán sin flota. Esto no se puede deshacer.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger">Eliminar flota</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="tab-pane fade" id="fleet-vehicles" role="tabpanel">
            @include('fleets._members-table', [
                'members' => $vehicles,
                'columns' => [['Placa', 'plate_no'], ['Combustible', 'fuel'], ['Tanque (L)', 'tank']],
                'type' => 'vehicles',
            ])
        </div>

        <div class="tab-pane fade" id="fleet-drivers" role="tabpanel">
            @include('fleets._members-table', [
                'members' => $drivers,
                'columns' => [['Nombre', 'name'], ['Licencia', 'license_no']],
                'type' => 'drivers',
            ])
        </div>

        <div class="tab-pane fade" id="fleet-cards" role="tabpanel">
            @include('fleets._members-table', [
                'members' => $cards,
                'columns' => [['Tarjeta', 'card'], ['Vehículo', 'plate'], ['Conductor', 'driver']],
                'type' => 'cards',
            ])
        </div>

        <div class="tab-pane fade" id="fleet-rules" role="tabpanel">
            @include('fleets._rules')
        </div>
    </div>
@endsection
