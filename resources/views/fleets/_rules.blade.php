{{--
    The fleet's rules: the stored list plus the create/replace form.
    Expects $fleet, $rules (Collection<FleetRule>) and $canUpdate.
--}}
@use('App\Enums\FleetRuleType')

@if ($rules->isEmpty())
    <p class="text-body-secondary">Esta flota no tiene reglas; sus tarjetas funcionan solo con las reglas de la tarjeta y de la empresa.</p>
@else
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle bg-body">
            <thead>
                <tr>
                    <th scope="col">Regla</th>
                    <th scope="col">Valor</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rules as $rule)
                    <tr>
                        <td>{{ $rule->rule_type->label() }}</td>
                        <td class="small">
                            @if ($rule->rule_type->isListRule())
                                {{ count($rule->rule_value['ids'] ?? []) }} elemento(s) permitido(s)
                            @else
                                {{ $rule->rule_value['value'] ?? '—' }}
                                {{ $rule->rule_type === FleetRuleType::MaxLitersPerTransaction ? 'L por transacción' : 'transacciones por día' }}
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

        <form method="POST" action="{{ route('fleets.rules.store', $fleet) }}">
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
                    <label for="rule_ids" class="form-label">Elementos permitidos (para reglas de lista)</label>
                    <select id="rule_ids" name="ids[]" class="form-select" multiple size="5">
                        @foreach ($ruleOptions['products'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                        @foreach ($ruleOptions['stations'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Productos y estaciones activos. Mantén Ctrl/Cmd para elegir varios.</div>
                </div>
                <div class="col-md-4">
                    <x-form.input name="value" label="Valor del límite (para reglas numéricas)" inputmode="decimal"
                                  help="Litros por transacción o transacciones por día." />
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="active" value="1" id="rule_active" checked>
                <label class="form-check-label" for="rule_active">Regla activa</label>
            </div>

            <div><button type="submit" class="btn btn-primary">Guardar regla</button></div>
        </form>
    </div>
@endif
