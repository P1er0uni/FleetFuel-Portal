{{-- Expects $rate (the row), $current (?ExchangeRate in effect) and $now. Words, not colour alone. --}}
@if ($current && $rate->is($current))
    <span class="badge text-bg-success">En uso</span>
@elseif ($rate->effective_at->isAfter($now))
    <span class="badge text-bg-info">Programada</span>
@elseif ($rate->isEligibleAt($now))
    <span class="badge text-bg-light border">Válida, no en uso</span>
@else
    <span class="badge text-bg-secondary">Expirada</span>
@endif
