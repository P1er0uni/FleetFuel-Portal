@extends('layouts.app')

@use('App\Support\AuditDisplay')
@use('App\Support\Display')

@section('title', 'Auditoría')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
        <h1 class="h3 mb-0">Registro de auditoría</h1>
        <span class="text-body-secondary">{{ number_format($entries->total()) }} {{ $entries->total() === 1 ? 'entrada' : 'entradas' }}</span>
    </div>
    <p class="small text-body-secondary">
        Cambios sensibles, del más reciente al más antiguo: cuotas, bloqueos, archivado, entregas, precios, ajustes de tasas y cuentas.
        Solo se registran campos de negocio seleccionados; las credenciales nunca, y los números de tarjeta se enmascaran. El registro no puede editarse.
    </p>

    <form method="GET" action="{{ route('audit.index') }}" class="row g-2 align-items-start mb-3" role="search" aria-label="Filtrar el registro de auditoría">
        <div class="col-6 col-md-4 col-xl-2">
            <label for="audit-actor" class="form-label small mb-1">Cambiado por</label>
            <select id="audit-actor" name="actor" @class(['form-select form-select-sm', 'is-invalid' => $errors->has('actor')])>
                <option value="">Cualquiera</option>
                <option value="system" @selected($filters['actor'] === 'system')>Línea de comandos / sistema</option>
                @foreach ($actors as $actorUser)
                    <option value="{{ $actorUser->id }}" @selected($filters['actor'] === (string) $actorUser->id)>{{ $actorUser->name }} ({{ $actorUser->role->label() }})</option>
                @endforeach
            </select>
            @error('actor')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label for="audit-action" class="form-label small mb-1">Acción</label>
            <select id="audit-action" name="action" @class(['form-select form-select-sm', 'is-invalid' => $errors->has('action')])>
                <option value="">Cualquier acción</option>
                @foreach ($actions as $actionName)
                    <option value="{{ $actionName }}" @selected($filters['action'] === $actionName)>{{ AuditDisplay::action($actionName) }}</option>
                @endforeach
            </select>
            @error('action')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label for="audit-entity" class="form-label small mb-1">Tipo de registro</label>
            <select id="audit-entity" name="entity" @class(['form-select form-select-sm', 'is-invalid' => $errors->has('entity')])>
                <option value="">Cualquier tipo</option>
                @foreach ($entities as $alias => $label)
                    <option value="{{ $alias }}" @selected($filters['entity'] === $alias)>{{ $label }}</option>
                @endforeach
            </select>
            @error('entity')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label for="audit-company" class="form-label small mb-1">Empresa</label>
            <select id="audit-company" name="company_id" @class(['form-select form-select-sm', 'is-invalid' => $errors->has('company_id')])>
                <option value="">Cualquier empresa</option>
                @foreach ($companies as $id => $name)
                    <option value="{{ $id }}" @selected($filters['company'] === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-4 col-xl-auto">
            <label for="audit-from" class="form-label small mb-1">Desde (fecha de Beirut)</label>
            <input id="audit-from" name="from" type="date" value="{{ old('from', $filters['from']) }}"
                   @class(['form-control form-control-sm', 'is-invalid' => $errors->has('from')])
                   @if ($errors->has('from')) aria-invalid="true" aria-describedby="audit-from-error" @endif>
            @error('from')<div id="audit-from-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-4 col-xl-auto">
            <label for="audit-to" class="form-label small mb-1">Antes de (no incluido)</label>
            <input id="audit-to" name="to" type="date" value="{{ old('to', $filters['to']) }}"
                   @class(['form-control form-control-sm', 'is-invalid' => $errors->has('to')])
                   @if ($errors->has('to')) aria-invalid="true" aria-describedby="audit-to-error" @endif>
            @error('to')<div id="audit-to-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-xl-auto d-flex gap-2 align-self-xl-end">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('audit.index') }}">Limpiar</a>
        </div>
    </form>

    @if ($entries->isEmpty())
        <div class="alert alert-light border">
            <p class="mb-0">Ninguna entrada de auditoría coincide con estos filtros.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-top bg-body">
                <thead>
                    <tr>
                        <th scope="col">Hora (Beirut)</th>
                        <th scope="col">Acción</th>
                        <th scope="col">Registro</th>
                        <th scope="col">Empresa</th>
                        <th scope="col">Cambiado por</th>
                        <th scope="col">Antes → después</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        @php($entityUrl = AuditDisplay::entityUrl($entry->auditable_type, $entry->auditable_id))
                        <tr>
                            <td class="text-nowrap">{{ $entry->created_at ? Display::businessTime($entry->created_at) : '' }}</td>
                            <td>
                                {{ AuditDisplay::action($entry->action) }}
                                <span class="d-block small text-body-secondary font-monospace">{{ $entry->action }}</span>
                            </td>
                            <td class="text-nowrap">
                                @if ($entityUrl)
                                    <a href="{{ $entityUrl }}">{{ AuditDisplay::entityLabel($entry->auditable_type) }} #{{ $entry->auditable_id }}</a>
                                @else
                                    {{ AuditDisplay::entityLabel($entry->auditable_type) }} #{{ $entry->auditable_id }}
                                @endif
                            </td>
                            <td>{{ $entry->company?->name ?? '—' }}</td>
                            <td>{{ $entry->user?->name ?? 'Línea de comandos / sistema' }}</td>
                            <td class="audit-values">
                                @php($changes = AuditDisplay::changes($entry->old_values, $entry->new_values))
                                @if ($changes === [])
                                    <span class="text-body-secondary">Sin valores de campos registrados.</span>
                                @else
                                    <dl class="row small mb-0">
                                        @foreach ($changes as $change)
                                            <dt class="col-5 fw-normal font-monospace text-body-secondary">{{ $change['field'] }}</dt>
                                            <dd class="col-7 mb-1">
                                                {{ $change['before'] ?? '—' }}
                                                <span aria-hidden="true">→</span><span class="visually-hidden">cambió a</span>
                                                {{ $change['after'] ?? '—' }}
                                            </dd>
                                        @endforeach
                                    </dl>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $entries->onEachSide(1)->links() }}
    @endif
@endsection
