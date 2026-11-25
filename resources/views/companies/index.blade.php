@extends('layouts.app')

@section('title', 'Empresas')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Empresas</h1>
        @can('create', App\Models\Company::class)
            <a class="btn btn-primary" href="{{ route('companies.create') }}">Agregar empresa</a>
        @endcan
    </div>

    @include('partials.list-filters', [
        'searchLabel' => 'Nombre o número fiscal',
        'statusOptions' => ['active' => 'Activa', 'inactive' => 'Inactiva'],
        'companies' => null,
    ])

    @if ($companies->isEmpty())
        <p class="text-body-secondary">Ninguna empresa coincide con estos filtros.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Número fiscal</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-end">Vehículos</th>
                        <th scope="col" class="text-end">Conductores</th>
                        <th scope="col" class="text-end">Tarjetas</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($companies as $company)
                        <tr>
                            <td>{{ $company->name }}</td>
                            <td>{{ $company->tax_no ?? '—' }}</td>
                            <td>@include('partials.active-badge', ['active' => $company->status === App\Enums\CompanyStatus::Active])</td>
                            <td class="text-end">{{ $company->vehicles_count }}</td>
                            <td class="text-end">{{ $company->drivers_count }}</td>
                            <td class="text-end">{{ $company->fuel_cards_count }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('companies.edit', $company) }}">Editar<span class="visually-hidden"> {{ $company->name }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $companies->links() }}
    @endif
@endsection
