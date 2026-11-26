@extends('layouts.app')

@section('title', 'Cambiar contraseña')

@section('content')
    <div class="row">
        <div class="col-lg-6">
            <h1 class="h3 mb-3">Cambiar contraseña</h1>

            @if (auth()->user()->must_change_password)
                <div class="alert alert-warning" role="status">
                    Debes cambiar tu contraseña temporal antes de continuar.
                </div>
            @endif

            <form method="POST" action="{{ route('password.change.update') }}" class="card card-body shadow-sm">
                @csrf
                @method('PUT')

                <x-form.input name="current_password" label="Contraseña actual" type="password" required />
                <x-form.input name="password" label="Nueva contraseña" type="password" required help="Mínimo 12 caracteres, distinta de la actual." />
                <x-form.input name="password_confirmation" label="Repite la nueva contraseña" type="password" required />

                <div>
                    <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
                </div>
            </form>
        </div>
    </div>
@endsection
