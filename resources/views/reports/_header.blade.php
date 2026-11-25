{{--
    Title, report navigation and the filter form shared by the report pages.
    Expects $title, $firstDay, $lastDay (Beirut dates, inclusive),
    $companies (admins only, else null), $companyName (null = all),
    $fleets (id => name) and $fleetName (null = all fleets).
    Optional: $datesApply (false for the current-month quota report) and
    $groupBy (consumption only).
--}}
@php
    $datesApply ??= true;
    $reportPages = [
        'reports.consumption' => 'Consumo',
        'reports.top-stations' => 'Top estaciones',
        'reports.quota-exceptions' => 'Excepciones de cuota',
        'reports.anomalies' => 'Anomalías',
        'reports.efficiency' => 'Estimación de eficiencia',
        'reports.delivery-sla' => 'SLA de entregas',
    ];
    $toValue = \Carbon\CarbonImmutable::parse($lastDay)->addDay()->format('Y-m-d');
@endphp

<h1 class="h3 mb-3">Reportes</h1>

<ul class="nav nav-pills flex-wrap gap-1 mb-3">
    @foreach ($reportPages as $pageRoute => $pageLabel)
        <li class="nav-item">
            <a @class(['nav-link', 'active' => request()->routeIs($pageRoute)])
               @if (request()->routeIs($pageRoute)) aria-current="page" @endif
               href="{{ route($pageRoute, request()->only(['from', 'to', 'company_id', 'fleet_id'])) }}">{{ $pageLabel }}</a>
        </li>
    @endforeach
</ul>

<form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end mb-3" aria-label="Filtros del reporte">
    @if ($datesApply)
        <div class="col-sm-4 col-lg-2">
            <label for="filter-from" class="form-label small mb-1">Desde (fecha de Beirut)</label>
            <input id="filter-from" name="from" type="date" value="{{ $firstDay }}" class="form-control form-control-sm @error('from') is-invalid @enderror">
            @error('from')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-4 col-lg-2">
            <label for="filter-to" class="form-label small mb-1">Antes de (no incluido)</label>
            <input id="filter-to" name="to" type="date" value="{{ $toValue }}" class="form-control form-control-sm @error('to') is-invalid @enderror">
            @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif
    @if ($companies !== null)
        <div class="col-sm-4 col-lg-3">
            <label for="filter-company" class="form-label small mb-1">Empresa</label>
            <select id="filter-company" name="company_id" class="form-select form-select-sm">
                <option value="">Todas las empresas</option>
                @foreach ($companies as $id => $name)
                    <option value="{{ $id }}" @selected(request()->string('company_id')->toString() === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-sm-4 col-lg-3">
        <label for="filter-fleet" class="form-label small mb-1">Flota</label>
        <select id="filter-fleet" name="fleet_id" class="form-select form-select-sm @error('fleet_id') is-invalid @enderror">
            <option value="">Todas las flotas</option>
            @foreach ($fleets as $id => $name)
                <option value="{{ $id }}" @selected(request()->string('fleet_id')->toString() === (string) $id)>{{ $name }}</option>
            @endforeach
        </select>
        @error('fleet_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @isset($groupBy)
        <div class="col-sm-4 col-lg-2">
            <label for="filter-group" class="form-label small mb-1">Agrupar por</label>
            <select id="filter-group" name="group_by" class="form-select form-select-sm">
                @foreach (['company' => 'Empresa', 'vehicle' => 'Vehículo', 'product' => 'Producto'] as $value => $text)
                    <option value="{{ $value }}" @selected($groupBy === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
    @endisset
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Mostrar</button>
        <a class="btn btn-sm btn-link" href="{{ url()->current() }}">Limpiar</a>
    </div>
</form>

<h2 class="h5 mb-1">{{ $title }}</h2>
<p class="small text-body-secondary">
    {{ $companyName ?? 'Todas las empresas' }}
    @if ($fleetName !== null)
        · Flota {{ $fleetName }}
    @endif
    @if ($datesApply)
        · {{ $firstDay }} a {{ $lastDay }}, hora de Beirut
    @endif
</p>
