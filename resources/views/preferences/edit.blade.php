@extends('layouts.app')

@section('title', 'Preferencias')

@section('content')
    @php
        $selectedWidgets = is_array($values['dashboard_widgets']) ? $values['dashboard_widgets'] : [];
        $orderedWidgets = [];
        foreach ($widgets as $widget) {
            if (in_array($widget['key'], $selectedWidgets, true)) {
                $orderedWidgets[] = $widget;
            }
        }
        foreach ($widgets as $widget) {
            if (! in_array($widget['key'], $selectedWidgets, true)) {
                $orderedWidgets[] = $widget;
            }
        }
    @endphp

    <h1 class="h3 mb-3">Preferencias</h1>

    <form method="POST" action="{{ route('preferences.update') }}" class="row g-3">
        @csrf
        @method('PUT')

        <div class="col-lg-4">
            <section class="card card-body shadow-sm h-100">
                <h2 class="h5">General</h2>
                <x-form.select name="theme" label="Tema" :options="$themes" :selected="$values['theme']" help="Claro u oscuro." />
                <x-form.select name="language" label="Idioma" :options="$languages" :selected="$values['language']" />
                <x-form.select name="date_format" label="Formato de fecha" :options="array_combine($dateFormats, $dateFormats)" :selected="$values['date_format']" />
                <x-form.select name="items_per_page" label="Filas por página" :options="array_combine($itemsPerPageOptions, $itemsPerPageOptions)" :selected="$values['items_per_page']" />
            </section>
        </div>

        <div class="col-lg-4">
            <section class="card card-body shadow-sm h-100">
                <h2 class="h5">Dashboard</h2>
                <x-form.select name="default_fleet_id" label="Flota por defecto" :options="$fleets" placeholder="Ninguna" :selected="$values['default_fleet_id']"
                              help="Preselecciona esta flota en los reportes." />
                <p class="form-label mb-1">Widgets visibles (arrastra para ordenar)</p>
                <ul id="widget-order" class="list-group">
                    @foreach ($orderedWidgets as $widget)
                        <li class="list-group-item d-flex align-items-center gap-2" data-key="{{ $widget['key'] }}">
                            <span class="text-body-secondary" style="cursor: grab;" aria-hidden="true">≡</span>
                            <input class="form-check-input mt-0" type="checkbox" name="dashboard_widgets[]"
                                   value="{{ $widget['key'] }}" id="widget-{{ $widget['key'] }}"
                                   @checked(in_array($widget['key'], $selectedWidgets, true))>
                            <label class="form-check-label" for="widget-{{ $widget['key'] }}">{{ $widget['label'] }}</label>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="card card-body shadow-sm h-100">
                <h2 class="h5">Mapa</h2>
                <div class="mb-3">
                    <label for="map_default_zoom" class="form-label">Zoom inicial (1-18)</label>
                    <input id="map_default_zoom" name="map_default_zoom" type="range" min="1" max="18"
                           value="{{ $values['map_default_zoom'] }}" class="form-range"
                           oninput="document.getElementById('zoom-value').textContent = this.value;">
                    <span class="small text-body-secondary">Zoom: <span id="zoom-value">{{ $values['map_default_zoom'] }}</span></span>
                </div>
                <x-form.input name="map_default_lat" label="Latitud" :value="$values['map_default_lat']" inputmode="decimal" />
                <x-form.input name="map_default_lng" label="Longitud" :value="$values['map_default_lng']" inputmode="decimal" />
                <button type="button" id="use-location" class="btn btn-sm btn-outline-secondary">Usar mi ubicación actual</button>
            </section>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        (function () {
            var list = document.getElementById('widget-order');
            if (list !== null && typeof Sortable !== 'undefined') {
                new Sortable(list, {animation: 120, handle: 'span[aria-hidden="true"]'});
            }

            var locate = document.getElementById('use-location');
            if (locate !== null) {
                locate.addEventListener('click', function () {
                    if (!navigator.geolocation) { return; }
                    navigator.geolocation.getCurrentPosition(function (position) {
                        document.getElementById('map_default_lat').value = position.coords.latitude.toFixed(6);
                        document.getElementById('map_default_lng').value = position.coords.longitude.toFixed(6);
                    });
                });
            }
        })();
    </script>
@endpush
