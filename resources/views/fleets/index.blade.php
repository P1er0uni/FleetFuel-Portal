@extends('layouts.app')

@use('App\Enums\FleetStatus')
@use('App\Support\Display')

@section('title', 'Flotas')

@section('content')
    @php($admin = auth()->user()->isAdmin())

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Flotas</h1>
        @can('create', App\Models\Fleet::class)
            <a class="btn btn-primary" href="{{ route('fleets.create') }}">Agregar flota</a>
        @endcan
    </div>

    @include('partials.list-filters', [
        'searchLabel' => 'Nombre o descripción',
        'statusOptions' => ['active' => 'Activa', 'inactive' => 'Inactiva'],
        'companies' => $companies,
    ])

    @if ($fleets->isEmpty())
        <p class="text-body-secondary">Ninguna flota coincide con estos filtros.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Descripción</th>
                        @if ($companies !== null)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col" class="text-end">Vehículos</th>
                        <th scope="col" class="text-end">Conductores</th>
                        <th scope="col" class="text-end">Tarjetas</th>
                        <th scope="col" class="text-end">Cuota del mes (L / %)</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fleets as $fleet)
                        @php($quota = $quotas[$fleet->id])
                        <tr>
                            <td><a href="{{ route('fleets.show', $fleet) }}">{{ $fleet->name }}</a></td>
                            <td class="small">{{ $fleet->description ?? '—' }}</td>
                            @if ($companies !== null)
                                <td>{{ $fleet->company->name }}</td>
                            @endif
                            <td class="text-end">{{ $fleet->vehicles_count }}</td>
                            <td class="text-end">{{ $fleet->drivers_count }}</td>
                            <td class="text-end">{{ $fleet->cards_count }}</td>
                            <td class="text-end text-nowrap">
                                {{ Display::decimal($quota->usedL) }} /
                                {{ $quota->quotaL === null ? 'sin límite' : Display::decimal($quota->quotaL) }}
                                <span class="d-block small {{ $quota->exceeded() ? 'text-danger' : 'text-body-secondary' }}">
                                    {{ $quota->quotaL === null ? '—' : $quota->litersPercent().' %' }}
                                    @if ($quota->exceeded())
                                        · sobre cuota
                                    @endif
                                </span>
                            </td>
                            <td>
                                @if ($fleet->status === FleetStatus::Active)
                                    <span class="badge text-bg-success">Activa</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('fleets.edit', $fleet) }}">Editar<span class="visually-hidden"> {{ $fleet->name }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $fleets->links() }}
    @endif
@endsection
