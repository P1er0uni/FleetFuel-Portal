{{-- Expects $type (fleet|vehicle|station) and $id. Toggles the favourite over AJAX. --}}
@php
    $isFavorite = auth()->check()
        && app(\App\Services\FavoriteService::class)->isFavorited(auth()->user(), $type, (int) $id);
@endphp
<button type="button" class="btn btn-sm btn-outline-warning favorite-star"
        data-type="{{ $type }}" data-id="{{ (int) $id }}"
        data-favorited="{{ $isFavorite ? '1' : '0' }}"
        data-url="{{ route('favorites.toggle') }}"
        data-token="{{ csrf_token() }}"
        aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
        title="Agregar o quitar de favoritos">
    <span class="favorite-star-icon" aria-hidden="true">{{ $isFavorite ? '★' : '☆' }}</span>
    <span class="visually-hidden">Agregar o quitar de favoritos</span>
</button>

@once
    @push('scripts')
        <script>
            (function () {
                if (window.__favoriteStarInit) { return; }
                window.__favoriteStarInit = true;

                document.addEventListener('click', function (event) {
                    var button = event.target.closest('.favorite-star');
                    if (button === null) { return; }

                    event.preventDefault();
                    var data = new FormData();
                    data.append('type', button.getAttribute('data-type'));
                    data.append('favoritable_id', button.getAttribute('data-id'));
                    data.append('_token', button.getAttribute('data-token'));

                    fetch(button.getAttribute('data-url'), {
                        method: 'POST',
                        body: data,
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        credentials: 'same-origin',
                    }).then(function (response) {
                        return response.ok ? response.json() : null;
                    }).then(function (payload) {
                        if (payload === null) { return; }
                        var icon = button.querySelector('.favorite-star-icon');
                        button.setAttribute('data-favorited', payload.favorited ? '1' : '0');
                        button.setAttribute('aria-pressed', payload.favorited ? 'true' : 'false');
                        if (icon !== null) { icon.textContent = payload.favorited ? '★' : '☆'; }
                    }).catch(function () {});
                });
            })();
        </script>
    @endpush
@endonce
