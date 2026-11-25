@extends('layouts.app')

@section('title', $station ? 'Editar estación' : 'Agregar estación')

@section('content')
    <h1 class="h3 mb-3">{{ $station ? 'Editar '.$station->name : 'Agregar estación' }}</h1>

    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ $station ? route('stations.update', $station) : route('stations.store') }}" class="card card-body shadow-sm">
                @csrf
                @if ($station)
                    @method('PUT')
                @endif

                <x-form.input name="name" label="Nombre" :value="$station?->name" required maxlength="120" />
                <div class="row">
                    <div class="col-md-6">
                        <x-form.input name="district" label="Distrito" :value="$station?->district" required maxlength="80" />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="governorate" label="Gobernación" :value="$station?->governorate" required maxlength="80" />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="latitude" label="Latitud" :value="$station?->latitude" inputmode="decimal" help="Opcional, hasta 7 decimales." />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="longitude" label="Longitud" :value="$station?->longitude" inputmode="decimal" help="Opcional, hasta 7 decimales." />
                    </div>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ route('stations.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
