@extends('layouts.app')

@section('title', 'Conductores')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Conductores</h1>
        @can('create', App\Models\Driver::class)
            <a class="btn btn-primary" href="{{ route('drivers.create') }}">Agregar conductor</a>
        @endcan
    </div>

    @include('partials.list-filters', [
        'searchLabel' => 'Nombre o número de licencia',
        'statusOptions' => ['active' => 'Activo', 'inactive' => 'Inactivo'],
        'companies' => $companies,
    ])

    @if ($drivers->isEmpty())
        <p class="text-body-secondary">Ningún conductor coincide con estos filtros.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        @if ($companies !== null)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col">Licencia</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($drivers as $driver)
                        <tr>
                            <td>{{ $driver->name }}</td>
                            @if ($companies !== null)
                                <td>{{ $driver->company->name }}</td>
                            @endif
                            <td class="font-monospace">{{ $driver->license_no }}</td>
                            <td>{{ $driver->phone ?? '—' }}</td>
                            <td>@include('partials.active-badge', ['active' => $driver->is_active])</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('drivers.edit', $driver) }}">Editar<span class="visually-hidden"> {{ $driver->name }}</span></a>
                                @include('partials.active-toggle', ['action' => route('drivers.active', $driver), 'active' => $driver->is_active, 'name' => $driver->name])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $drivers->links() }}
    @endif
@endsection
