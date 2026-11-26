@extends('layouts.app')

@section('title', $fleet ? 'Editar flota' : 'Agregar flota')

@section('content')
    <h1 class="h3 mb-3">{{ $fleet ? 'Editar flota '.$fleet->name : 'Agregar flota' }}</h1>

    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ $fleet ? route('fleets.update', $fleet) : route('fleets.store') }}" class="card card-body shadow-sm">
                @csrf
                @if ($fleet)
                    @method('PUT')
                @endif

                @if (! $fleet)
                    @if ($companies !== null)
                        @if ($companies->isEmpty())
                            <div class="alert alert-warning">Todavía no hay ninguna empresa activa. Primero <a href="{{ route('companies.create') }}">agrega una empresa</a>.</div>
                        @endif
                        <x-form.select name="company_id" label="Empresa" :options="$companies" placeholder="Elige una empresa" required />
                    @else
                        <p class="mb-3">Empresa: <strong>{{ $ownCompany?->name }}</strong></p>
                    @endif
                @else
                    <p class="mb-3">Empresa: <strong>{{ $ownCompany?->name }}</strong></p>
                @endif

                <x-form.input name="name" label="Nombre" :value="$fleet?->name" required maxlength="100"
                              help="Único dentro de la empresa." />
                <x-form.input name="description" label="Descripción" :value="$fleet?->description" maxlength="255" help="Opcional." />

                <div class="row">
                    <div class="col-md-6">
                        <x-form.input name="monthly_quota_liters" label="Cuota mensual (litros)" :value="$fleet?->monthly_quota_liters" inputmode="decimal"
                                      help="Déjalo vacío para no poner límite." />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="monthly_quota_amount" label="Cuota mensual (USD)" :value="$fleet?->monthly_quota_amount" inputmode="decimal"
                                      help="Déjalo vacío para no poner límite." />
                    </div>
                </div>

                @unless ($fleet)
                    <hr>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="add_rule" value="1" id="add_rule" @checked(old('add_rule'))>
                        <label class="form-check-label" for="add_rule">Agregar una regla inicial (opcional)</label>
                    </div>
                    <div id="initial-rule" class="border rounded p-3 mb-3">
                        <p class="small text-body-secondary">Podrás agregar más reglas después, en el detalle de la flota.</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="rule_type" class="form-label small mb-1">Tipo de regla</label>
                                <select id="rule_type" name="rule[rule_type]" class="form-select form-select-sm">
                                    @foreach (\App\Enums\FleetRuleType::cases() as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="rule_enforcement" class="form-label small mb-1">Aplicación</label>
                                <select id="rule_enforcement" name="rule[enforcement]" class="form-select form-select-sm">
                                    <option value="block">Bloqueante</option>
                                    <option value="warn">Solo aviso</option>
                                </select>
                            </div>
                            <div class="col-md-6 rule-group" data-group="products">
                                <label class="form-label small mb-1">Productos permitidos</label>
                                <select name="rule[ids][]" class="form-select form-select-sm" multiple size="4">
                                    @foreach ($ruleOptions['products'] as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 rule-group" data-group="stations">
                                <label class="form-label small mb-1">Estaciones permitidas</label>
                                <select name="rule[ids][]" class="form-select form-select-sm" multiple size="4">
                                    @foreach ($ruleOptions['stations'] as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 rule-group" data-group="value">
                                <label class="form-label small mb-1">Valor del límite</label>
                                <input name="rule[value]" class="form-control form-control-sm" inputmode="decimal">
                            </div>
                            <div class="col-md-6 rule-group" data-group="windows">
                                <label class="form-label small mb-1">Días y horas permitidos</label>
                                <div class="d-flex flex-wrap gap-2 mb-1">
                                    @foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $num => $label)
                                        <label class="form-check form-check-inline mb-0">
                                            <input class="form-check-input" type="checkbox" name="rule[windows][0][days][]" value="{{ $num }}">
                                            <span class="form-check-label small">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="d-flex gap-2">
                                    <input name="rule[windows][0][start]" type="time" class="form-control form-control-sm" aria-label="Hora inicio">
                                    <input name="rule[windows][0][end]" type="time" class="form-control form-control-sm" aria-label="Hora fin">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Vigencia (opcional)</label>
                                <div class="d-flex gap-2">
                                    <input name="rule[valid_from]" type="date" class="form-control form-control-sm" aria-label="Vigencia desde">
                                    <input name="rule[valid_to]" type="date" class="form-control form-control-sm" aria-label="Vigencia hasta">
                                </div>
                            </div>
                        </div>
                    </div>
                @endunless

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ $fleet ? route('fleets.show', $fleet) : route('fleets.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    @if ($fleet)
        <section class="mt-4">
            <h2 class="h5 mb-2">Reglas y límites</h2>
            @include('fleets._rules', [
                'fleet' => $fleet,
                'rules' => $rules,
                'canUpdate' => $canUpdate,
                'ruleOptions' => $ruleOptions,
            ])
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var type = document.getElementById('rule_type');
            var initial = document.getElementById('initial-rule');
            if (type === null || initial === null) { return; }
            var groups = initial.querySelectorAll('.rule-group');
            function sync() {
                var v = type.value;
                groups.forEach(function (g) {
                    var name = g.getAttribute('data-group');
                    var show = (name === 'products' && v === 'allowed_products')
                        || (name === 'stations' && v === 'allowed_stations')
                        || (name === 'value' && ['max_liters_per_transaction','max_transactions_per_day','max_liters_per_day','max_amount_per_transaction_usd','max_amount_per_day_usd'].indexOf(v) !== -1)
                        || (name === 'windows' && v === 'allowed_time_windows');
                    g.hidden = !show;
                    g.querySelectorAll('input, select').forEach(function (el) { el.disabled = !show; });
                });
            }
            type.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
