@extends('errors.layout')

@section('code', '419')
@section('title', 'Tu sesión expiró')
{{-- A form was sent with an outdated security (CSRF) token: signed out, timed out, or signed in again in another tab. --}}
@section('message', 'Por tu seguridad, los formularios dejan de funcionar tras cerrar sesión o permanecer inactivo un tiempo. No se guardó nada. Inicia sesión de nuevo y repite el cambio.')
@section('actions')
    <a class="btn btn-primary" href="{{ route('login') }}">Iniciar sesión de nuevo</a>
    <a class="btn btn-outline-secondary" href="{{ url('/') }}">Ir a la página de inicio</a>
@endsection
