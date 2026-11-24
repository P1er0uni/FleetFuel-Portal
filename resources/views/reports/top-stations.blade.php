@extends('layouts.app')

@use('App\Support\Display')

@section('title', 'Top estaciones')

@section('content')
    @include('reports._header', ['title' => 'Top estaciones por litros'])

    <p class="small">Como máximo diez estaciones, más litros primero; los totales iguales se ordenan por número de estación.</p>

    @if ($rows === [])
        <p class="text-body-secondary">No hay compras en este periodo.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Estación</th>
                        <th scope="col">Gobernación</th>
                        <th scope="col" class="text-end">Compras</th>
                        <th scope="col" class="text-end">Litros</th>
                        <th scope="col" class="text-end">LBP</th>
                        <th scope="col" class="text-end">USD</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $rank => $row)
                        <tr>
                            <td>{{ $rank + 1 }}</td>
                            <td>{{ $row['station'] }}</td>
                            <td>{{ $row['governorate'] }}</td>
                            <td class="text-end">{{ $row['purchases'] }}</td>
                            <td class="text-end text-nowrap">{{ Display::decimal($row['liters']) }}</td>
                            <td class="text-end text-nowrap">{{ Display::decimal($row['amount_lbp']) }}</td>
                            <td class="text-end text-nowrap">{{ Display::decimal($row['amount_usd']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
