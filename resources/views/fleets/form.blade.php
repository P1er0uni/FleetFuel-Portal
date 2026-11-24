@extends('layouts.app')

@section('title', $fleet ? 'Editar flota' : 'Agregar flota')

@section('content')
    <h1 class="h3 mb-3">{{ $fleet ? 'Editar flota '.$fleet->name : 'Agregar flota' }}</h1>

    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ $fleet ? route('fleets.update', $fleet) : route('fleets.store') }}" class="card card-body shadow-sm">
                @csrf
                @if ($fleet)
                    @method('PUT')
                @endif

                @if (! $fleet)
                    @if ($companies !== null)
                        @if ($companies->isEmpty())
                            <div class="alert alert-warning">Todavía no hay ninguna empresa activa. Primero <a href="{{ route('companies.create') }}">agrega una empresa</a>.</div>
                        @endif
                        <x-form.select name="company_id" label="Empresa" :options="$companies" placeholder="Elige una empresa" required />
                    @else
                        <p class="mb-3">Empresa: <strong>{{ $ownCompany?->name }}</strong></p>
                    @endif
                @else
                    <p class="mb-3">Empresa: <strong>{{ $ownCompany?->name }}</strong></p>
                @endif

                <x-form.input name="name" label="Nombre" :value="$fleet?->name" required maxlength="100"
                              help="Único dentro de la empresa." />
                <x-form.input name="description" label="Descripción" :value="$fleet?->description" maxlength="255" help="Opcional." />

                <div class="row">
                    <div class="col-md-6">
                        <x-form.input name="monthly_quota_liters" label="Cuota mensual (litros)" :value="$fleet?->monthly_quota_liters" inputmode="decimal"
                                      help="Déjalo vacío para no poner límite." />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="monthly_quota_amount" label="Cuota mensual (USD)" :value="$fleet?->monthly_quota_amount" inputmode="decimal"
                                      help="Déjalo vacío para no poner límite." />
                    </div>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ $fleet ? route('fleets.show', $fleet) : route('fleets.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
