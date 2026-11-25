<?php

namespace App\Http\Requests\Notifications;

use App\Enums\NotificationEventType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the notification page: read state and event type.
 */
class ListNotificationsRequest extends FormRequest
{
    public const PER_PAGE = 25;

    protected $redirectRoute = 'notifications.index';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Notification::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['read', 'unread'])],
            'event_type' => ['nullable', 'string', Rule::enum(NotificationEventType::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filterValue(string $key): ?string
    {
        $value = $this->validated($key);

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function actor(): User
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user;
    }
}
