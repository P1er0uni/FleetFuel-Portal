@extends('layouts.app')

@section('title', $rule ? 'Editar regla de notificación' : 'Agregar regla de notificación')

@section('content')
    @php
        $eventOptions = collect($events)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all();
        $channelOptions = collect($channels)->mapWithKeys(fn ($channel) => [$channel->value => $channel->label()])->all();
        $operatorOptions = collect($operators)->mapWithKeys(fn ($operator) => [$operator->value => $operator->label()])->all();
        $currentEvent = old('event_type', $rule?->event_type?->value);
        $isQuota = $currentEvent === 'quota_warning';
    @endphp

    <h1 class="h3 mb-3">{{ $rule ? 'Editar regla de notificación' : 'Agregar regla de notificación' }}</h1>

    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ $rule ? route('notifications.rules.update', $rule) : route('notifications.rules.store') }}"
                  class="card card-body shadow-sm">
                @csrf
                @if ($rule)
                    @method('PUT')
                @endif

                @if (! $rule)
                    @if ($companies !== null)
                        <x-form.select name="company_id" label="Empresa" :options="$companies" placeholder="Elige una empresa" required />
                    @else
                        <p class="mb-3">Empresa: <strong>{{ $ownCompany?->name }}</strong></p>
                    @endif
                @else
                    <p class="mb-3">Empresa: <strong>{{ $ownCompany?->name }}</strong></p>
                @endif

                <x-form.select name="fleet_id" label="Flota (opcional)" :options="$fleets" placeholder="Toda la empresa"
                              :selected="$rule?->fleet_id" help="Si eliges una flota, la regla solo aplica a sus eventos." />
                <x-form.select name="event_type" label="Evento" :options="$eventOptions" :selected="$rule?->event_type?->value" required id="event_type" />
                <x-form.select name="channel" label="Canal" :options="$channelOptions" :selected="$rule?->channel?->value" required />

                <div id="threshold-block">
                    <div class="row">
                        <div class="col-md-6">
                            <x-form.input name="threshold_value" label="Umbral (%)" inputmode="decimal"
                                          :value="$rule?->threshold_value"
                                          help="Solo para el aviso de cuota. Ej.: 80." />
                        </div>
                        <div class="col-md-6">
                            <x-form.select name="threshold_operator" label="Operador" :options="$operatorOptions"
                                           :selected="$rule?->threshold_operator?->value" placeholder="Elige un operador" />
                        </div>
                    </div>
                </div>

                @if (! $rule)
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="active" checked>
                        <label class="form-check-label" for="active">Regla activa</label>
                    </div>
                @endif

                <div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a class="btn btn-link" href="{{ route('notifications.rules.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var eventSelect = document.getElementById('event_type');
            var block = document.getElementById('threshold-block');
            if (eventSelect === null || block === null) {
                return;
            }

            function sync() {
                block.hidden = eventSelect.value !== 'quota_warning';
            }

            eventSelect.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
