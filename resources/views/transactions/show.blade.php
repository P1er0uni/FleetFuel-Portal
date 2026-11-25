@extends('layouts.app')

@use('App\Support\Display')
@use('App\Support\Redact')

@section('title', 'Compra '.$transaction->id)

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
        <h1 class="h3 mb-0">Compra {{ $transaction->id }}</h1>
        <span class="text-body-secondary">Referencia POS <span class="font-monospace">{{ $transaction->external_ref }}</span></span>
    </div>

    @if ($transaction->exceedsTankCapacity())
        <div class="alert alert-warning" role="alert">
            Anomalía: {{ Display::decimal($transaction->liters) }} L superan la capacidad de tanque
            registrada del vehículo, de {{ Display::decimal($transaction->tank_capacity_l) }} L.
        </div>
    @endif

    <p class="text-body-secondary">
        Las compras aceptadas nunca se editan. Los montos de abajo quedaron fijados con el precio y la
        tasa de cambio que aplicaban al momento de la compra.
    </p>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 text-body-secondary">Quién y dónde</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Empresa</dt>
                        <dd class="col-sm-7">{{ $transaction->company->name }}</dd>
                        <dt class="col-sm-5">Estación</dt>
                        <dd class="col-sm-7">{{ $transaction->station->name }}</dd>
                        <dt class="col-sm-5">Tarjeta</dt>
                        <dd class="col-sm-7 font-monospace">{{ Redact::cardNumber($transaction->fuelCard->card_no) }}</dd>
                        <dt class="col-sm-5">Vehículo</dt>
                        <dd class="col-sm-7">{{ $transaction->vehicle?->plate_no ?? 'Ninguno' }}</dd>
                        <dt class="col-sm-5">Conductor</dt>
                        <dd class="col-sm-7">{{ $transaction->driver?->name ?? 'Ninguno' }}</dd>
                        <dt class="col-sm-5">Odómetro</dt>
                        <dd class="col-sm-7">{{ $transaction->odometer_km !== null ? number_format($transaction->odometer_km).' km' : 'No registrado' }}</dd>
                        <dt class="col-sm-5">Comprado el (Beirut)</dt>
                        <dd class="col-sm-7">{{ Display::businessTime($transaction->transacted_at) }}</dd>
                        <dt class="col-sm-5">Recibido el (Beirut)</dt>
                        <dd class="col-sm-7">{{ $transaction->created_at ? Display::businessTime($transaction->created_at) : 'Desconocido' }}</dd>
                        <dt class="col-sm-5">Mes de cuota</dt>
                        <dd class="col-sm-7 mb-0">{{ $transaction->quota_month->locale('es')->translatedFormat('F Y') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 text-body-secondary">Combustible, precio y tasa de cambio</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Producto</dt>
                        <dd class="col-sm-7">{{ $transaction->product->name }}</dd>
                        <dt class="col-sm-5">Litros</dt>
                        <dd class="col-sm-7">{{ Display::decimal($transaction->liters) }}</dd>
                        <dt class="col-sm-5">Precio unitario</dt>
                        <dd class="col-sm-7">{{ Display::decimal($transaction->unit_price_lbp, 4) }} LBP por litro</dd>
                        <dt class="col-sm-5">Monto (LBP)</dt>
                        <dd class="col-sm-7">{{ Display::decimal($transaction->amount_lbp) }}</dd>
                        <dt class="col-sm-5">Tasa de cambio</dt>
                        <dd class="col-sm-7">{{ Display::decimal($transaction->rate_lbp_per_usd, 8) }} LBP por USD</dd>
                        <dt class="col-sm-5">Origen de la tasa</dt>
                        <dd class="col-sm-7">
                            @include('partials.rate-source-badge', ['source' => $transaction->rate_source])
                            <span class="d-block small">vigente desde {{ Display::businessTime($transaction->rate_effective_at) }} (hora de Beirut)</span>
                        </dd>
                        <dt class="col-sm-5">Monto (USD)</dt>
                        <dd class="col-sm-7 mb-0 fw-semibold">{{ Display::decimal($transaction->amount_usd) }}</dd>
                    </dl>
                    @if ($transaction->rate_source === App\Enums\RateSource::Provider)
                        @include('partials.rate-attribution')
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
