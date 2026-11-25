<?php

namespace App\Http\Controllers\Web;

use App\Enums\NotificationEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\ListNotificationsRequest;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A manager's in-app notification inbox (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * B3): the paginated list with filters, the unread badge count, marking one
 * read and marking all read. Every query is scoped to the signed-in user.
 */
class NotificationController extends Controller
{
    public function index(ListNotificationsRequest $request): View
    {
        $user = $request->actor();
        $status = $request->filterValue('status');
        $type = $request->filterValue('event_type');

        $notifications = Notification::query()
            ->forUser($user)
            ->with('rule')
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($type !== null, fn ($query) => $query->where('event_type', $type))
            ->orderByDesc('id')
            ->paginate(ListNotificationsRequest::PER_PAGE)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filters' => ['status' => $status, 'event_type' => $type],
            'types' => NotificationEventType::cases(),
        ]);
    }

    /** Polled by the bell every 60 s. */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        return response()->json([
            'count' => Notification::query()->forUser($user)->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, int $notification): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $record = Notification::query()->forUser($user)->findOrFail($notification);
        Gate::authorize('update', $record);

        if ($record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }

        return back()->with('status', 'Notificación marcada como leída.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        Notification::query()->forUser($user)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Todas las notificaciones se marcaron como leídas.');
    }
}
