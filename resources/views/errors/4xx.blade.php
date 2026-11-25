@extends('errors.layout')

{{-- Any other 4xx (Laravel falls back to this view), notably 429 Too Many Requests. --}}
@php($status = $exception->getStatusCode())

@section('code', (string) $status)
@section('title', $status === 429 ? 'Demasiadas solicitudes' : 'Solicitud no aceptada')
@section('message', $status === 429
    ? 'Demasiados intentos en poco tiempo. Espera un minuto y vuelve a intentarlo.'
    : 'No se pudo completar la solicitud. Regresa y vuelve a intentarlo.')
@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Ir a la página de inicio</a>
@endsection
