<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ $theme ?? 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-body-tertiary">
    <a class="visually-hidden-focusable skip-link" href="#main">Saltar al contenido principal</a>

    @auth
        @php
            $navUser = auth()->user();
            // Grouped [route, label, active-route pattern]; links follow the same policies as the pages.
            $navGroups = array_filter(array_map('array_filter', [
                'General' => [
                    $navUser->isStationOperator() ? ['station.home', 'Estación', 'station.home'] : ['dashboard', 'Panel', 'dashboard'],
                    $navUser->can('viewAny', App\Models\FuelTransaction::class) ? ['transactions.index', 'Transacciones', 'transactions.*'] : null,
                    $navUser->can('viewReports') ? ['reports.consumption', 'Reportes', 'reports.*'] : null,
                    $navUser->can('viewAny', App\Models\DeliveryOrder::class) ? ['deliveries.index', 'Entregas', 'deliveries.*'] : null,
                    $navUser->can('useNotifications') ? ['notifications.index', 'Notificaciones', 'notifications.index'] : null,
                    ($navUser->isAdmin() || $navUser->isCompanyManager()) ? ['map.index', 'Mapa', 'map.*'] : null,
                ],
                'Flota' => [
                    $navUser->can('viewAny', App\Models\Company::class) ? ['companies.index', 'Empresas', 'companies.*'] : null,
                    $navUser->can('viewAny', App\Models\Vehicle::class) ? ['vehicles.index', 'Vehículos', 'vehicles.*'] : null,
                    $navUser->can('viewAny', App\Models\Driver::class) ? ['drivers.index', 'Conductores', 'drivers.*'] : null,
                    $navUser->can('viewAny', App\Models\FuelCard::class) ? ['cards.index', 'Tarjetas', 'cards.*'] : null,
                    $navUser->can('viewAny', App\Models\Fleet::class) ? ['fleets.index', 'Flotas', 'fleets.*'] : null,
                    $navUser->can('importData') ? ['import.index', 'Importar', 'import.*'] : null,
                ],
                'Datos de referencia' => [
                    ['stations.index', 'Estaciones', 'stations.*'],
                    ['products.index', 'Productos y precios', 'products.*'],
                ],
                'Administración' => [
                    $navUser->can('viewAny', App\Models\ExchangeRate::class) ? ['integrations.exchange-rates', 'Tasas de cambio', 'integrations.*'] : null,
                    $navUser->can('useNotifications') ? ['notifications.rules.index', 'Reglas de notificación', 'notifications.rules.*'] : null,
                    $navUser->can('viewAny', App\Models\User::class) ? ['users.index', 'Usuarios', 'users.*'] : null,
                    $navUser->can('viewAny', App\Models\AuditLog::class) ? ['audit.index', 'Auditoría', 'audit.*'] : null,
                ],
            ]));
            $homeUrl = route($navUser->role->homeRoute());
        @endphp

        <div class="app-shell d-lg-flex">
            {{-- Below 992px: a top bar with a menu button that opens the sidebar as an off-canvas panel. --}}
            <header class="app-topbar navbar d-lg-none" data-bs-theme="dark">
                <div class="container-fluid d-flex align-items-center gap-2">
                    <a class="navbar-brand" href="{{ $homeUrl }}">{{ config('app.name') }}</a>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#app-sidebar"
                                aria-controls="app-sidebar">
                            Menú
                        </button>
                        {{-- Signing out changes state, so it is a POST with a CSRF token, never a link. --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-light btn-sm" aria-label="Cerrar sesión">Salir</button>
                        </form>
                    </div>
                </div>
            </header>

            {{-- A plain wrapper: the landmark is the <nav> inside, not a "complementary" region. --}}
            <div class="app-sidebar">
                <div class="offcanvas-lg offcanvas-start" id="app-sidebar" tabindex="-1" aria-labelledby="app-sidebar-title" data-bs-theme="dark">
                    <div class="offcanvas-header">
                        <span class="visually-hidden" id="app-sidebar-title">Menú de navegación</span>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#app-sidebar" aria-label="Cerrar menú"></button>
                    </div>
                    <div class="offcanvas-body flex-column w-100 p-3">
                        <nav aria-label="Principal">
                            @foreach ($navGroups as $groupLabel => $groupLinks)
                                <h2 class="nav-section">{{ $groupLabel }}</h2>
                                <ul class="nav flex-column">
                                    @foreach ($groupLinks as [$navRoute, $navLabel, $navPattern])
                                        <li class="nav-item">
                                            <a @class(['nav-link', 'active' => request()->routeIs($navPattern)])
                                               @if (request()->routeIs($navPattern)) aria-current="page" @endif
                                               href="{{ route($navRoute) }}">{{ $navLabel }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endforeach
                        </nav>

                        @include('layouts._notifications')

                        <div class="account mt-auto pt-3 px-2">
                            <a class="d-block small text-white" href="{{ route('preferences.edit') }}">Preferencias</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-grow-1 min-vh-100 min-w-0">
                {{-- Desktop-only bar: a button to collapse/expand the sidebar and reclaim horizontal space. --}}
                <header class="app-desktopbar d-none d-lg-flex align-items-center gap-2 px-3 py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary app-sidebar-toggle"
                            data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="true"
                            aria-label="Mostrar u ocultar el menú lateral" title="Mostrar u ocultar el menú">
                        <span aria-hidden="true">☰</span>
                    </button>
                    <span class="fw-semibold">{{ config('app.name') }}</span>
                    <span class="text-body-secondary small ms-auto d-none d-md-inline">{{ $navUser->name }} · {{ $navUser->role->label() }}</span>
                    {{-- Signing out changes state, so it is a POST with a CSRF token, never a link. --}}
                    <form method="POST" action="{{ route('logout') }}" class="d-flex ms-lg-3">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Cerrar sesión</button>
                    </form>
                </header>

                <main id="main" class="app-main container-fluid flex-grow-1 px-3 px-lg-4 py-4" tabindex="-1">
                    @include('layouts._flash')
                    @yield('content')
                </main>
                @include('layouts._footer')
            </div>
        </div>

        @push('scripts')
            <script>
                (function () {
                    var shell = document.querySelector('.app-shell');
                    if (shell === null) { return; }

                    var button = document.querySelector('[data-sidebar-toggle]');
                    var key = 'ff-sidebar-collapsed';

                    function sync() {
                        if (button === null) { return; }
                        button.setAttribute('aria-expanded', shell.classList.contains('sidebar-collapsed') ? 'false' : 'true');
                    }

                    try {
                        if (window.localStorage.getItem(key) === '1') {
                            shell.classList.add('sidebar-collapsed');
                        }
                    } catch (error) { /* storage blocked: start expanded */ }

                    sync();

                    if (button !== null) {
                        button.addEventListener('click', function () {
                            shell.classList.toggle('sidebar-collapsed');
                            sync();
                            try {
                                window.localStorage.setItem(key, shell.classList.contains('sidebar-collapsed') ? '1' : '0');
                            } catch (error) { /* ignore */ }
                        });
                    }
                })();
            </script>
        @endpush
    @else
        <nav class="app-topbar navbar" data-bs-theme="dark" aria-label="Principal">
            <div class="container">
                <a class="navbar-brand" href="{{ route('home') }}">{{ config('app.name') }}</a>

                @unless (request()->routeIs('login'))
                    <a class="btn btn-outline-light btn-sm" href="{{ route('login') }}">Iniciar sesión</a>
                @endunless
            </div>
        </nav>

        <div class="d-flex flex-column min-vh-100">
            <main id="main" class="container flex-grow-1 py-4" tabindex="-1">
                @include('layouts._flash')
                @yield('content')
            </main>
            @include('layouts._footer')
        </div>
    @endauth
    @stack('scripts')
</body>
</html>
