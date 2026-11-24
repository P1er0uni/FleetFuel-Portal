@use('App\Support\Display')
@use('App\Support\Redact')

{{--
    Expects $transactions (with company, station, product, fuelCard loaded), $showCompany and $showStation.
    Optional: $showRateSource (the USD/LBP rate's provenance) and $emptyMessage.
--}}
@php($showRateSource ??= false)
@if ($transactions->isEmpty())
    <p class="text-body-secondary">{{ $emptyMessage ?? 'Todavía no hay compras.' }}</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle bg-body">
            <thead>
                <tr>
                    <th scope="col">Hora (Beirut)</th>
                    @if ($showCompany)
                        <th scope="col">Empresa</th>
                    @endif
                    @if ($showStation)
                        <th scope="col">Estación</th>
                    @endif
                    <th scope="col">Tarjeta</th>
                    <th scope="col">Producto</th>
                    <th scope="col" class="text-end">Litros</th>
                    <th scope="col" class="text-end">Monto (USD)</th>
                    <th scope="col" class="text-end">Monto (LBP)</th>
                    @if ($showRateSource)
                        <th scope="col">Origen de la tasa</th>
                    @endif
                    <th scope="col"><span class="visually-hidden">Detalles</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transactions as $transaction)
                    <tr>
                        <td class="text-nowrap">{{ Display::businessTime($transaction->transacted_at) }}</td>
                        @if ($showCompany)
                            <td>{{ $transaction->company->name }}</td>
                        @endif
                        @if ($showStation)
                            <td>{{ $transaction->station->name }}</td>
                        @endif
                        <td class="font-monospace">{{ Redact::cardNumber($transaction->fuelCard->card_no) }}</td>
                        <td>{{ $transaction->product->name }}</td>
                        <td class="text-end text-nowrap">{{ Display::decimal($transaction->liters) }}</td>
                        <td class="text-end text-nowrap">{{ Display::decimal($transaction->amount_usd) }}</td>
                        <td class="text-end text-nowrap">{{ Display::decimal($transaction->amount_lbp) }}</td>
                        @if ($showRateSource)
                            <td>@include('partials.rate-source-badge', ['source' => $transaction->rate_source])</td>
                        @endif
                        <td><a href="{{ route('transactions.show', $transaction->id) }}">Detalles<span class="visually-hidden"> de la compra {{ $transaction->id }}</span></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
