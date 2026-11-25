@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3">Iniciar sesión</h1>

                    <form method="POST" action="{{ route('login.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}"
                                   @class(['form-control', 'is-invalid' => $errors->has('email')])
                                   @error('email') aria-describedby="email-error" aria-invalid="true" @enderror
                                   autocomplete="username" required autofocus>
                            @error('email')
                                <div id="email-error" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Contraseña</label>
                            <input id="password" name="password" type="password"
                                   @class(['form-control', 'is-invalid' => $errors->has('password')])
                                   @error('password') aria-describedby="password-error" aria-invalid="true" @enderror
                                   autocomplete="current-password" required>
                            @error('password')
                                <div id="password-error" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Iniciar sesión</button>
                    </form>
                </div>
            </div>

            <p class="small text-body-secondary mt-3">
                Las cuentas las crea un administrador; no hay autorregistro.
            </p>
        </div>
    </div>
@endsection
