@extends('layouts.app')

@use('App\Enums\RateSource')
@use('App\Support\Display')
@use('App\Support\Redact')

@section('title', 'Panel')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
        <h1 class="h3 mb-0">Panel</h1>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="text-body-secondary">
                {{ $user->isAdmin() ? 'Todas las empresas' : $user->company?->name }}
                · {{ $monthStart->locale('es')->translatedFormat('F Y') }} (hora de Beirut)
            </span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('map.index') }}">Mapa de estaciones</a>
        </div>
    </div>

    <section class="card shadow-sm mb-4" aria-labelledby="favorites-heading">
        <div class="card-body">
            <h2 id="favorites-heading" class="h5">Favoritos</h2>
            @if (empty($favorites))
                <p class="text-body-secondary mb-0">
                    Marca flotas o vehículos con la estrella para abrirlos rápido desde aquí.
                </p>
            @else
                <ul id="favorites-list" class="list-unstyled d-flex flex-wrap gap-2 mb-0">
                    @foreach ($favorites as $favorite)
                        <li class="border rounded px-2 py-1 bg-body d-flex align-items-center gap-2" data-id="{{ $favorite['id'] }}">
                            <span class="text-body-secondary" style="cursor: grab;" aria-hidden="true">≡</span>
                            <a href="{{ $favorite['url'] }}" class="text-decoration-none">{{ $favorite['label'] }}</a>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 favorite-remove"
                                    data-id="{{ $favorite['id'] }}" data-url="{{ route('favorites.destroy', $favorite['id']) }}"
                                    aria-label="Quitar {{ $favorite['label'] }} de favoritos">×</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
        <script>
            (function () {
                var list = document.getElementById('favorites-list');
                if (list === null) { return; }

                var token = document.querySelector('meta[name="csrf-token"]');
                token = token ? token.getAttribute('content') : '';

                if (typeof Sortable !== 'undefined') {
                    new Sortable(list, {
                        animation: 120,
                        handle: 'span[aria-hidden="true"]',
                        onEnd: function () {
                            var ids = Array.prototype.map.call(
                                list.querySelectorAll('[data-id]'),
                                function (el) { return parseInt(el.getAttribute('data-id'), 10); }
                            );
                            fetch('{{ route('favorites.reorder') }}', {
                                method: 'PUT',
                                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                                body: JSON.stringify({ids: ids}),
                                credentials: 'same-origin',
                            }).catch(function () {});
                        }
                    });
                }

                list.addEventListener('click', function (event) {
                    var button = event.target.closest('.favorite-remove');
                    if (button === null) { return; }
                    event.preventDefault();
                    fetch(button.getAttribute('data-url'), {
                        method: 'DELETE',
                        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                        credentials: 'same-origin',
                    }).then(function (response) {
                        if (response.ok) {
                            var item = button.closest('li');
                            if (item !== null) { item.remove(); }
                        }
                    }).catch(function () {});
                });
            })();
        </script>
    @endpush

    @if (in_array('summary', $widgets))
    <section aria-labelledby="month-heading" class="mb-4">
        <h2 id="month-heading" class="visually-hidden">Este mes</h2>
        <div class="row row-cols-2 row-cols-xl-4 g-2 g-md-3">
            @foreach ([
                'purchases' => ['Compras este mes', fn ($totals) => number_format($totals['purchases'])],
                'liters' => ['Litros este mes', fn ($totals) => Display::decimal($totals['liters'])],
                'amount_usd' => ['Gasto este mes (USD)', fn ($totals) => Display::decimal($totals['amount_usd'])],
                'amount_lbp' => ['Gasto este mes (LBP)', fn ($totals) => Display::decimal($totals['amount_lbp'])],
            ] as $key => [$label, $format])
                <div class="col">
                    <div class="card stat-card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="small text-body-secondary">{{ $label }}</div>
                            <div class="stat-value" data-month-total="{{ $key }}">{{ $format($current) }}</div>
                            <div class="small text-body-secondary">{{ $previousMonthStart->locale('es')->translatedFormat('F') }}: {{ $format($previous) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="small text-body-secondary mt-2 mb-0">
            Los montos son los LBP y USD guardados con cada compra, a su propio precio y tasa de cambio.
            <a href="{{ route('transactions.index') }}">Abre la lista de transacciones</a> para cualquier periodo.
        </p>
    </section>
    @endif

    <div class="row g-4 mb-4">
        @if (in_array('quota_alerts', $widgets))
        <div class="col-lg-7">
            <section class="card h-100 shadow-sm" aria-labelledby="quota-heading">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                        <h2 id="quota-heading" class="h5 mb-0">Alertas de cuota</h2>
                        @if ($quotaWarnings !== [])
                            <span class="badge text-bg-warning">{{ count($quotaWarnings) }} {{ count($quotaWarnings) === 1 ? 'tarjeta requiere' : 'tarjetas requieren' }} atención</span>
                        @endif
                    </div>
                    @if ($quotaWarnings === [])
                        <p class="text-body-secondary mb-0">Ninguna tarjeta está bloqueada o fuera de cuota este mes.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach (array_slice($quotaWarnings, 0, 5) as $warning)
                                <li class="list-group-item px-0">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <span>
                                            <a class="font-monospace" href="{{ route('cards.show', $warning['card_id']) }}">{{ Redact::cardNumber($warning['card_no']) }}</a>
                                            <span class="text-body-secondary">· {{ $warning['vehicle_plate'] ?? 'Sin vehículo' }}</span>
                                            @if ($user->isAdmin())
                                                <span class="text-body-secondary">· {{ $warning['company'] }}</span>
                                            @endif
                                        </span>
                                        @if ($warning['status'] === 'blocked')
                                            <span class="badge text-bg-danger">Bloqueada</span>
                                        @else
                                            <span class="badge text-bg-warning">Fuera de cuota</span>
                                        @endif
                                    </div>
                                    <div class="small">{{ implode(' ', $warning['reasons']) }}</div>
                                </li>
                            @endforeach
                        </ul>
                        <a class="small" href="{{ route('reports.quota-exceptions') }}">
                            {{ count($quotaWarnings) > 5 ? 'Ver todas las '.count($quotaWarnings).' en el reporte de excepciones de cuota' : 'Abrir el reporte de excepciones de cuota' }}
                        </a>
                    @endif
                </div>
            </section>
        </div>
        @endif

        @if (in_array('open_deliveries', $widgets))
        <div class="col-lg-5">
            <section class="card h-100 shadow-sm" aria-labelledby="deliveries-heading">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                        <h2 id="deliveries-heading" class="h5 mb-0">Entregas abiertas</h2>
                        <span class="text-body-secondary small">{{ $openDeliveryCount }} abiertas</span>
                    </div>
                    @if ($openDeliveries->isEmpty())
                        <p class="text-body-secondary">Ninguna entrega está pendiente, programada o en camino.</p>
                    @else
                        <ul class="list-group list-group-flush mb-2">
                            @foreach ($openDeliveries as $order)
                                <li class="list-group-item px-0">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <a href="{{ route('deliveries.show', $order) }}">Order #{{ $order->id }}</a>
                                        @include('partials.delivery-status-badge', ['status' => $order->status])
                                    </div>
                                    <div class="small text-body-secondary">
                                        @if ($user->isAdmin()){{ $order->company->name }} · @endif{{ $order->governorate }} · {{ Display::decimal($order->liters) }} L
                                        · {{ $order->scheduled_start_at ? 'programada '.Display::businessTime($order->scheduled_start_at) : 'preferida desde '.Display::businessTime($order->preferred_start_at) }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <a class="small" href="{{ route('deliveries.index') }}">Todas las entregas</a>
                    @can('create', App\Models\DeliveryOrder::class)
                        · <a class="small" href="{{ route('deliveries.create') }}">Solicitar una entrega</a>
                    @endcan
                </div>
            </section>
        </div>
        @endif
    </div>

    @if (in_array('fleet_stats', $widgets))
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <section class="card h-100 shadow-sm" aria-labelledby="fleet-heading">
                <div class="card-body">
                    <h2 id="fleet-heading" class="h5">Flota</h2>
                    <dl class="row row-cols-2 row-cols-md-3 g-2 mb-0">
                        @foreach (array_filter([
                            'Empresas' => $companies,
                            'Vehículos activos' => $vehicles,
                            'Conductores activos' => $drivers,
                            'Tarjetas activas' => $activeCards,
                            'Tarjetas bloqueadas' => $blockedCards,
                        ], fn ($value) => $value !== null) as $label => $value)
                            <div class="col">
                                <dt class="small fw-normal text-body-secondary">{{ $label }}</dt>
                                <dd class="fs-5 fw-semibold mb-0">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="card h-100 shadow-sm" aria-labelledby="rate-heading">
                <div class="card-body">
                    <h2 id="rate-heading" class="h5">Tasa USD/LBP en uso</h2>
                    @if ($rate)
                        <p class="fs-5 fw-semibold mb-1">{{ Display::decimal($rate->rate, 8) }} LBP por USD</p>
                        <p class="mb-1">@include('partials.rate-source-badge', ['source' => $rate->source])</p>
                        <p class="small text-body-secondary mb-1">
                            Vigente desde {{ Display::businessTime($rate->effective_at) }}, válida hasta {{ Display::businessTime($rate->expires_at) }} (hora de Beirut).
                            Las compras nuevas guardan esta tasa; las anteriores conservan la suya.
                        </p>
                        @if ($rate->source === RateSource::Provider)
                            @include('partials.rate-attribution')
                        @endif
                    @else
                        <div class="alert alert-warning mb-1" role="status">
                            Ahora mismo no hay una tasa USD/LBP válida. Las compras POS se rechazan hasta que haya una tasa disponible (nunca se convierte con una tasa adivinada).
                        </div>
                    @endif
                    @can('viewAny', App\Models\ExchangeRate::class)
                        <a class="small" href="{{ route('integrations.exchange-rates') }}">Estado de tasas de cambio y ajustes</a>
                    @endcan
                </div>
            </section>
        </div>
    </div>
    @endif

    @if (in_array('fleet_consumption', $widgets))
    <section class="card shadow-sm mb-4" aria-labelledby="fleet-usage-heading">
        <div class="card-body">
            <h2 id="fleet-usage-heading" class="h5">Consumo por flota</h2>
            @if ($fleets->isEmpty())
                <p class="small text-body-secondary mb-0">
                    Todavía no hay flotas. <a href="{{ route('fleets.index') }}">Gestionar flotas</a>.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="small text-body-secondary">
                            Litros de {{ $monthStart->locale('es')->translatedFormat('F Y') }} (hora de Beirut) contra la cuota de cada flota.
                            Pasada la cuota es una señal: nunca bloquea una compra.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Flota</th>
                                <th scope="col" class="text-end">Litros del mes</th>
                                <th scope="col">Cuota de litros</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fleets as $fleet)
                                @php($quota = $fleetQuotas[$fleet->id])
                                <tr>
                                    <td><a href="{{ route('fleets.show', $fleet) }}">{{ $fleet->name }}</a></td>
                                    <td class="text-end">{{ Display::decimal($quota->usedL) }}</td>
                                    <td>
                                        @if ($quota->quotaL !== null)
                                            @php($percent = min(100, (int) round((float) $quota->litersPercent())))
                                            <div class="progress mb-1" style="height: 6px;" role="progressbar"
                                 aria-label="Porcentaje de la cuota de litros de {{ $fleet->name }}"
                                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
                                                <div class="progress-bar @if ($quota->litersExceeded()) bg-warning @else bg-success @endif" style="width: {{ $percent }}%"></div>
                                            </div>
                                            <small class="text-body-secondary">{{ $quota->litersPercent() }} % de {{ Display::decimal($quota->quotaL) }} L</small>
                                        @else
                                            <small class="text-body-secondary">Sin cuota de litros</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($quota->exceeded())
                                            <span class="badge text-bg-warning">Cuota excedida</span>
                                        @else
                                            <span class="badge text-bg-success">En orden</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
    @endif

    @if (in_array('recent_transactions', $widgets))
    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
        <h2 class="h5">Compras recientes</h2>
        <a class="small" href="{{ route('transactions.index') }}">Todas las transacciones</a>
    </div>
    @include('transactions._table', [
        'transactions' => $recent,
        'showCompany' => $user->isAdmin(),
        'showStation' => true,
        'showRateSource' => true,
    ])
    @endif
@endsection
