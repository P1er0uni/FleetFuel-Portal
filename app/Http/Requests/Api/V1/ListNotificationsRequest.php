<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\NotificationEventType;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/notifications: the caller's own inbox, filterable by read state
 * and event type, paginated, unknown query parameters refused.
 */
class ListNotificationsRequest extends PaginatedListRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'status' => ['nullable', 'string', Rule::in(['read', 'unread'])],
            'event_type' => ['nullable', 'string', Rule::enum(NotificationEventType::class)],
        ];
    }

    public function status(): ?string
    {
        $value = $this->validated('status');

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function eventType(): ?string
    {
        $value = $this->validated('event_type');

        return $value === null || $value === '' ? null : (string) $value;
    }
}
