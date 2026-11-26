@extends('layouts.app')

@section('title', 'Estaciones habilitadas')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Estaciones habilitadas · {{ $company->name }}</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('companies.edit', $company) }}">Volver a la empresa</a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('companies.stations.update', $company) }}" class="card card-body shadow-sm">
                @csrf
                @method('PUT')

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="restrict_stations" value="1" id="restrict_stations"
                           @checked(old('restrict_stations', $company->restrict_stations))>
                    <label class="form-check-label" for="restrict_stations">
                        Restringir estaciones
                    </label>
                    <div class="form-text">
                        Si está desactivado, la empresa puede usar <strong>todas</strong> las estaciones activas.
                        Si lo activas, solo podrá usar las marcadas abajo (si no marcas ninguna, no podrá repostar en ninguna).
                    </div>
                </div>

                @if ($stations->isEmpty())
                    <p class="text-body-secondary">No hay estaciones activas.</p>
                @else
                    <p class="form-label mb-1">Estaciones permitidas</p>
                    <div class="border rounded p-2 mb-3" style="max-height: 20rem; overflow-y: auto;">
                        @foreach ($stations as $station)
                            <label class="form-check d-block">
                                <input class="form-check-input" type="checkbox" name="station_ids[]" value="{{ $station->id }}"
                                       @checked(in_array($station->id, $selected, true))>
                                <span class="form-check-label">
                                    {{ $station->name }}
                                    <span class="text-body-secondary small">· {{ $station->district }}, {{ $station->governorate }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ route('companies.index') }}">Cancelar</a>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card card-body shadow-sm">
                <h2 class="h5">Resumen</h2>
                @if (! $company->restrict_stations)
                    <p class="mb-1"><span class="badge text-bg-success">Sin restricción</span></p>
                    <p class="small text-body-secondary mb-0">La empresa puede usar todas las estaciones activas.</p>
                @else
                    <p class="mb-1"><span class="badge text-bg-warning">Restringida</span></p>
                    @if (count($selected) === 0)
                        <p class="small text-danger mb-0">No hay estaciones habilitadas: la empresa no podrá repostar.</p>
                    @else
                        <p class="small text-body-secondary mb-0">{{ count($selected) }} estación(es) habilitada(s).</p>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
