{{--
    One member tab: a paginated table plus the assign/unassign forms.
    Expects $members (paginator), $columns (list of [label, key]) and $type
    ('vehicles' | 'drivers' | 'cards').
--}}
@use('App\Support\Display')
@use('App\Support\Redact')

@if ($members->isEmpty())
    <p class="text-body-secondary">Esta flota no tiene {{ ['vehicles' => 'vehículos', 'drivers' => 'conductores', 'cards' => 'tarjetas'][$type] }} asignados.</p>
@else
    <div class="table-responsive mb-3">
        <table class="table table-sm table-hover align-middle bg-body">
            <thead>
                <tr>
                    @foreach ($columns as [$label])
                        <th scope="col">{{ $label }}</th>
                    @endforeach
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($members as $member)
                    <tr>
                        @foreach ($columns as [, $key])
                            <td>
                                @if ($key === 'card')
                                    <a class="font-monospace" href="{{ route('cards.show', $member) }}">{{ Redact::cardNumber($member->card_no) }}</a>
                                @elseif ($key === 'fuel')
                                    {{ $member->fuel_type->value === 'diesel' ? 'Diésel' : 'Gasolina' }}
                                @elseif ($key === 'tank')
                                    {{ Display::decimal($member->tank_capacity_l) }}
                                @elseif ($key === 'plate')
                                    {{ $member->vehicle?->plate_no ?? '—' }}
                                @elseif ($key === 'driver')
                                    {{ $member->driver?->name ?? '—' }}
                                @else
                                    {{ $member->{$key} }}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end">
                            @if ($canUpdate)
                                <form method="POST" action="{{ route('fleets.unassign', $fleet) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="{{ $type }}[]" value="{{ $member->id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Desasignar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $members->onEachSide(1)->links() }}
@endif

@if ($canUpdate)
    <div class="card card-body shadow-sm">
        <h3 class="h6">Asignar {{ ['vehicles' => 'vehículos', 'drivers' => 'conductores', 'cards' => 'tarjetas'][$type] }} a esta flota</h3>
        @if ($assignOptions[$type]->isEmpty())
            <p class="small text-body-secondary mb-0">No quedan {{ ['vehicles' => 'vehículos', 'drivers' => 'conductores', 'cards' => 'tarjetas'][$type] }} de la empresa sin asignar.</p>
        @else
            <form method="POST" action="{{ route('fleets.assign', $fleet) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-sm-8 col-lg-6">
                    <label for="assign-{{ $type }}" class="form-label small mb-1">Elige uno o varios (Ctrl/Cmd para varios)</label>
                    <select id="assign-{{ $type }}" name="{{ $type }}[]" class="form-select" multiple size="4" required>
                        @foreach ($assignOptions[$type] as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Asignar</button>
                </div>
            </form>
        @endif
    </div>
@endif
