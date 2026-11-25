@extends('layouts.app')

@section('title', 'Estación')

@section('content')
    <div class="mb-3">
        <h1 class="h3 mb-0">{{ $user->station->name }}</h1>
        <span class="text-body-secondary">{{ $user->station->district }}, {{ $user->station->governorate }}</span>
    </div>

    @unless ($user->station->is_active)
        <div class="alert alert-warning" role="alert">
            Esta estación está inactiva. Las compras POS desde ella serán rechazadas.
        </div>
    @endunless

    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
        <h2 class="h5">Compras recientes en esta estación</h2>
        <a class="small" href="{{ route('transactions.index') }}">Todas las compras, con filtros y totales</a>
    </div>
    @include('transactions._table', [
        'transactions' => $purchases,
        'showCompany' => true,
        'showStation' => false,
    ])

    <h2 class="h5 mt-4">Acceso a la API POS</h2>
    <p>
        La terminal del punto de venta inicia sesión con su propio token de API, no con esta sesión del navegador.
        Solicita un token con las credenciales de tu cuenta. Se muestra una sola vez, expira tras 24 horas
        y puede revocarse en cualquier momento:
    </p>
<pre class="bg-body border rounded p-3 small"><code>curl -X POST {{ url('/api/v1/auth/token') }} \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email": "{{ $user->email }}", "password": "YOUR-PASSWORD", "device_name": "pos-terminal-1"}'</code></pre>
    <p>
        Envía cada compra con el token. La estación siempre es la tuya; un <code>external_ref</code> único
        por compra hace seguro reintentar (un reintento idéntico devuelve la compra original en lugar de cobrar dos veces).
        Los litros son una cadena decimal y la hora debe llevar su desplazamiento UTC:
    </p>
<pre class="bg-body border rounded p-3 small"><code>curl -X POST {{ url('/api/v1/transactions') }} \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer YOUR-TOKEN' \
  -d '{"external_ref": "POS-0001", "card_no": "FF-ATLAS-001", "product_code": "DIESEL",
       "liters": "20.00", "transacted_at": "{{ now()->setTimezone(config('fleetfuel.business_timezone'))->format('Y-m-d\TH:i:sP') }}"}'</code></pre>
    <p>
        Consulta una tarjeta antes de despachar combustible con <code>GET /api/v1/cards/{card_no}/balance</code>. Revoca el token con
        <code>DELETE /api/v1/auth/token</code>, enviándolo como <code>Authorization: Bearer …</code>.
    </p>

    <h2 class="h5 mt-4">Simulador POS</h2>
    <p class="mb-0">
        El simulador independiente del proyecto (<code>tools/pos-simulator</code>) hace el papel de una terminal a través de esta API y
        verifica cada respuesta: una compra, un reintento idéntico y uno cambiado, una tarjeta bloqueada y una solicitud sobre cuota.
        Desde la carpeta del proyecto, con tus credenciales solo en el entorno:
        <code>POS_EMAIL={{ $user->email }} POS_PASSWORD=… make simulate</code>.
        Su README explica las opciones y cómo preparar tarjetas nuevas para otra corrida.
    </p>
@endsection
