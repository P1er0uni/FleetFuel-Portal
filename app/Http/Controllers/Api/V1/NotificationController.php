<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The caller's own notification inbox (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * B4): list with filters, unread count, mark one read, mark all read. Every
 * query is scoped to the authenticated user.
 */
class NotificationController extends Controller
{
    use RespondsWithPages;

    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $page = Notification::query()
            ->forUser($this->user($request))
            ->when($request->status() === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($request->status() === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($request->eventType() !== null, fn ($query) => $query->where('event_type', $request->eventType()))
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return $this->pageResponse($page, NotificationResource::class, $request);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::query()->forUser($this->user($request))->whereNull('read_at')->count();

        return response()->json(['data' => ['unread_count' => $count]]);
    }

    public function read(Request $request, int $notification): JsonResponse
    {
        $record = Notification::query()->forUser($this->user($request))->findOrFail($notification);

        if ($record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }

        return NotificationResource::make($record)->response();
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = Notification::query()->forUser($this->user($request))->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['marked_read' => $count]]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
