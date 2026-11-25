@extends('layouts.app')

@section('title', $driver ? 'Editar conductor' : 'Agregar conductor')

@section('content')
    <h1 class="h3 mb-3">{{ $driver ? 'Editar conductor '.$driver->name : 'Agregar conductor' }}</h1>

    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ $driver ? route('drivers.update', $driver) : route('drivers.store') }}" class="card card-body shadow-sm">
                @csrf
                @if ($driver)
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

                <x-form.input name="name" label="Nombre" :value="$driver?->name" required maxlength="120" />
                <div class="row">
                    <div class="col-md-6">
                        <x-form.input name="license_no" label="Número de licencia" :value="$driver?->license_no" required maxlength="50"
                                      help="Único dentro de la empresa." />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="phone" label="Teléfono" type="tel" :value="$driver?->phone" maxlength="30" help="Opcional." />
                    </div>
                </div>

                @if ($fleets->isNotEmpty())
                    <x-form.select name="fleet_id" label="Flota (opcional)" :options="$fleets" :selected="$driver?->fleet_id"
                                   placeholder="Sin flota" help="Agrupa al conductor con la cuota y las reglas de esa flota." />
                @endif

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ route('drivers.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
