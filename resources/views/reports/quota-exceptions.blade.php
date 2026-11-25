@extends('layouts.app')

@use('App\Support\Display')
@use('App\Support\Redact')

@section('title', 'Excepciones de cuota')

@section('content')
    @include('reports._header', ['title' => 'Excepciones de cuota, '.$month->locale('es')->translatedFormat('F Y'), 'datesApply' => false])

    <p class="small">
        Tarjetas bloqueadas, y tarjetas que han usado todo un límite mensual o más, en este mes de Beirut.
        El consumo proviene del contador mensual; los límites son los vigentes de cada tarjeta. Las tarjetas archivadas no se listan, y las compras rechazadas nunca cuentan como consumo.
    </p>

    @if ($rows === [])
        <p class="text-body-secondary">Ninguna tarjeta requiere atención este mes.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Tarjeta</th>
                        @if ($companies !== null)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col">Vehículo</th>
                        <th scope="col" class="text-end">Litros usados / límite</th>
                        <th scope="col" class="text-end">USD usados / límite</th>
                        <th scope="col">Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="font-monospace text-nowrap"><a href="{{ route('cards.show', $row['card_id']) }}">{{ Redact::cardNumber($row['card_no']) }}</a></td>
                            @if ($companies !== null)
                                <td>{{ $row['company'] }}</td>
                            @endif
                            <td>{{ $row['vehicle_plate'] ?? 'Sin vehículo' }}</td>
                            <td class="text-end text-nowrap">{{ Display::decimal($row['used_l']) }} / {{ $row['monthly_limit_l'] === null ? 'sin límite' : Display::decimal($row['monthly_limit_l']) }}</td>
                            <td class="text-end text-nowrap">{{ Display::decimal($row['used_usd']) }} / {{ $row['monthly_limit_usd'] === null ? 'sin límite' : Display::decimal($row['monthly_limit_usd']) }}</td>
                            <td>
                                <ul class="list-unstyled small mb-0">
                                    @foreach ($row['reasons'] as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
