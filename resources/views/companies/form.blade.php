@extends('layouts.app')

@section('title', $company ? 'Editar empresa' : 'Agregar empresa')

@section('content')
    <h1 class="h3 mb-3">{{ $company ? 'Editar '.$company->name : 'Agregar empresa' }}</h1>

    <div class="row g-4">
        <div class="col-lg-6">
            <form method="POST" action="{{ $company ? route('companies.update', $company) : route('companies.store') }}" class="card card-body shadow-sm">
                @csrf
                @if ($company)
                    @method('PUT')
                @endif

                <x-form.input name="name" label="Nombre" :value="$company?->name" required maxlength="120" />
                <x-form.input name="tax_no" label="Número fiscal" :value="$company?->tax_no" maxlength="50" help="Opcional. Debe ser único si se proporciona." />

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ route('companies.index') }}">Cancelar</a>
                </div>
            </form>
        </div>

        @if ($company)
            @php($active = $company->status === App\Enums\CompanyStatus::Active)
            <div class="col-lg-6">
                <div class="card card-body shadow-sm">
                    <h2 class="h5">Estado @include('partials.active-badge', ['active' => $active])</h2>
                    <p class="mb-2">{{ $company->vehicles_count }} vehículos · {{ $company->drivers_count }} conductores · {{ $company->fuel_cards_count }} tarjetas</p>
                    <p class="small text-body-secondary">
                        Las empresas nunca se eliminan. Una empresa inactiva conserva todos sus datos e historial, y su flota
                        pasa a ser de solo lectura: las tarjetas aún pueden bloquearse o archivarse, y los vehículos o conductores desactivarse.
                    </p>
                    <form method="POST" action="{{ route('companies.status', $company) }}"
                          @if ($active) data-confirm="¿Desactivar {{ $company->name }}?" @endif>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $active ? 'inactive' : 'active' }}">
                        <button type="submit" @class(['btn', 'btn-outline-danger' => $active, 'btn-outline-success' => ! $active])>
                            {{ $active ? 'Desactivar empresa' : 'Reactivar empresa' }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
