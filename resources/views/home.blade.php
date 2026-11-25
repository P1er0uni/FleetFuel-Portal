@extends('layouts.app')

@section('content')
    <div class="p-4 bg-body rounded-3 shadow-sm">
        <h1 class="h3">{{ config('app.name') }}</h1>
        <p class="lead">
            Tarjetas de combustible para flotas corporativas, compras en POS de estaciones y
            entregas de diésel, reportadas en USD y LBP.
        </p>
        <p class="text-body-secondary">
            Los gerentes de flota administran vehículos, conductores y tarjetas de combustible con
            cuotas mensuales; las estaciones envían compras a través de una API; el personal del
            distribuidor programa entregas de diésel y consulta reportes de consumo. Toda empresa,
            persona y precio aquí es ficticio. Estado del servicio:
            <a href="{{ route('health') }}">/health</a>.
        </p>

        @auth
            <a class="btn btn-primary" href="{{ route(auth()->user()->role->homeRoute()) }}">Abrir el portal</a>
        @else
            <a class="btn btn-primary" href="{{ route('login') }}">Iniciar sesión</a>
        @endauth
    </div>
@endsection
