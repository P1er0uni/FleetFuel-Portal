@extends('layouts.app')

@use('App\Enums\DeliveryStatus')
@use('App\Support\Display')

@section('title', 'Entregas de diésel')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Entregas de diésel</h1>
        @can('create', App\Models\DeliveryOrder::class)
            <a class="btn btn-primary" href="{{ route('deliveries.create') }}">Solicitar entrega</a>
        @endcan
    </div>

    @include('partials.list-filters', [
        'searchLabel' => 'Dirección, gobernación o camión',
        'statusOptions' => collect(DeliveryStatus::cases())->mapWithKeys(fn ($status) => [$status->value => ucfirst($status->displayLabel())])->all(),
        'companies' => $companies,
    ])

    <p class="small text-body-secondary">Los horarios son hora de Beirut. El diésel entregado no se cobra a ninguna tarjeta de combustible.</p>

    @if ($orders->isEmpty())
        <p class="text-body-secondary">Ninguna orden de entrega coincide con estos filtros.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Orden</th>
                        @if ($companies !== null)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col">Dirección</th>
                        <th scope="col" class="text-end">Litros</th>
                        <th scope="col">Ventana preferida</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Camión</th>
                        <th scope="col">Solicitada</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td><a href="{{ route('deliveries.show', $order) }}">#{{ $order->id }}</a></td>
                            @if ($companies !== null)
                                <td>{{ $order->company->name }}</td>
                            @endif
                            <td>
                                {{ $order->address }}
                                <span class="d-block small text-body-secondary">{{ $order->governorate }}</span>
                            </td>
                            <td class="text-end text-nowrap">{{ Display::decimal($order->liters) }}</td>
                            <td class="text-nowrap">{{ Display::businessTime($order->preferred_start_at) }} – {{ Display::businessTime($order->preferred_end_at) }}</td>
                            <td>@include('partials.delivery-status-badge', ['status' => $order->status])</td>
                            <td>{{ $order->assigned_truck ?? '—' }}</td>
                            <td class="text-nowrap">{{ Display::businessTime($order->created_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    @endif
@endsection
