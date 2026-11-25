@extends('layouts.app')

@use('App\Enums\CardStatus')
@use('App\Enums\CompanyStatus')
@use('App\Support\Display')

@section('title', $card ? 'Cambiar asignación de tarjeta' : 'Emitir tarjeta')

@section('content')
    @php
        $used ??= false;
        $locked = $card !== null && ($used || $card->status === CardStatus::Archived);
        $vehicleOptions = $vehicles->mapWithKeys(fn ($vehicle) => [
            $vehicle->id => $vehicle->plate_no.' · '.($vehicle->fuel_type->value === 'diesel' ? 'Diésel' : 'Gasolina').' · '.Display::decimal($vehicle->tank_capacity_l).' L'.($vehicle->is_active ? '' : ' (inactivo)'),
        ]);
        $driverOptions = $drivers->mapWithKeys(fn ($driver) => [
            $driver->id => $driver->name.' · '.$driver->license_no.($driver->is_active ? '' : ' (inactivo)'),
        ]);
        $productOptions = $products->mapWithKeys(fn ($product) => [
            $product->id => $product->name.' ('.$product->code.')'.($product->is_active ? '' : ' (inactivo)'),
        ]);
    @endphp

    <h1 class="h3 mb-3">
        @if ($card)
            Cambiar asignación de la tarjeta <span class="font-monospace">{{ $card->card_no }}</span>
        @else
            Emitir una tarjeta de combustible
        @endif
    </h1>

    <p>
        Empresa: <strong>{{ $company->name }}</strong>
        @if (! $card && auth()->user()->isAdmin())
            · <a href="{{ route('cards.create') }}">elegir otra empresa</a>
        @endif
    </p>

    @if ($company->status !== CompanyStatus::Active)
        <div class="alert alert-warning" role="alert">Esta empresa está inactiva, así que su flota es de solo lectura.</div>
    @endif
    @if ($card && $used)
        <div class="alert alert-info" role="status">
            Esta tarjeta ya se usó, así que su vehículo, conductor y restricción de producto quedan bloqueados.
            Los límites y el bloqueo siguen disponibles en la <a href="{{ route('cards.show', $card) }}">página de la tarjeta</a>.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ $card ? route('cards.update', $card) : route('cards.store') }}" class="card card-body shadow-sm">
                @csrf
                @if ($card)
                    @method('PUT')
                @elseif (auth()->user()->isAdmin())
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                @endif

                <fieldset @disabled($locked)>
                    <x-form.select name="vehicle_id" label="Vehículo" :options="$vehicleOptions" :selected="$card?->vehicle_id"
                                   placeholder="Sin vehículo" help="Opcional. El tipo de combustible del vehículo limita qué productos puede comprar la tarjeta." />
                    @if ($vehicles->isEmpty())
                        <p class="form-text mt-n2">Esta empresa aún no tiene vehículos activos. <a href="{{ route('vehicles.create') }}">Agrega un vehículo</a>, o emite la tarjeta sin uno.</p>
                    @endif

                    <x-form.select name="driver_id" label="Conductor" :options="$driverOptions" :selected="$card?->driver_id"
                                   placeholder="Sin conductor" help="Opcional." />
                    @if ($drivers->isEmpty())
                        <p class="form-text mt-n2">Esta empresa aún no tiene conductores activos. <a href="{{ route('drivers.create') }}">Agrega un conductor</a>, o emite la tarjeta sin uno.</p>
                    @endif

                    <x-form.select name="allowed_product_id" label="Restricción de producto" :options="$productOptions" :selected="$card?->allowed_product_id"
                                   placeholder="Cualquier producto que el vehículo pueda usar" />

                    <x-form.select name="fleet_id" label="Flota (opcional)" :options="$fleets->mapWithKeys(fn ($f) => [$f->id => $f->name])" :selected="$card?->fleet_id"
                                   placeholder="Sin flota" help="La tarjeta hereda las reglas de la flota y suma a su cuota mensual." />

                    @unless ($card)
                        <div class="row">
                            <div class="col-md-6">
                                <x-form.input name="monthly_limit_l" label="Límite mensual (litros)" inputmode="decimal"
                                              help="Déjalo vacío para no poner límite; 0 no permite compras." />
                            </div>
                            <div class="col-md-6">
                                <x-form.input name="monthly_limit_usd" label="Límite mensual (USD)" inputmode="decimal"
                                              help="Déjalo vacío para no poner límite; 0 no permite compras." />
                            </div>
                        </div>
                    @endunless

                    <div>
                        <button type="submit" class="btn btn-primary">{{ $card ? 'Guardar asignación' : 'Emitir tarjeta' }}</button>
                        <a class="btn btn-link" href="{{ $card ? route('cards.show', $card) : route('cards.index') }}">Cancelar</a>
                    </div>
                </fieldset>
            </form>
        </div>
    </div>
@endsection
