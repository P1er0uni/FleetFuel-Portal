<?php

namespace App\Events;

use App\Enums\NotificationEventType;
use App\Support\NotificationEntity;

/** A diesel delivery order was cancelled. */
final class DeliveryCancelled extends DomainNotificationEvent
{
    public function __construct(
        public int $orderId,
        public int $companyId,
        public string $reason,
    ) {}

    public function type(): NotificationEventType
    {
        return NotificationEventType::DeliveryCancelled;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function entity(): NotificationEntity
    {
        return NotificationEntity::make('delivery_order', $this->orderId);
    }

    public function context(): array
    {
        return ['delivery_order_id' => $this->orderId, 'reason' => $this->reason];
    }

    public function title(): string
    {
        return 'Entrega cancelada';
    }

    public function message(): string
    {
        return "La entrega #{$this->orderId} fue cancelada: {$this->reason}.";
    }
}
