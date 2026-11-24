@extends('errors.layout')

@section('code', '404')
@section('title', 'Página no encontrada')
{{-- The same words whether the record never existed or belongs to another company: a 404 reveals nothing. --}}
@section('message', 'Esta página o registro no existe, o no tienes acceso a él. Revisa la dirección o vuelve a tu página de inicio.')
@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Ir a la página de inicio</a>
@endsection
