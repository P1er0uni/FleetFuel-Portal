@extends('layouts.app')

@section('title', 'Solicitar entrega')

@section('content')
    @php
        // Suggestions only; any governorate name up to 80 characters is accepted.
        $governorates = ['Akkar', 'Baalbek-Hermel', 'Beirut', 'Bekaa', 'Keserwan-Jbeil', 'Mount Lebanon', 'Nabatieh', 'North', 'South'];
        $minLocal = now()->setTimezone(config('fleetfuel.business_timezone'))->format('Y-m-d\TH:i');
    @endphp

    <h1 class="h3 mb-3">Solicitar una entrega de diésel</h1>

    <p>
        Empresa: <strong>{{ $company->name }}</strong>
        @if (auth()->user()->isAdmin())
            · <a href="{{ route('deliveries.create') }}">elegir otra empresa</a>
        @endif
    </p>

    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('deliveries.store') }}" class="card card-body shadow-sm">
                @csrf
                @if (auth()->user()->isAdmin())
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                @endif
                @error('company_id')
                    <div class="alert alert-danger" role="alert">{{ $message }}</div>
                @enderror

                <div class="mb-3">
                    <label for="address" class="form-label">Dirección de entrega<span class="text-danger" aria-hidden="true"> *</span></label>
                    <textarea id="address" name="address" rows="2" maxlength="500" required
                              @class(['form-control', 'is-invalid' => $errors->has('address')])
                              @error('address') aria-describedby="address-error" @enderror>{{ old('address') }}</textarea>
                    @error('address')
                        <div id="address-error" class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <x-form.input name="governorate" label="Gobernación" list="governorate-options" maxlength="80" required />
                <datalist id="governorate-options">
                    @foreach ($governorates as $governorate)
                        <option value="{{ $governorate }}"></option>
                    @endforeach
                </datalist>

                <x-form.input name="liters" label="Diésel (litros)" inputmode="decimal" required
                              help="Hasta dos decimales. Las entregas no se cargan a una tarjeta de combustible." />

                <div class="row">
                    <div class="col-md-6">
                        <x-form.input type="datetime-local" name="preferred_start_at" label="Preferido desde (hora de Beirut)" :min="$minLocal" required />
                    </div>
                    <div class="col-md-6">
                        <x-form.input type="datetime-local" name="preferred_end_at" label="Preferido hasta (hora de Beirut)" :min="$minLocal" required />
                    </div>
                </div>
                <p class="form-text mt-0">La ventana debe empezar en el futuro. El personal del distribuidor confirma la ventana real y el camión al programar la orden.</p>

                <div>
                    <button type="submit" class="btn btn-primary">Solicitar entrega</button>
                    <a class="btn btn-link" href="{{ route('deliveries.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
