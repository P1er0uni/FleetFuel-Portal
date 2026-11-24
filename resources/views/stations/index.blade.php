@extends('layouts.app')

@section('title', 'Estaciones')

@section('content')
    @php($admin = auth()->user()->isAdmin())

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">{{ $admin ? 'Estaciones' : 'Estaciones activas' }}</h1>
        @can('create', App\Models\Station::class)
            <a class="btn btn-primary" href="{{ route('stations.create') }}">Agregar estación</a>
        @endcan
    </div>

    @include('partials.list-filters', [
        'searchLabel' => 'Nombre, distrito o gobernación',
        'statusOptions' => $admin ? ['active' => 'Activa', 'inactive' => 'Inactiva'] : null,
        'companies' => null,
    ])

    @if ($stations->isEmpty())
        <p class="text-body-secondary">Ninguna estación coincide con estos filtros.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Distrito</th>
                        <th scope="col">Gobernación</th>
                        @if ($admin)
                            <th scope="col">Estado</th>
                            <th scope="col"><span class="visually-hidden">Acciones</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stations as $station)
                        <tr>
                            <td>{{ $station->name }}</td>
                            <td>{{ $station->district }}</td>
                            <td>{{ $station->governorate }}</td>
                            @if ($admin)
                                <td>@include('partials.active-badge', ['active' => $station->is_active])</td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('stations.edit', $station) }}">Editar<span class="visually-hidden"> {{ $station->name }}</span></a>
                                    @include('partials.active-toggle', ['action' => route('stations.active', $station), 'active' => $station->is_active, 'name' => $station->name])
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $stations->links() }}
    @endif
@endsection
