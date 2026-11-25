{{-- Expects $rate (?ExchangeRate): the USD/LBP rate in effect now. --}}
@use('App\Enums\RateSource')
@use('App\Support\Display')
@if ($rate)
    <p class="small text-body-secondary mb-1">
        El USD indicativo usa la tasa {{ strtolower($rate->source->label()) }} de
        {{ Display::decimal($rate->rate, 8) }} LBP por USD, con vigencia desde {{ Display::businessTime($rate->effective_at) }} (hora de Beirut).
        Las compras guardan sus propios montos en LBP y USD al momento de realizarse.
    </p>
    @if ($rate->source === RateSource::Provider)
        @include('partials.rate-attribution')
    @endif
@else
    <p class="small text-warning-emphasis mb-1">Ahora mismo no hay una tasa USD/LBP válida, así que no se muestran los precios indicativos en USD.</p>
@endif
