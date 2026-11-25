@extends('layouts.app')

@section('title', 'Importar')

@section('content')
    <h1 class="h3 mb-2">Importación masiva</h1>
    <p class="text-body-secondary">
        Sube un archivo CSV para crear vehículos, conductores o tarjetas de una sola vez.
        El encabezado debe coincidir exactamente con la plantilla; las filas inválidas se
        saltan con su error y las válidas se guardan. Usa «Simular» para validar sin guardar.
        Límite: 5000 filas y 5 MB por archivo.
    </p>

    @if ($result)
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap gap-3 align-items-center">
                <strong>Resultado de la importación</strong>
                <span class="badge text-bg-secondary">{{ $result['type'] }}</span>
                @if ($result['dry_run'])
                    <span class="badge text-bg-warning">Simulación (no se guardó nada)</span>
                @endif
                <span class="ms-auto small">
                    Leídas: <strong>{{ $result['total_rows'] }}</strong> ·
                    Guardadas: <strong class="text-success">{{ $result['successful'] }}</strong> ·
                    Con error: <strong class="{{ $result['failed'] > 0 ? 'text-danger' : '' }}">{{ $result['failed'] }}</strong>
                </span>
            </div>
            @if ($result['errors'] !== [])
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="text-end">Fila</th>
                                <th scope="col">Campo</th>
                                <th scope="col">Mensaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['errors'] as $error)
                                <tr>
                                    <td class="text-end">{{ $error['row'] }}</td>
                                    <td class="font-monospace">{{ $error['field'] }}</td>
                                    <td>{{ $error['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="mb-1">No se pudo procesar el archivo:</p>
            <ul class="mb-0">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <ul class="nav nav-tabs" id="import-tabs" role="tablist">
        @foreach ($types as $type => $label)
            <li class="nav-item" role="presentation">
                <button @class(['nav-link', 'active' => $loop->first]) id="tab-{{ $type }}-button"
                        data-bs-toggle="tab" data-bs-target="#tab-{{ $type }}" type="button" role="tab"
                        aria-controls="tab-{{ $type }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                    {{ $label }}
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom p-3 bg-body">
        @foreach ($types as $type => $label)
            <div @class(['tab-pane fade', 'show active' => $loop->first]) id="tab-{{ $type }}" role="tabpanel"
                 aria-labelledby="tab-{{ $type }}-button" tabindex="0">
                <p class="small text-body-secondary mb-2">
                    Encabezado esperado: <code>{{ $headers[$type] }}</code>
                </p>
                <p class="mb-3">
                    <a href="{{ route('import.template', $type) }}" class="btn btn-sm btn-outline-secondary">
                        Descargar plantilla CSV
                    </a>
                </p>

                <form method="POST" action="{{ route('import.store', $type) }}" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    @if ($companies !== null)
                        <div class="col-sm-5 col-lg-4">
                            <label for="company-{{ $type }}" class="form-label small mb-1">Empresa</label>
                            <select id="company-{{ $type }}" name="company_id" class="form-select form-select-sm" required>
                                <option value="">Elige una empresa</option>
                                @foreach ($companies as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <p class="col-auto mb-2 small">Empresa: <strong>{{ $ownCompany?->name }}</strong></p>
                    @endif

                    <div class="col-sm-5 col-lg-4">
                        <label for="file-{{ $type }}" class="form-label small mb-1">Archivo CSV</label>
                        <input id="file-{{ $type }}" name="file" type="file" accept=".csv,text/csv"
                               class="form-control form-control-sm" required>
                    </div>

                    <div class="col-auto">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="dry_run" value="1" id="dry-run-{{ $type }}">
                            <label class="form-check-label small" for="dry-run-{{ $type }}">Simular (no guardar)</label>
                        </div>
                    </div>

                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Importar</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
@endsection
