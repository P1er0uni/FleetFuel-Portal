@extends('layouts.app')

@use('App\Enums\CardStatus')
@use('App\Support\Display')
@use('Carbon\CarbonImmutable')

@section('title', 'Tarjeta '.$card->card_no)

@section('content')
    @php
        $archived = $card->status === CardStatus::Archived;
        $canUpdate = auth()->user()->can('update', $card);
        $limitText = fn (?string $value) => $value === null ? 'Sin límite' : Display::decimal($value);
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
        <h1 class="h3 mb-0">Tarjeta <span class="font-monospace">{{ $card->card_no }}</span> @include('partials.card-status-badge', ['status' => $card->status])</h1>
        <span class="text-body-secondary">{{ $card->company->name }}</span>
    </div>

    @if ($balance->isOverQuota())
        <div class="alert alert-warning" role="status">
            Sobre cuota este mes: se bajó un límite por debajo de lo que la tarjeta ya había usado.
            Cualquier compra adicional este mes excedería el límite.
        </div>
    @endif
    @if ($archived)
        <div class="alert alert-secondary" role="status">Esta tarjeta está archivada. Ya no puede usarse ni modificarse; su historial se conserva.</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Asignación</h2>
                    <dl class="row mb-2">
                        <dt class="col-sm-5">Vehículo</dt>
                        <dd class="col-sm-7">{{ $card->vehicle ? $card->vehicle->plate_no.' ('.($card->vehicle->fuel_type->value === 'diesel' ? 'diésel' : 'gasolina').')' : 'Ninguno' }}</dd>
                        <dt class="col-sm-5">Conductor</dt>
                        <dd class="col-sm-7">{{ $card->driver?->name ?? 'Ninguno' }}</dd>
                        <dt class="col-sm-5">Restricción de producto</dt>
                        <dd class="col-sm-7">{{ $card->allowedProduct?->name ?? 'Cualquier producto que el vehículo pueda usar' }}</dd>
                    </dl>
                    @if ($used)
                        <p class="small text-body-secondary mb-0">Bloqueado: la tarjeta ya se usó.</p>
                    @elseif ($canUpdate && ! $archived)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('cards.edit', $card) }}">Cambiar asignación</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">{{ CarbonImmutable::parse($balance->month)->locale('es')->translatedFormat('F Y') }} <span class="small text-body-secondary">(hora de Beirut)</span></h2>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th scope="col"></th><th scope="col" class="text-end">Litros</th><th scope="col" class="text-end">USD</th></tr>
                        </thead>
                        <tbody>
                            <tr><th scope="row">Límite mensual</th><td class="text-end">{{ $limitText($balance->limitL) }}</td><td class="text-end">{{ $limitText($balance->limitUsd) }}</td></tr>
                            <tr><th scope="row">Usado</th><td class="text-end">{{ Display::decimal($balance->usedL) }}</td><td class="text-end">{{ Display::decimal($balance->usedUsd) }}</td></tr>
                            <tr><th scope="row">Restante</th><td class="text-end">{{ $limitText($balance->remainingL()) }}</td><td class="text-end">{{ $limitText($balance->remainingUsd()) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($canUpdate && ! $archived)
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <form method="POST" action="{{ route('cards.limits', $card) }}" class="card card-body shadow-sm h-100">
                    @csrf
                    @method('PATCH')
                    <h2 class="h5">Límites mensuales</h2>

                    @error('confirm_below_usage')
                        <div class="alert alert-warning" role="alert">
                            {{ $message }}
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="confirm_below_usage" value="1" id="confirm_below_usage">
                                <label class="form-check-label" for="confirm_below_usage">Entiendo que la tarjeta quedará sobre cuota.</label>
                            </div>
                        </div>
                    @enderror

                    <div class="row">
                        <div class="col-md-6">
                            <x-form.input name="monthly_limit_l" label="Litros" :value="$card->monthly_limit_l" inputmode="decimal" />
                        </div>
                        <div class="col-md-6">
                            <x-form.input name="monthly_limit_usd" label="USD" :value="$card->monthly_limit_usd" inputmode="decimal" />
                        </div>
                    </div>
                    <p class="form-text mt-0">Déjalo vacío para no poner límite; 0 no permite más compras. Los cambios quedan en auditoría.</p>
                    <div><button type="submit" class="btn btn-primary">Guardar límites</button></div>
                </form>
            </div>

            <div class="col-lg-6">
                <div class="card card-body shadow-sm h-100">
                    <h2 class="h5">Estado</h2>
                    <p class="small text-body-secondary">El bloqueo surte efecto en todas las estaciones a la vez. Archivar es definitivo.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('cards.status', $card) }}">
                            @csrf
                            @method('PATCH')
                            @if ($card->status === CardStatus::Active)
                                <input type="hidden" name="status" value="blocked">
                                <button type="submit" class="btn btn-warning">Bloquear tarjeta</button>
                            @else
                                <input type="hidden" name="status" value="active">
                                <button type="submit" class="btn btn-outline-success">Desbloquear tarjeta</button>
                            @endif
                        </form>
                        <form method="POST" action="{{ route('cards.status', $card) }}"
                              data-confirm="¿Archivar la tarjeta {{ $card->card_no }}? Esto no se puede deshacer.">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="archived">
                            <button type="submit" class="btn btn-outline-danger">Archivar tarjeta</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <h2 class="h5">Compras recientes</h2>
    @include('transactions._table', ['transactions' => $recent, 'showCompany' => false, 'showStation' => true])

    @if ($audit !== null)
        <h2 class="h5 mt-4">Historial de auditoría</h2>
        @if ($audit->isEmpty())
            <p class="text-body-secondary">Sin cambios registrados.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle bg-body">
                    <thead>
                        <tr>
                            <th scope="col">Hora (Beirut)</th>
                            <th scope="col">Cambio</th>
                            <th scope="col">Por</th>
                            <th scope="col">Antes</th>
                            <th scope="col">Después</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audit as $entry)
                            <tr>
                                <td class="text-nowrap">{{ $entry->created_at ? Display::businessTime($entry->created_at) : '' }}</td>
                                <td class="font-monospace small">{{ $entry->action }}</td>
                                <td>{{ $entry->user?->name ?? 'Línea de comandos / sistema' }}</td>
                                <td class="font-monospace small">{{ $entry->old_values ? json_encode($entry->old_values) : '—' }}</td>
                                <td class="font-monospace small">{{ $entry->new_values ? json_encode($entry->new_values) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection
