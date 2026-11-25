@extends('layouts.app')

@use('App\Support\Display')
@use('App\Support\FuelAmounts')

@section('title', 'Productos')

@section('content')
    @php($admin = auth()->user()->isAdmin())

    <h1 class="h3 mb-3">{{ $admin ? 'Productos' : 'Productos activos' }}</h1>

    @if ($products->isEmpty())
        <p class="text-body-secondary">Todavía no hay productos.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Combustible</th>
                        <th scope="col" class="text-end">Precio vigente<br><span class="fw-normal small">LBP por litro</span></th>
                        <th scope="col" class="text-end">Indicativo<br><span class="fw-normal small">USD por litro</span></th>
                        @if ($admin)
                            <th scope="col">Estado</th>
                        @endif
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        @php($price = $currentPrices[$product->id])
                        <tr>
                            <td class="font-monospace">{{ $product->code }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->fuel_type->value === 'diesel' ? 'Diésel' : 'Gasolina' }}</td>
                            <td class="text-end font-monospace">{{ $price ? Display::decimal($price->price_lbp, 4) : 'Sin precio aún' }}</td>
                            <td class="text-end font-monospace">{{ $price && $rate ? FuelAmounts::indicativeUnitPriceUsd($price->price_lbp, $rate->rate) : '—' }}</td>
                            @if ($admin)
                                <td>@include('partials.active-badge', ['active' => $product->is_active])</td>
                            @endif
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('products.prices.index', $product) }}">Precios<span class="visually-hidden"> de {{ $product->code }}</span></a>
                                @if ($admin)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('products.edit', $product) }}">Editar<span class="visually-hidden"> {{ $product->code }}</span></a>
                                    @include('partials.active-toggle', ['action' => route('products.active', $product), 'active' => $product->is_active, 'name' => $product->code])
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @include('partials.indicative-rate-note', ['rate' => $rate])
    @endif
@endsection
