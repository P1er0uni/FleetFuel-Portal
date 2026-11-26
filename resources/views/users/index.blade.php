@extends('layouts.app')

@use('App\Support\Display')

@section('title', 'Usuarios')

@section('content')
    @php($roleLabels = collect($roles)->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all())

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Usuarios</h1>
        <a class="btn btn-primary" href="{{ route('users.create') }}">Agregar usuario</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-end mb-3" role="search">
        <div class="col-sm-4 col-lg-3">
            <label for="u-q" class="form-label small mb-1">Nombre o correo</label>
            <input id="u-q" name="q" type="search" value="{{ $filters['q'] }}" class="form-control form-control-sm" maxlength="100">
        </div>
        <div class="col-6 col-lg-2">
            <label for="u-role" class="form-label small mb-1">Rol</label>
            <select id="u-role" name="role" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach ($roleLabels as $value => $label)
                    <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="u-status" class="form-label small mb-1">Estado</label>
            <select id="u-status" name="status" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="active" @selected($filters['status'] === 'active')>Activas</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivas</option>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="u-company" class="form-label small mb-1">Empresa</label>
            <select id="u-company" name="company" class="form-select form-select-sm">
                <option value="">Todas</option>
                @foreach ($companies as $id => $name)
                    <option value="{{ $id }}" @selected($filters['company'] === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="u-station" class="form-label small mb-1">Estación</label>
            <select id="u-station" name="station" class="form-select form-select-sm">
                <option value="">Todas</option>
                @foreach ($stations as $id => $name)
                    <option value="{{ $id }}" @selected($filters['station'] === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a class="btn btn-sm btn-link" href="{{ route('users.index') }}">Limpiar</a>
        </div>
    </form>

    @if ($users->isEmpty())
        <p class="text-body-secondary">No hay usuarios que coincidan con estos filtros.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Empresa / Estación</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td class="small">{{ $user->email }}</td>
                            <td class="small">{{ $roleLabels[$user->role->value] }}</td>
                            <td class="small">
                                {{ $user->company?->name ?? $user->station?->name ?? '—' }}
                            </td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge text-bg-success">Activa</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('users.edit', $user) }}">Editar</a>
                                @unless (auth()->id() === $user->id)
                                    <form method="POST" action="{{ route('users.status', $user) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                                        <button type="submit" @class(['btn btn-sm', 'btn-outline-secondary' => $user->is_active, 'btn-outline-success' => ! $user->is_active])>
                                            {{ $user->is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    @endif
@endsection
