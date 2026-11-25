{{-- Expects $searchLabel, $statusOptions (array|null) and $companies (Collection|null, admins only). --}}
<form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end mb-3" role="search">
    <div class="col-sm-5 col-lg-4">
        <label for="filter-q" class="form-label small mb-1">{{ $searchLabel }}</label>
        <input id="filter-q" name="q" type="search" value="{{ request()->string('q') }}" class="form-control form-control-sm" maxlength="100">
    </div>
    @if (! empty($statusOptions))
        <div class="col-sm-3 col-lg-2">
            <label for="filter-status" class="form-label small mb-1">Estado</label>
            <select id="filter-status" name="status" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach ($statusOptions as $value => $text)
                    <option value="{{ $value }}" @selected(request()->string('status')->toString() === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if ($companies !== null)
        <div class="col-sm-4 col-lg-3">
            <label for="filter-company" class="form-label small mb-1">Empresa</label>
            <select id="filter-company" name="company" class="form-select form-select-sm">
                <option value="">Todas las empresas</option>
                @foreach ($companies as $id => $companyName)
                    <option value="{{ $id }}" @selected(request()->string('company')->toString() === (string) $id)>{{ $companyName }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
        <a class="btn btn-sm btn-link" href="{{ url()->current() }}">Limpiar</a>
    </div>
</form>
