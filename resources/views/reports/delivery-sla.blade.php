@extends('layouts.app')

@section('title', 'SLA de entregas')

@section('content')
    @include('reports._header', ['title' => 'Tiempo de entrega por gobernación'])

    <p class="small">
        Horas desde la solicitud (la primera entrada de la cronología de la orden) hasta la entrega (su entrada "entregada"), para órdenes entregadas en este periodo.
        Las órdenes pendientes, en curso y canceladas no se incluyen.
    </p>

    @if ($rows === [])
        <p class="text-body-secondary">No se completó ninguna entrega en este periodo.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Gobernación</th>
                        <th scope="col" class="text-end">Entregadas</th>
                        <th scope="col" class="text-end">Horas promedio</th>
                        <th scope="col" class="text-end">Más rápida</th>
                        <th scope="col" class="text-end">Más lenta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['governorate'] }}</td>
                            <td class="text-end">{{ $row['delivered'] }}</td>
                            <td class="text-end">{{ $row['average_hours'] }}</td>
                            <td class="text-end">{{ $row['fastest_hours'] }}</td>
                            <td class="text-end">{{ $row['slowest_hours'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <th scope="row">Todas las gobernaciones</th>
                        <td class="text-end">{{ $delivered }}</td>
                        <td class="text-end">{{ $averageHours }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
@endsection
