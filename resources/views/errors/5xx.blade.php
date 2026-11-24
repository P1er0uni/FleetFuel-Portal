@extends('errors.layout')

{{--
    500, 503 and any other server error (Laravel falls back to this view).
    No exception text is shown: it is logged, with the request ID.
--}}
@php($status = $exception->getStatusCode())

@section('code', (string) $status)
@section('title', $status === 503 ? 'No disponible temporalmente' : 'Algo salió mal')
@section('message', $status === 503
    ? 'El portal no está disponible por un momento, por ejemplo durante mantenimiento. Vuelve a intentarlo en unos minutos.'
    : 'Un error inesperado detuvo esta solicitud de nuestro lado. Quedó registrado. Vuelve a intentarlo en un momento; si sigue ocurriendo, avísale a un administrador qué estabas haciendo.')
@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Ir a la página de inicio</a>
@endsection
