{{--
    Fleet rules editor (Fase 5). Expects $fleet, $rules (Collection<FleetRule>),
    $canUpdate and $ruleOptions (products/stations active).
--}}
@use('App\Enums\FleetRuleType')
@use('App\Enums\RuleEnforcement')

@php
    $dayNames = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
    $valueLabel = function ($rule) use ($ruleOptions, $dayNames): string {
        if ($rule->rule_type->isListRule()) {
            $map = $rule->rule_type === \App\Enums\FleetRuleType::AllowedProducts ? $ruleOptions['products'] : $ruleOptions['stations'];
            $names = collect($rule->rule_value['ids'] ?? [])->map(fn ($id) => $map[$id] ?? ('#'.$id));
            return $names->isEmpty() ? '—' : $names->implode(', ');
        }
        if ($rule->rule_type->isWindowsRule()) {
            return collect($rule->rule_value['windows'] ?? [])->map(function ($w) use ($dayNames) {
                $days = collect($w['days'] ?? [])->map(fn ($d) => $dayNames[(int) $d] ?? $d)->implode(', ');
                return $days.' '.($w['start'] ?? '').'–'.($w['end'] ?? '');
            })->implode(' · ') ?: '—';
        }
        return ($rule->rule_value['value'] ?? '—').' '.$rule->rule_type->unit();
    };
@endphp

@if ($rules->isEmpty())
    <p class="text-body-secondary">Esta flota no tiene reglas; sus tarjetas funcionan solo con las reglas de la tarjeta y de la empresa.</p>
@else
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle bg-body">
            <thead>
                <tr>
                    <th scope="col">Regla</th>
                    <th scope="col">Valor</th>
                    <th scope="col">Aplicación</th>
                    <th scope="col">Vigencia</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rules as $rule)
                    <tr>
                        <td>{{ $rule->rule_type->label() }}</td>
                        <td class="small">{{ $valueLabel($rule) }}</td>
                        <td>
                            @if ($rule->enforcement === RuleEnforcement::Warn)
                                <span class="badge text-bg-warning">Solo aviso</span>
                            @else
                                <span class="badge text-bg-dark">Bloqueante</span>
                            @endif
                        </td>
                        <td class="small">
                            @if ($rule->valid_from || $rule->valid_to)
                                {{ $rule->valid_from?->format('d/m/Y') ?? '—' }} → {{ $rule->valid_to?->format('d/m/Y') ?? '—' }}
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
                        <td class="text-end">
                            @if ($canUpdate)
                                <form method="POST" action="{{ route('fleets.rules.destroy', [$fleet, $rule]) }}" class="d-inline"
                                      data-confirm="¿Eliminar la regla {{ $rule->rule_type->label() }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($canUpdate)
    <div class="card card-body shadow-sm">
        <h2 class="h5">Agregar o reemplazar una regla</h2>
        <p class="small text-body-secondary">Cada flota tiene una regla por tipo; guardar de nuevo un tipo reemplaza su valor.</p>

        <form method="POST" action="{{ route('fleets.rules.store', $fleet) }}" id="rule-form">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="rule_type" class="form-label">Tipo de regla</label>
                    <select id="rule_type" name="rule_type" class="form-select" required>
                        @foreach (FleetRuleType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="rule_enforcement" class="form-label">Aplicación</label>
                    <select id="rule_enforcement" name="enforcement" class="form-select">
                        <option value="block">Bloqueante (rechaza la compra)</option>
                        <option value="warn">Solo aviso (permite y notifica)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="rule_valid_from" class="form-label">Vigencia desde (opcional)</label>
                    <input id="rule_valid_from" name="valid_from" type="date" class="form-control">
                    <input name="valid_to" type="date" class="form-control mt-1" aria-label="Vigencia hasta (opcional)">
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6 rule-group" data-group="products">
                    <label for="rule_products" class="form-label">Productos permitidos</label>
                    <select id="rule_products" name="ids[]" class="form-select" multiple size="5">
                        @foreach ($ruleOptions['products'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 rule-group" data-group="stations">
                    <label for="rule_stations" class="form-label">Estaciones permitidas</label>
                    <select id="rule_stations" name="ids[]" class="form-select" multiple size="5">
                        @foreach ($ruleOptions['stations'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 rule-group" data-group="value">
                    <label for="rule_value" class="form-label">Valor del límite</label>
                    <input id="rule_value" name="value" class="form-control" inputmode="decimal">
                    <div class="form-text">Litros, USD o número de transacciones según el tipo.</div>
                </div>
                <div class="col-md-6 rule-group" data-group="windows">
                    <label class="form-label">Días y horas permitidos (una franja)</label>
                    <div class="d-flex flex-wrap gap-2 mb-1">
                        @foreach ($dayNames as $num => $label)
                            <label class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" name="windows[0][days][]" value="{{ $num }}">
                                <span class="form-check-label small">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="d-flex gap-2">
                        <input name="windows[0][start]" type="time" class="form-control form-control-sm" aria-label="Hora inicio">
                        <input name="windows[0][end]" type="time" class="form-control form-control-sm" aria-label="Hora fin">
                    </div>
                </div>
            </div>

            <div class="form-check my-3">
                <input class="form-check-input" type="checkbox" name="active" value="1" id="rule_active" checked>
                <label class="form-check-label" for="rule_active">Regla activa</label>
            </div>

            <div><button type="submit" class="btn btn-primary">Guardar regla</button></div>
        </form>
    </div>

    @push('scripts')
        <script>
            (function () {
                var type = document.getElementById('rule_type');
                if (type === null) { return; }
                var groups = document.querySelectorAll('#rule-form .rule-group');
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
@endif
