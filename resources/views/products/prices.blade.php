@extends('layouts.app')

@use('App\Support\Display')
@use('App\Support\FuelAmounts')

@section('title', 'Precios de '.$product->code)

@section('content')
    @php($admin = auth()->user()->isAdmin())

    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
        <h1 class="h3 mb-0">Precios de <span class="font-monospace">{{ $product->code }}</span></h1>
        <a href="{{ route('products.index') }}">Todos los productos</a>
    </div>

    <p class="text-body-secondary">
        {{ $product->name }}. Los precios están en <strong>LBP por litro</strong>. Los precios publicados nunca cambian:
        una compra conserva el precio que aplicaba al momento de realizarse, y una corrección es un precio nuevo, posterior.
    </p>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Precio vigente</h2>
                    @if ($current)
                        <p class="display-6 font-monospace mb-1">{{ Display::decimal($current->price_lbp, 4) }} <span class="fs-6">LBP por litro</span></p>
                        <p class="mb-2">
                            Desde {{ Display::businessTime($current->effective_from) }} (hora de Beirut)
                            @if ($rate)
                                · aproximadamente <span class="font-monospace">{{ FuelAmounts::indicativeUnitPriceUsd($current->price_lbp, $rate->rate) }}</span> USD por litro
                            @endif
                        </p>
                        @include('partials.indicative-rate-note', ['rate' => $rate])
                    @else
                        <p class="mb-0">Todavía no hay ningún precio vigente, así que este producto no puede venderse.</p>
                    @endif
                </div>
            </div>
        </div>

        @if ($admin)
            <div class="col-lg-6">
                <form method="POST" action="{{ route('products.prices.store', $product) }}" class="card card-body shadow-sm h-100">
                    @csrf
                    <h2 class="h5">Publicar un precio</h2>
                    <x-form.input name="price_lbp" label="Precio (LBP por litro)" required inputmode="decimal"
                                  help="Hasta 4 decimales, por ejemplo 80000 o 80000.5." />
                    <x-form.input name="effective_from" type="datetime-local" label="Inicia a las (hora de Beirut)"
                                  help="Déjalo vacío para empezar ahora. Se rechaza una hora pasada." />
                    <div>
                        <button type="submit" class="btn btn-primary">Publicar precio</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <h2 class="h5">Cronología</h2>
    @if ($prices->isEmpty())
        <p class="text-body-secondary">Todavía no hay precios.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Inicia (hora de Beirut)</th>
                        <th scope="col" class="text-end">Precio (LBP por litro)</th>
                        <th scope="col">Estado</th>
                        @if ($admin)
                            <th scope="col">Publicado por</th>
                            <th scope="col">Publicado el</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($prices as $price)
                        <tr>
                            <td>{{ Display::businessTime($price->effective_from) }}</td>
                            <td class="text-end font-monospace">{{ Display::decimal($price->price_lbp, 4) }}</td>
                            <td>
                                @if ($price->effective_from->isAfter($now))
                                    <span class="badge text-bg-info">Programado</span>
                                @elseif ($current && $price->is($current))
                                    <span class="badge text-bg-success">Vigente</span>
                                @else
                                    <span class="badge text-bg-secondary">Anterior</span>
                                @endif
                            </td>
                            @if ($admin)
                                <td>{{ $price->creator->name }}</td>
                                <td>{{ $price->created_at ? Display::businessTime($price->created_at) : '' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $prices->links() }}
    @endif
@endsection
