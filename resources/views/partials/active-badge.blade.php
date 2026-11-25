{{-- Expects $active (bool). Status is shown in words, not colour alone. --}}
@if ($active)
    <span class="badge text-bg-success">Activo</span>
@else
    <span class="badge text-bg-secondary">Inactivo</span>
@endif
