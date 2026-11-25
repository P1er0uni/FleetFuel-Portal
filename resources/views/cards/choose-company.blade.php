@extends('layouts.app')

@section('title', 'Emitir tarjeta')

@section('content')
    <h1 class="h3 mb-3">Emitir una tarjeta de combustible</h1>

    <div class="row">
        <div class="col-lg-6">
            <form method="GET" action="{{ route('cards.create') }}" class="card card-body shadow-sm">
                <p>Primero elige la empresa dueña de la tarjeta. Sus vehículos y conductores se ofrecen en el siguiente paso.</p>

                @if ($companies->isEmpty())
                    <div class="alert alert-warning">Todavía no hay ninguna empresa activa. Primero <a href="{{ route('companies.create') }}">agrega una empresa</a>.</div>
                @endif

                <x-form.select name="company" label="Empresa" :options="$companies" placeholder="Elige una empresa" required />

                <div>
                    <button type="submit" class="btn btn-primary">Continuar</button>
                    <a class="btn btn-link" href="{{ route('cards.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
