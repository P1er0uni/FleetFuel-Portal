@extends('layouts.app')

@use('App\Support\CardBalance')
@use('App\Support\Display')
@use('App\Support\Redact')
@use('Carbon\CarbonImmutable')

@section('title', 'Tarjetas de combustible')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Tarjetas de combustible</h1>
        @can('create', App\Models\FuelCard::class)
            <a class="btn btn-primary" href="{{ route('cards.create') }}">Emitir tarjeta</a>
        @endcan
    </div>

    @include('partials.list-filters', [
        'searchLabel' => 'Número de tarjeta (o parte de él)',
        'statusOptions' => ['active' => 'Activa', 'blocked' => 'Bloqueada', 'archived' => 'Archivada'],
        'companies' => $companies,
    ])

    <p class="small text-body-secondary">
        Consumo de {{ CarbonImmutable::parse($month)->locale('es')->translatedFormat('F Y') }} (hora de Beirut) contra los límites mensuales vigentes de cada tarjeta.
    </p>

    @if ($cards->isEmpty())
        <p class="text-body-secondary">Ninguna tarjeta coincide con estos filtros.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Tarjeta</th>
                        @if ($companies !== null)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col">Vehículo / conductor</th>
                        <th scope="col">Producto</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-end">Litros usados / límite</th>
                        <th scope="col" class="text-end">USD usados / límite</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cards as $card)
                        @php($balance = CardBalance::for($card, $card->monthlyUsages->first(), $month))
                        <tr>
                            <td class="font-monospace"><a href="{{ route('cards.show', $card) }}">{{ Redact::cardNumber($card->card_no) }}</a></td>
                            @if ($companies !== null)
                                <td>{{ $card->company->name }}</td>
                            @endif
                            <td>
                                {{ $card->vehicle?->plate_no ?? 'Sin vehículo' }}
                                <span class="d-block small text-body-secondary">{{ $card->driver?->name ?? 'Sin conductor' }}</span>
                            </td>
                            <td>{{ $card->allowedProduct?->name ?? 'Cualquiera' }}</td>
                            <td>
                                @include('partials.card-status-badge', ['status' => $card->status])
                                @if ($balance->isOverQuota())
                                    <span class="badge text-bg-warning">Sobre cuota</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">{{ Display::decimal($balance->usedL) }} / {{ $balance->limitL === null ? 'sin límite' : Display::decimal($balance->limitL) }}</td>
                            <td class="text-end text-nowrap">{{ Display::decimal($balance->usedUsd) }} / {{ $balance->limitUsd === null ? 'sin límite' : Display::decimal($balance->limitUsd) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $cards->links() }}
    @endif
@endsection
