@extends('layouts.app')

@use('App\Support\Display')

@section('title', 'Estimación de eficiencia')

@section('content')
    @include('reports._header', ['title' => 'Eficiencia de combustible (estimación)'])

    <p class="small">
        <strong>Una estimación.</strong> Kilómetros desde la carga anterior del vehículo, divididos entre los litros de esta carga. Asume que cada carga llena el tanque (lleno a lleno).
        Sin una carga anterior, una lectura de odómetro en ambas cargas, o una lectura mayor que la anterior, no se muestra ninguna cifra. Las lecturas son las registradas en la bomba, nunca el odómetro actual del vehículo.
    </p>

    @if ($rows === [])
        <p class="text-body-secondary">No hay cargas de vehículos en este periodo.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Vehículo</th>
                        @if ($companies !== null)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col">Compra</th>
                        <th scope="col">Hora (Beirut)</th>
                        <th scope="col" class="text-end">Litros</th>
                        <th scope="col" class="text-end">Odómetro (km)</th>
                        <th scope="col" class="text-end">Distancia (km)</th>
                        <th scope="col" class="text-end">km por litro</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['vehicle_plate'] }}</td>
                            @if ($companies !== null)
                                <td>{{ $row['company'] }}</td>
                            @endif
                            <td><a href="{{ route('transactions.show', $row['id']) }}">#{{ $row['id'] }}</a></td>
                            <td class="text-nowrap">{{ Display::businessTime($row['transacted_at']) }}</td>
                            <td class="text-end">{{ Display::decimal($row['liters']) }}</td>
                            <td class="text-end">{{ $row['odometer_km'] === null ? '—' : number_format($row['odometer_km']) }}</td>
                            <td class="text-end">{{ $row['distance_km'] === null ? '—' : number_format($row['distance_km']) }}</td>
                            <td class="text-end">
                                @if ($row['km_per_liter'] !== null)
                                    {{ $row['km_per_liter'] }}
                                @else
                                    <span class="small text-body-secondary">{{ $row['note'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
