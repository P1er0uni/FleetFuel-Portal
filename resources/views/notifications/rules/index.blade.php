@extends('layouts.app')

@section('title', 'Reglas de notificación')

@section('content')
    @php($admin = auth()->user()->isAdmin())

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Reglas de notificación</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('notifications.index') }}">Notificaciones</a>
            @can('create', App\Models\NotificationRule::class)
                <a class="btn btn-sm btn-primary" href="{{ route('notifications.rules.create') }}">Agregar regla</a>
            @endcan
        </div>
    </div>

    @if ($rules->isEmpty())
        <p class="text-body-secondary">Todavía no hay reglas. Crea una para empezar a recibir avisos.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Evento</th>
                        <th scope="col">Canal</th>
                        <th scope="col">Flota</th>
                        @if ($admin)
                            <th scope="col">Empresa</th>
                        @endif
                        <th scope="col">Umbral</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rules as $rule)
                        <tr>
                            <td>{{ $rule->event_type->label() }}</td>
                            <td>{{ $rule->channel->label() }}</td>
                            <td>{{ $rule->fleet?->name ?? 'Toda la empresa' }}</td>
                            @if ($admin)
                                <td>{{ $rule->company->name }}</td>
                            @endif
                            <td class="small">
                                @if ($rule->threshold_value !== null && $rule->threshold_operator !== null)
                                    {{ $rule->threshold_operator->symbol() }} {{ $rule->threshold_value }} %
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($rule->active)
                                    <span class="badge text-bg-success">Activa</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('notifications.rules.edit', $rule) }}">Editar</a>
                                <form method="POST" action="{{ route('notifications.rules.toggle', $rule) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $rule->active ? 'Desactivar' : 'Activar' }}</button>
                                </form>
                                <form method="POST" action="{{ route('notifications.rules.destroy', $rule) }}" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar esta regla?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $rules->links() }}
    @endif
@endsection
