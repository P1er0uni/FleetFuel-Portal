@extends('layouts.app')

@use('App\Support\Display')

@section('title', 'Vehículos')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Vehículos</h1>
        @can('create', App\Models\Vehicle::class)
            <a class="btn btn-primary" href="{{ route('vehicles.create') }}">Agregar vehículo</a>
        @endcan
    </div>

    @include('partials.list-filters', [
        'searchLabel' => 'Número de placa',
        'statusOptions' => ['active' => 'Activo', 'inactive' => 'Inactivo'],
        'companies' => $companies,
    ])

    @if ($vehicles->isEmpty())
        <p class="text-body-secondary">Ningún vehículo coincide con estos filtros.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Placa</th>
                        @if ($companies !== null)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col">Combustible</th>
                        <th scope="col" class="text-end">Tanque (L)</th>
                        <th scope="col" class="text-end">Odómetro (km)</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vehicles as $vehicle)
                        <tr>
                            <td class="font-monospace">{{ $vehicle->plate_no }}</td>
                            @if ($companies !== null)
                                <td>{{ $vehicle->company->name }}</td>
                            @endif
                            <td>{{ $vehicle->fuel_type->value === 'diesel' ? 'Diésel' : 'Gasolina' }}</td>
                            <td class="text-end">{{ Display::decimal($vehicle->tank_capacity_l) }}</td>
                            <td class="text-end">{{ $vehicle->odometer_km !== null ? number_format($vehicle->odometer_km) : '—' }}</td>
                            <td>@include('partials.active-badge', ['active' => $vehicle->is_active])</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('vehicles.edit', $vehicle) }}">Editar<span class="visually-hidden"> {{ $vehicle->plate_no }}</span></a>
                                @include('partials.active-toggle', ['action' => route('vehicles.active', $vehicle), 'active' => $vehicle->is_active, 'name' => $vehicle->plate_no])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $vehicles->links() }}
    @endif
@endsection
