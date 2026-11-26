@extends('layouts.app')

@section('title', $user ? 'Editar usuario' : 'Agregar usuario')

@section('content')
    @php
        $roleOptions = collect($roles)->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all();
        $currentRole = old('role', $user?->role?->value ?? 'company_manager');
    @endphp

    <h1 class="h3 mb-3">{{ $user ? 'Editar usuario' : 'Agregar usuario' }}</h1>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ $user ? route('users.update', $user) : route('users.store') }}" class="card card-body shadow-sm">
                @csrf
                @if ($user)
                    @method('PUT')
                @endif

                <x-form.input name="name" label="Nombre" :value="$user?->name" required maxlength="255" />
                <x-form.input name="email" label="Correo" type="email" :value="$user?->email" required maxlength="255" />

                <x-form.select name="role" label="Rol" :options="$roleOptions" :selected="$currentRole" required id="role" help="El alcance depende del rol." />

                <div id="company-field">
                    <x-form.select name="company_id" label="Empresa" :options="$companies" placeholder="Elige una empresa"
                                   :selected="$user?->company_id" help="Solo para gerentes de empresa." />
                </div>
                <div id="station-field">
                    <x-form.select name="station_id" label="Estación" :options="$stations" placeholder="Elige una estación"
                                   :selected="$user?->station_id" help="Solo para operadores de estación." />
                </div>

                @unless ($user)
                    <div class="row">
                        <div class="col-md-6">
                            <x-form.input name="password" label="Contraseña temporal" type="password" required
                                          help="Mínimo 12 caracteres. El usuario deberá cambiarla al entrar." />
                        </div>
                        <div class="col-md-6">
                            <x-form.input name="password_confirmation" label="Repite la contraseña" type="password" required />
                        </div>
                    </div>
                @endunless

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ route('users.index') }}">Cancelar</a>
                </div>
            </form>
        </div>

        @if ($user)
            <div class="col-lg-5">
                <form method="POST" action="{{ route('users.password', $user) }}" class="card card-body shadow-sm">
                    @csrf
                    <h2 class="h5">Restablecer contraseña</h2>
                    <p class="small text-body-secondary">
                        Se cerrará la sesión actual del usuario y deberá cambiar esta contraseña temporal al entrar.
                    </p>
                    <x-form.input name="password" label="Contraseña temporal" type="password" required help="Mínimo 12 caracteres." />
                    <x-form.input name="password_confirmation" label="Repite la contraseña" type="password" required />
                    <div>
                        <button type="submit" class="btn btn-outline-danger">Restablecer contraseña</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var role = document.getElementById('role');
            var companyField = document.getElementById('company-field');
            var stationField = document.getElementById('station-field');

            function sync() {
                var value = role ? role.value : '';
                companyField.hidden = value !== 'company_manager';
                stationField.hidden = value !== 'station_operator';
            }

            if (role !== null) {
                role.addEventListener('change', sync);
                sync();
            }
        })();
    </script>
@endpush
