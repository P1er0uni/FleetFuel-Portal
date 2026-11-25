@extends('layouts.app')

@section('title', $vehicle ? 'Editar vehículo' : 'Agregar vehículo')

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">{{ $vehicle ? 'Editar vehículo '.$vehicle->plate_no : 'Agregar vehículo' }}</h1>
        @if ($vehicle)
            @include('partials.favorite-star', ['type' => 'vehicle', 'id' => $vehicle->id])
        @endif
    </div>

    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ $vehicle ? route('vehicles.update', $vehicle) : route('vehicles.store') }}" class="card card-body shadow-sm">
                @csrf
                @if ($vehicle)
                    @method('PUT')
                @endif

                @if ($companies !== null)
                    @if ($companies->isEmpty())
                        <div class="alert alert-warning">Todavía no hay ninguna empresa activa. Primero <a href="{{ route('companies.create') }}">agrega una empresa</a>.</div>
                    @endif
                    <x-form.select name="company_id" label="Empresa" :options="$companies" placeholder="Elige una empresa" required />
                @else
                    <p class="mb-3">Empresa: <strong>{{ $ownCompany?->name }}</strong></p>
                @endif

                <x-form.input name="plate_no" label="Número de placa" :value="$vehicle?->plate_no" required maxlength="30"
                              help="Letras, dígitos, espacios y guiones. Se guarda en mayúsculas." />

                @if ($vehicle)
                    <p class="mb-3">Tipo de combustible: <strong>{{ $vehicle->fuel_type->value === 'diesel' ? 'Diésel' : 'Gasolina' }}</strong> (fijo al agregar el vehículo)</p>
                @else
                    <x-form.select name="fuel_type" label="Tipo de combustible" :options="['diesel' => 'Diésel', 'petrol' => 'Gasolina']" placeholder="Elige un tipo de combustible" required />
                @endif

                <div class="row">
                    <div class="col-md-6">
                        <x-form.input name="tank_capacity_l" label="Capacidad del tanque (L)" :value="$vehicle?->tank_capacity_l" required inputmode="decimal"
                                      help="Por ejemplo 60 o 60.5." />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="odometer_km" label="Odómetro (km)" type="number" min="0" step="1" :value="$vehicle?->odometer_km" help="Opcional." />
                    </div>
                </div>

                @if ($fleets->isNotEmpty())
                    <x-form.select name="fleet_id" label="Flota (opcional)" :options="$fleets" :selected="$vehicle?->fleet_id"
                                   placeholder="Sin flota" help="Agrupa el vehículo con la cuota y las reglas de esa flota." />
                @endif

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ route('vehicles.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
