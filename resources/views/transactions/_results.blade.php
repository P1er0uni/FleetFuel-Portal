@use('App\Support\Display')

{{--
    Totals, rows and pages of the purchase list. Rendered on the full page and,
    for page scripts, by TransactionController::results(). Expects $purchases
    (paginator), $totals, $summary, $firstDay, $lastDay, $csvUrl (null when the
    user may not export), $showCompany and $showStation.
--}}
<section class="mb-3" aria-labelledby="transaction-totals-heading" data-transaction-totals>
    <h2 id="transaction-totals-heading" class="h6 mb-2">
        {{ $totals['purchases'] === 1 ? 'Totales de la única compra que coincide' : 'Totales de las '.number_format($totals['purchases']).' compras que coinciden' }},
        {{ $firstDay }} a {{ $lastDay }}
        <span class="fw-normal text-body-secondary">(todo el filtro, no solo esta página)</span>
    </h2>
    <div class="row row-cols-2 row-cols-md-4 g-2">
        @foreach ([
            'purchases' => ['Compras', number_format($totals['purchases'])],
            'liters' => ['Litros', Display::decimal($totals['liters'])],
            'amount_usd' => ['Monto (USD)', Display::decimal($totals['amount_usd'])],
            'amount_lbp' => ['Monto (LBP)', Display::decimal($totals['amount_lbp'])],
        ] as $key => [$label, $shown])
            <div class="col">
                <div class="card stat-card h-100 shadow-sm">
                    <div class="card-body py-2">
                        <div class="small text-body-secondary">{{ $label }}</div>
                        <div class="stat-value" data-total="{{ $key }}" data-value="{{ $totals[$key] }}">{{ $shown }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if ($csvUrl !== null && $totals['purchases'] > 0)
        <a class="btn btn-sm btn-outline-primary mt-2" href="{{ $csvUrl }}" data-csv-link>
            Descargar CSV<span class="visually-hidden"> de estas {{ $totals['purchases'] }} compras</span>
        </a>
        <span class="small text-body-secondary ms-2">Mismos filtros, todas las filas, montos almacenados.</span>
    @endif
</section>

@if ($totals['purchases'] === 0)
    <div class="alert alert-light border">
        <p class="fw-semibold mb-1">Ninguna compra coincide con estos filtros.</p>
        <p class="small mb-0">Prueba con un rango de fechas más amplio, o quita el filtro de tarjeta, estación o producto. Los números de tarjeta deben ser completos.</p>
    </div>
@elseif ($purchases->isEmpty())
    <div class="alert alert-light border">
        <p class="mb-0">Esta página está más allá de la última página de resultados. <a href="{{ $purchases->url(1) }}">Ir a la primera página</a>.</p>
    </div>
@else
    <p class="small text-body-secondary mb-1">{{ $summary }}</p>
    @include('transactions._table', [
        'transactions' => $purchases,
        'showCompany' => $showCompany,
        'showStation' => $showStation,
        'showRateSource' => true,
    ])
    {{ $purchases->onEachSide(1)->links() }}
@endif
