@extends('layouts.app')

@use('App\Enums\RateMode')
@use('App\Enums\RateSource')
@use('App\Support\Display')

@section('title', 'Tasas de cambio')

@section('content')
    <h1 class="h3 mb-3">Tasas de cambio USD/LBP</h1>

    <p>
        @if ($mode === RateMode::Live)
            <span class="badge text-bg-primary">Modo en vivo</span>
            Las tasas provienen del proveedor externo mediante la sincronización diaria. Las tasas de fixture se ignoran.
        @else
            <span class="badge text-bg-secondary">Modo fixture</span>
            Tasas demo sintéticas con un valor ficticio; no se llama a ningún proveedor.
        @endif
        Los ajustes manuales aplican en ambos modos y tienen prioridad mientras sean válidos.
    </p>

    @if ($state?->last_error_code)
        <div class="alert alert-warning" role="status">
            <strong>Sincronización degradada.</strong> {{ $state->errorDescription() }}
            Las tasas almacenadas no cambian y siguen funcionando hasta expirar.
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Tasa vigente ahora</h2>
                    @if ($current)
                        <p class="display-6 font-monospace mb-2">{{ Display::decimal($current->rate, 8) }} <span class="fs-6">LBP por USD</span></p>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Origen</dt>
                            <dd class="col-sm-8">{{ $current->source->label() }}</dd>
                            <dt class="col-sm-4">Vigente</dt>
                            <dd class="col-sm-8">{{ Display::businessTime($current->effective_at) }} (hora de Beirut)</dd>
                            <dt class="col-sm-4">Expira</dt>
                            <dd class="col-sm-8">{{ Display::businessTime($current->expires_at) }} ({{ $current->expires_at->locale('es')->diffForHumans($now) }})</dd>
                            @if ($current->source === RateSource::Manual)
                                <dt class="col-sm-4">Motivo</dt>
                                <dd class="col-sm-8">{{ $current->reason }}</dd>
                            @endif
                        </dl>
                    @else
                        <div class="alert alert-danger mb-0" role="alert">
                            <strong>Sin tasa válida.</strong> Las compras POS nuevas se rechazan (<code>rate_unavailable</code>)
                            hasta que una sincronización tenga éxito o un administrador ingrese un ajuste. Las compras pasadas conservan sus montos almacenados.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Última sincronización <span class="small text-body-secondary">(modo {{ $mode->value }})</span></h2>
                    @if ($state?->last_attempt_at)
                        <dl class="row">
                            <dt class="col-sm-5">Último intento</dt>
                            <dd class="col-sm-7">{{ Display::businessTime($state->last_attempt_at) }}</dd>
                            <dt class="col-sm-5">Último éxito</dt>
                            <dd class="col-sm-7">{{ $state->last_success_at ? Display::businessTime($state->last_success_at) : 'Nunca' }}</dd>
                            <dt class="col-sm-5">Resultado</dt>
                            <dd class="col-sm-7">{{ $state->last_error_code ? 'Falló o degradado: '.$state->last_error_code : 'OK' }}</dd>
                            <dt class="col-sm-5">Próxima llamada al proveedor</dt>
                            <dd class="col-sm-7">{{ $state->next_attempt_at ? 'No antes de '.Display::businessTime($state->next_attempt_at) : 'En la próxima corrida' }}</dd>
                        </dl>
                    @else
                        <p>Todavía no ha corrido ninguna sincronización en este modo.</p>
                    @endif
                    <p class="small text-body-secondary mb-0">
                        El programador ejecuta <code>rates:sync</code> diariamente a las {{ config('fleetfuel.exchange_rates.sync_daily_at') }} UTC.
                        Para sincronizar ahora: <code>php artisan rates:sync</code>.
                        Las páginas nunca llaman al proveedor por sí mismas.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <form method="POST" action="{{ route('integrations.exchange-rates.overrides.store') }}" class="card card-body shadow-sm h-100">
                @csrf
                <h2 class="h5">Ingresar un ajuste manual</h2>
                <p class="small text-body-secondary">
                    Un ajuste no puede empezar en el pasado, dura como máximo {{ config('fleetfuel.exchange_rates.max_age_hours') }} horas
                    y no puede editarse después; un ajuste más reciente tiene prioridad. Nunca cambia compras pasadas.
                </p>
                <x-form.input name="rate" label="Tasa (LBP por USD)" required inputmode="decimal"
                              help="Hasta 8 decimales, por ejemplo 89500 o 89500.25." />
                <x-form.input name="reason" label="Motivo" required maxlength="255" />
                <x-form.input name="valid_for_hours" type="number" label="Válida por (horas)" required min="1"
                              :max="config('fleetfuel.exchange_rates.max_age_hours')" value="24" />
                <x-form.input name="starts_at" type="datetime-local" label="Inicia a las (hora de Beirut)"
                              help="Déjalo vacío para empezar ahora." />
                <div>
                    <button type="submit" class="btn btn-primary">Guardar ajuste</button>
                </div>
            </form>
        </div>

        <div class="col-lg-6">
            <h2 class="h5">Ajustes recientes</h2>
            @if ($overrides->isEmpty())
                <p class="text-body-secondary">Todavía no hay ajustes manuales.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle bg-body">
                        <thead>
                            <tr>
                                <th scope="col" class="text-end">LBP por USD</th>
                                <th scope="col">Válida (hora de Beirut)</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overrides as $override)
                                <tr>
                                    <td class="text-end font-monospace">{{ Display::decimal($override->rate, 8) }}</td>
                                    <td class="small">{{ Display::businessTime($override->effective_at) }} a {{ Display::businessTime($override->expires_at) }}</td>
                                    <td>@include('integrations.rate-status', ['rate' => $override])</td>
                                    <td class="small">{{ $override->reason }} <span class="text-body-secondary">({{ $override->creator?->name }})</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <h2 class="h5">Observaciones recientes de {{ strtolower($mode->source()->label()) }}</h2>
    @if ($observations->isEmpty())
        <p class="text-body-secondary">Ninguna almacenada todavía.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Observada (hora de Beirut)</th>
                        <th scope="col" class="text-end">LBP por USD</th>
                        <th scope="col">Expira</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($observations as $observation)
                        <tr>
                            <td>{{ Display::businessTime($observation->effective_at) }}</td>
                            <td class="text-end font-monospace">{{ Display::decimal($observation->rate, 8) }}</td>
                            <td>{{ Display::businessTime($observation->expires_at) }}</td>
                            <td>@include('integrations.rate-status', ['rate' => $observation])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($mode === RateMode::Live || $current?->source === RateSource::Provider)
        @include('partials.rate-attribution')
    @endif
@endsection
