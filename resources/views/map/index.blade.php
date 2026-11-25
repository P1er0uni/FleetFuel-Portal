@extends('layouts.app')

@section('title', 'Mapa de estaciones')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Mapa de estaciones</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('stations.index') }}">Lista de estaciones</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none mb-2" type="button"
                    data-bs-toggle="collapse" data-bs-target="#map-filters" aria-expanded="true" aria-controls="map-filters">
                Filtros
            </button>
            <div class="collapse show" id="map-filters">
                <form id="map-filter-form" class="card card-body shadow-sm" role="search" aria-label="Filtrar estaciones del mapa">
                    <div class="mb-3">
                        <label for="map-status" class="form-label small mb-1">Estado</label>
                        <select id="map-status" name="status" class="form-select form-select-sm">
                            <option value="all">Todas</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="map-product" class="form-label small mb-1">Producto</label>
                        <select id="map-product" name="product" class="form-select form-select-sm">
                            <option value="">Todos los productos</option>
                            @foreach ($products as $code => $label)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="map-q" class="form-label small mb-1">Buscar por nombre</label>
                        <input id="map-q" name="q" type="search" class="form-control form-control-sm" maxlength="100">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">Aplicar filtros</button>
                        <button type="button" id="map-locate" class="btn btn-sm btn-outline-secondary">Centrar en mi ubicación</button>
                    </div>
                </form>
            </div>

            @if ($withoutLocation->isNotEmpty())
                <div class="card card-body shadow-sm mt-3">
                    <h2 class="h6">Estaciones sin ubicación</h2>
                    <p class="small text-body-secondary mb-2">
                        No tienen coordenadas, así que no aparecen en el mapa. Añádelas editando cada estación.
                    </p>
                    <ul class="list-unstyled small mb-0">
                        @foreach ($withoutLocation as $station)
                            <li class="d-flex justify-content-between gap-2">
                                <span>{{ $station->name }}</span>
                                @if ($isAdmin)
                                    <a href="{{ route('stations.edit', $station) }}">Editar</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="col-lg-9">
            <div id="map-empty" class="alert alert-info d-none" role="status">
                No hay estaciones con ubicación configurada. Agrega coordenadas en la edición de cada estación.
            </div>
            <div id="station-map" class="border rounded shadow-sm"
                 style="height: calc(100vh - 220px); min-height: 480px;"
                 data-center='@json($mapCenter)'
                 data-url="{{ route('map.data') }}"
                 data-stations-url="{{ route('stations.index') }}"></div>
        </div>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        (function () {
            var el = document.getElementById('station-map');
            if (el === null || typeof L === 'undefined') {
                return;
            }

            var center = JSON.parse(el.getAttribute('data-center') || '{}');
            var url = el.getAttribute('data-url');
            var stationsUrl = el.getAttribute('data-stations-url');

            var map = L.map(el, {scrollWheelZoom: true}).setView(
                [center.lat || -12.0812, center.lng || -77.0333], center.zoom || 14
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            var cluster = L.markerClusterGroup();
            map.addLayer(cluster);

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function (c) {
                    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
                });
            }

            function color(status, transactions) {
                if (status === 'inactive') { return '#6c757d'; }
                return transactions > 0 ? '#198754' : '#0d6efd';
            }

            function pin(color) {
                return L.divIcon({
                    className: '',
                    html: '<svg width="26" height="38" viewBox="0 0 26 38" xmlns="http://www.w3.org/2000/svg">'
                        + '<path d="M13 0C5.8 0 0 5.8 0 13c0 9.8 13 25 13 25s13-15.2 13-25C26 5.8 20.2 0 13 0z" fill="' + color + '"/>'
                        + '<circle cx="13" cy="13" r="5" fill="#fff"/></svg>',
                    iconSize: [26, 38],
                    iconAnchor: [13, 38],
                    popupAnchor: [0, -34],
                });
            }

            function popup(station) {
                var products = station.products.length ? station.products.map(escapeHtml).join(', ') : '—';
                var last = station.last_transaction_at ? escapeHtml(station.last_transaction_at) : 'sin compras';
                return '<div class="small"><strong>' + escapeHtml(station.name) + '</strong><br>'
                    + escapeHtml(station.address || '') + '<br>'
                    + 'Estado: ' + escapeHtml(station.status) + '<br>'
                    + 'Productos: ' + products + '<br>'
                    + 'Compras hoy: ' + station.transactions_today + ' · Litros hoy: ' + escapeHtml(station.liters_today) + '<br>'
                    + 'Último tanqueo: ' + last + '<br>'
                    + '<a href="' + stationsUrl + '">Ver estaciones</a></div>';
            }

            var markers = [];

            function render(stations) {
                cluster.clearLayers();
                markers = [];
                stations.forEach(function (station) {
                    var marker = L.marker([station.latitude, station.longitude], {
                        icon: pin(color(station.status, station.transactions_today)),
                        title: station.name,
                    }).bindPopup(popup(station));
                    markers.push(marker);
                    cluster.addLayer(marker);
                });

                document.getElementById('map-empty').classList.toggle('d-none', stations.length > 0);

                if (markers.length > 0) {
                    map.fitBounds(cluster.getBounds().pad(0.15));
                }
            }

            function load() {
                var params = new URLSearchParams();
                var form = document.getElementById('map-filter-form');
                var data = new FormData(form);
                data.forEach(function (value, key) {
                    if (value !== '' && !(key === 'status' && value === 'all')) {
                        params.append(key, value);
                    }
                });

                fetch(url + (params.toString() ? ('?' + params.toString()) : ''), {
                    headers: {'Accept': 'application/json'}, credentials: 'same-origin'
                })
                    .then(function (response) { return response.ok ? response.json() : {stations: []}; })
                    .then(function (payload) { render(payload.stations || []); })
                    .catch(function () {});
            }

            document.getElementById('map-filter-form').addEventListener('submit', function (event) {
                event.preventDefault();
                load();
            });

            document.getElementById('map-locate').addEventListener('click', function () {
                if (!navigator.geolocation) { return; }
                navigator.geolocation.getCurrentPosition(function (position) {
                    map.setView([position.coords.latitude, position.coords.longitude], 13);
                });
            });

            load();
        })();
    </script>
@endpush
