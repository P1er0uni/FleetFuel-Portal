{{--
    An order's details, timeline and the next steps this user may take.
    Expects $order with company, creator and statusHistory.changedBy loaded.
    The order page includes it, and resources/js/delivery-actions.js reloads
    it (route deliveries.panel) after every status change or refusal.

    Buttons only show what the policy allows; DeliveryOrderService checks
    everything again with the order locked, so a hidden button is never
    the protection. Every form sends the status shown here as
    expected_status: if the order changed meanwhile, the change is refused
    (409 stale_state) and the panel is reloaded.
--}}
@use('App\Enums\DeliveryStatus')
@use('App\Support\Display')

@php
    $viewer = auth()->user();
    $status = $order->status;
    $advanceTo = $viewer->can('advance', $order)
        ? array_values(array_filter($status->nextStatuses(), fn (DeliveryStatus $next) => $next !== DeliveryStatus::Cancelled))
        : [];
    $canCancel = $viewer->can('cancel', $order);
    $minLocal = now()->setTimezone(config('fleetfuel.business_timezone'))->format('Y-m-d\TH:i');
    $window = fn ($start, $end) => $start === null ? '—' : Display::businessTime($start).' – '.Display::businessTime($end);
@endphp

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Orden <span data-delivery-status="{{ $status->value }}">@include('partials.delivery-status-badge', ['status' => $status])</span></h2>
                <dl class="row mb-0">
                    <dt class="col-sm-5">Empresa</dt>
                    <dd class="col-sm-7">{{ $order->company->name }}</dd>
                    <dt class="col-sm-5">Dirección</dt>
                    <dd class="col-sm-7">{{ $order->address }}</dd>
                    <dt class="col-sm-5">Gobernación</dt>
                    <dd class="col-sm-7">{{ $order->governorate }}</dd>
                    <dt class="col-sm-5">Diésel</dt>
                    <dd class="col-sm-7">{{ Display::decimal($order->liters) }} L <span class="small text-body-secondary">(no se cobra a una tarjeta de combustible)</span></dd>
                    <dt class="col-sm-5">Ventana preferida</dt>
                    <dd class="col-sm-7">{{ $window($order->preferred_start_at, $order->preferred_end_at) }}</dd>
                    <dt class="col-sm-5">Ventana programada</dt>
                    <dd class="col-sm-7">{{ $window($order->scheduled_start_at, $order->scheduled_end_at) }}</dd>
                    <dt class="col-sm-5">Camión</dt>
                    <dd class="col-sm-7">{{ $order->assigned_truck ?? '—' }}</dd>
                    @if ($order->delivered_at)
                        <dt class="col-sm-5">Entregado el</dt>
                        <dd class="col-sm-7">{{ Display::businessTime($order->delivered_at) }}</dd>
                    @endif
                    @if ($order->cancel_reason !== null)
                        <dt class="col-sm-5">Motivo de cancelación</dt>
                        <dd class="col-sm-7">{{ $order->cancel_reason }}</dd>
                    @endif
                </dl>
                <p class="small text-body-secondary mt-2 mb-0">Los horarios son hora de Beirut.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Timeline</h2>
                <ol class="list-unstyled mb-0" data-delivery-timeline>
                    @foreach ($order->statusHistory as $step)
                        <li class="d-flex gap-2 align-items-baseline mb-2">
                            @include('partials.delivery-status-badge', ['status' => $step->to_status])
                            <span>
                                {{ $step->from_status === null ? 'Solicitada' : ucfirst($step->to_status->displayLabel()) }} por {{ $step->changedBy->name }}
                                <span class="d-block small text-body-secondary">{{ Display::businessTime($step->changed_at) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
</div>

@if ($advanceTo !== [] || $canCancel)
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5">Siguiente paso</h2>
            <div class="row g-4">
                @foreach ($advanceTo as $next)
                    <div class="col-lg-6">
                        <form method="POST" action="{{ route('deliveries.status', $order) }}" data-delivery-action
                              @if ($next === DeliveryStatus::Delivered) data-delivery-confirm="¿Marcar la orden #{{ $order->id }} como entregada? Esto es definitivo." @endif>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="expected_status" value="{{ $status->value }}">
                            <input type="hidden" name="status" value="{{ $next->value }}">

                            @if ($next === DeliveryStatus::Scheduled)
                                <h3 class="h6">Programar</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <x-form.input type="datetime-local" name="scheduled_start_at" label="Inicio de ventana" :min="$minLocal" required />
                                    </div>
                                    <div class="col-md-6">
                                        <x-form.input type="datetime-local" name="scheduled_end_at" label="Fin de ventana" :min="$minLocal" required />
                                    </div>
                                </div>
                                <x-form.input name="assigned_truck" label="Camión" maxlength="60" required help="Por ejemplo TRK-04." />
                                <button type="submit" class="btn btn-primary">Programar entrega</button>
                            @elseif ($next === DeliveryStatus::OutForDelivery)
                                <h3 class="h6">Despachar</h3>
                                <p class="small text-body-secondary">El camión {{ $order->assigned_truck }} sale con el diésel.</p>
                                <button type="submit" class="btn btn-primary">Marcar en camino</button>
                            @else
                                <h3 class="h6">Entregar</h3>
                                <p class="small text-body-secondary">Registra la hora de entrega como ahora. Las órdenes entregadas son definitivas.</p>
                                <button type="submit" class="btn btn-success">Marcar entregada</button>
                            @endif
                        </form>
                    </div>
                @endforeach

                @if ($canCancel)
                    <div class="col-lg-6">
                        <form method="POST" action="{{ route('deliveries.status', $order) }}" data-delivery-action
                              data-delivery-confirm="¿Cancelar la orden #{{ $order->id }}? Esto es definitivo.">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="expected_status" value="{{ $status->value }}">
                            <input type="hidden" name="status" value="{{ DeliveryStatus::Cancelled->value }}">
                            <h3 class="h6">Cancelar</h3>
                            <x-form.input name="reason" label="Motivo" maxlength="255" required />
                            <button type="submit" class="btn btn-outline-danger">Cancelar orden</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@elseif ($status->isTerminal())
    <p class="text-body-secondary">Esta orden está {{ $status->displayLabel() }}. Es definitiva y ya no puede cambiar.</p>
@else
    <p class="text-body-secondary">El personal del distribuidor avanza la orden. Ya no puede cancelarse aquí una vez programada; contacta al personal del distribuidor.</p>
@endif
