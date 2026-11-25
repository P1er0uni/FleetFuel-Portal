@php($bellUnread = $bellUnread ?? 0)
@php($bellRecent = $bellRecent ?? collect())

<div class="notification-bell px-2 mb-3"
     data-unread-url="{{ route('notifications.unread-count') }}">
    <button class="btn btn-link text-white text-decoration-none p-0 d-flex align-items-center w-100" type="button"
            data-bs-toggle="collapse" data-bs-target="#notification-dropdown" aria-expanded="false"
            aria-controls="notification-dropdown">
        <span>Notificaciones</span>
        <span class="badge text-bg-danger ms-2" data-bell-count="{{ $bellUnread }}" @if ($bellUnread === 0) hidden @endif>{{ $bellUnread }}</span>
    </button>

    <div class="collapse mt-2" id="notification-dropdown">
        <div class="bg-white text-dark rounded p-2 small">
            @forelse ($bellRecent as $notification)
                <div class="border-bottom py-1">
                    <div class="fw-semibold">{{ $notification->title }}</div>
                    <div class="text-body-secondary">{{ $notification->message }}</div>
                </div>
            @empty
                <p class="mb-1 text-body-secondary">Sin notificaciones.</p>
            @endforelse
            <a class="d-inline-block mt-2" href="{{ route('notifications.index') }}">Ver todas</a>
        </div>
    </div>
</div>

<script>
    (function () {
        var bell = document.querySelector('.notification-bell');
        if (bell === null) {
            return;
        }
        var badge = bell.querySelector('[data-bell-count]');
        var url = bell.getAttribute('data-unread-url');

        function refresh() {
            fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (data === null || badge === null) {
                        return;
                    }
                    badge.textContent = data.count;
                    badge.hidden = data.count === 0;
                })
                .catch(function () { /* offline: keep the last count */ });
        }

        window.setInterval(refresh, 60000);
    })();
</script>
