@extends('layouts.app')

@section('title', 'Editar producto')

@section('content')
    <h1 class="h3 mb-3">Editar producto <span class="font-monospace">{{ $product->code }}</span></h1>

    <div class="row">
        <div class="col-lg-6">
            <form method="POST" action="{{ route('products.update', $product) }}" class="card card-body shadow-sm">
                @csrf
                @method('PUT')

                <p class="mb-3">
                    Código <span class="font-monospace">{{ $product->code }}</span> ·
                    {{ $product->fuel_type->value === 'diesel' ? 'Diésel' : 'Gasolina' }} · se vende por {{ $product->unit }}.
                    El código y el tipo de combustible nunca cambian.
                </p>
                <x-form.input name="name" label="Nombre visible" :value="$product->name" required maxlength="80" />

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ route('products.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
