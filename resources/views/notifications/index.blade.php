@extends('layouts.app')

@use('App\Support\Display')

@section('title', 'Notificaciones')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Notificaciones</h1>
        <div class="d-flex gap-2">
            @can('viewAny', App\Models\NotificationRule::class)
                <a class="btn btn-sm btn-outline-primary" href="{{ route('notifications.rules.index') }}">Reglas de notificación</a>
            @endcan
            <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">Marcar todas como leídas</button>
            </form>
        </div>
    </div>

    <form method="GET" action="{{ route('notifications.index') }}" class="row g-2 align-items-end mb-3" role="search">
        <div class="col-sm-4 col-lg-3">
            <label for="filter-status" class="form-label small mb-1">Estado</label>
            <select id="filter-status" name="status" class="form-select form-select-sm">
                <option value="">Todas</option>
                <option value="unread" @selected($filters['status'] === 'unread')>No leídas</option>
                <option value="read" @selected($filters['status'] === 'read')>Leídas</option>
            </select>
        </div>
        <div class="col-sm-5 col-lg-4">
            <label for="filter-type" class="form-label small mb-1">Tipo</label>
            <select id="filter-type" name="event_type" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected($filters['event_type'] === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a class="btn btn-sm btn-link" href="{{ route('notifications.index') }}">Limpiar</a>
        </div>
    </form>

    @if ($notifications->isEmpty())
        <p class="text-body-secondary">No hay notificaciones que coincidan con estos filtros.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Título</th>
                        <th scope="col">Mensaje</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Fecha (Beirut)</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($notifications as $notification)
                        <tr @class(['fw-semibold' => $notification->read_at === null])>
                            <td>
                                @if ($notification->read_at === null)
                                    <span class="badge text-bg-primary me-1">Nueva</span>
                                @endif
                                {{ $notification->title }}
                            </td>
                            <td class="small">{{ $notification->message }}</td>
                            <td class="small">{{ $notification->event_type->label() }}</td>
                            <td class="text-nowrap small">{{ Display::businessTime($notification->created_at) }}</td>
                            <td class="text-end">
                                @if ($notification->read_at === null)
                                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Marcar leída</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $notifications->links() }}
    @endif
@endsection
