@extends('errors.layout')

@section('code', '403')
@section('title', 'No permitido')
@section('message', 'Tu cuenta no tiene acceso a esta página o acción. Si crees que debería tenerlo, pídeselo a un administrador.')
@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Ir a la página de inicio</a>
@endsection
