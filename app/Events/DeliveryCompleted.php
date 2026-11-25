<?php

namespace App\Events;

use App\Enums\NotificationEventType;
use App\Support\NotificationEntity;

/** A diesel delivery order reached the delivered state. */
final class DeliveryCompleted extends DomainNotificationEvent
{
    public function __construct(
        public int $orderId,
        public int $companyId,
        public string $address,
    ) {}

    public function type(): NotificationEventType
    {
        return NotificationEventType::DeliveryCompleted;
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
        return ['delivery_order_id' => $this->orderId];
    }

    public function title(): string
    {
        return 'Entrega completada';
    }

    public function message(): string
    {
        return "La entrega #{$this->orderId} a «{$this->address}» se completó.";
    }
}
