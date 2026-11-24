@extends('layouts.app')

@use('App\Support\Display')
@use('Brick\Math\BigDecimal')

@section('title', 'Anomalías')

@section('content')
    @include('reports._header', ['title' => 'Anomalías'])

    <h3 class="h6">Más de lo que cabe en el tanque</h3>
    <p class="small">Compras con más litros que la capacidad de tanque del vehículo registrada al momento de la compra. Las compras sin vehículo no se comparan.</p>
    @if ($overfills === [])
        <p class="text-body-secondary">Ninguna en este periodo.</p>
    @else
        <div class="table-responsive mb-4">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Compra</th>
                        <th scope="col">Hora (Beirut)</th>
                        @if ($companies !== null)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col">Vehículo</th>
                        <th scope="col">Estación</th>
                        <th scope="col" class="text-end">Litros</th>
                        <th scope="col" class="text-end">Tanque</th>
                        <th scope="col" class="text-end">Exceso</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($overfills as $row)
                        <tr>
                            <td><a href="{{ route('transactions.show', $row['id']) }}">#{{ $row['id'] }}</a></td>
                            <td class="text-nowrap">{{ Display::businessTime($row['transacted_at']) }}</td>
                            @if ($companies !== null)
                                <td>{{ $row['company'] }}</td>
                            @endif
                            <td>{{ $row['vehicle_plate'] }}</td>
                            <td>{{ $row['station'] }}</td>
                            <td class="text-end">{{ Display::decimal($row['liters']) }}</td>
                            <td class="text-end">{{ Display::decimal($row['tank_capacity_l']) }}</td>
                            <td class="text-end">{{ Display::decimal((string) BigDecimal::of($row['liters'])->minus($row['tank_capacity_l'])) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h3 class="h6">Cargas rápidas</h3>
    <p class="small">Cargas a menos de 30 minutos de la carga anterior del mismo vehículo, incluyendo una carga anterior justo antes de este periodo. Dos cargas en el mismo segundo cuentan, ordenadas por número de compra. Las compras solo con tarjeta no tienen vehículo y nunca se marcan.</p>
    @if ($rapidFills === [])
        <p class="text-body-secondary">Ninguna en este periodo.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Compra</th>
                        <th scope="col">Hora (Beirut)</th>
                        @if ($companies !== null)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col">Vehículo</th>
                        <th scope="col">Estación</th>
                        <th scope="col" class="text-end">Litros</th>
                        <th scope="col">Carga anterior</th>
                        <th scope="col" class="text-end">Minutos de diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rapidFills as $row)
                        <tr>
                            <td><a href="{{ route('transactions.show', $row['id']) }}">#{{ $row['id'] }}</a></td>
                            <td class="text-nowrap">{{ Display::businessTime($row['transacted_at']) }}</td>
                            @if ($companies !== null)
                                <td>{{ $row['company'] }}</td>
                            @endif
                            <td>{{ $row['vehicle_plate'] }}</td>
                            <td>{{ $row['station'] }}</td>
                            <td class="text-end">{{ Display::decimal($row['liters']) }}</td>
                            <td class="text-nowrap"><a href="{{ route('transactions.show', $row['previous_id']) }}">#{{ $row['previous_id'] }}</a> a las {{ Display::businessTime($row['previous_at']) }}</td>
                            <td class="text-end">{{ intdiv($row['seconds_since_previous'], 60) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
