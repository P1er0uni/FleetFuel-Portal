{{-- Expects $status (App\Enums\CardStatus). --}}
@switch ($status)
    @case (App\Enums\CardStatus::Active)
        <span class="badge text-bg-success">Activa</span>
        @break
    @case (App\Enums\CardStatus::Blocked)
        <span class="badge text-bg-danger">Bloqueada</span>
        @break
    @default
        <span class="badge text-bg-secondary">Archivada</span>
@endswitch
