<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Deliveries\TransitionDeliveryRequest as WebTransitionDeliveryRequest;
use App\Models\User;
use App\Rules\OffsetTimestamp;
use Carbon\CarbonImmutable;

/**
 * PATCH /api/v1/delivery-orders/{id}/status. The route admits a token with
 * either delivery ability; which one counts depends on the role: an admin
 * needs deliveries:status, a company manager deliveries:write (and can
 * then only cancel their own pending order). Unknown fields are refused
 * and times are offset-bearing ISO 8601 instants.
 */
class TransitionDeliveryRequest extends WebTransitionDeliveryRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        $user = $this->user();

        return parent::authorize()
            && $user instanceof User
            && $user->tokenCan($user->isAdmin() ? 'deliveries:status' : 'deliveries:write');
    }

    /**
     * The API keeps the documented English messages (SetApiLocale).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_start_at.prohibited' => 'A delivery window is only sent when scheduling.',
            'scheduled_end_at.prohibited' => 'A delivery window is only sent when scheduling.',
            'assigned_truck.prohibited' => 'A truck is only sent when scheduling.',
            'reason.prohibited' => 'A reason is only sent when cancelling.',
            'reason.required' => 'Give a reason for the cancellation.',
            'assigned_truck.required' => 'Assign a truck.',
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function timeRules(): array
    {
        return [new OffsetTimestamp];
    }

    protected function time(string $key): CarbonImmutable
    {
        $time = OffsetTimestamp::parse($this->validated($key));
        assert($time !== null);

        return $time;
    }
}
