<?php

namespace App\Events;

use App\Enums\NotificationEventType;
use App\Support\NotificationEntity;

/** A POS purchase was refused because it would exceed the card's monthly quota. */
final class QuotaExceeded extends DomainNotificationEvent
{
    public function __construct(
        public int $cardId,
        public int $companyId,
        public ?int $targetFleetId = null,
        public ?string $percent = null,
        public string $dimension = 'liters',
    ) {}

    public function type(): NotificationEventType
    {
        return NotificationEventType::QuotaExceeded;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function fleetId(): ?int
    {
        return $this->targetFleetId;
    }

    public function entity(): NotificationEntity
    {
        return NotificationEntity::make('fuel_card', $this->cardId);
    }

    public function context(): array
    {
        return ['fuel_card_id' => $this->cardId, 'dimension' => $this->dimension, 'percent' => $this->percent];
    }

    public function title(): string
    {
        return 'Cuota excedida';
    }

    public function message(): string
    {
        return "Una compra fue rechazada porque la tarjeta #{$this->cardId} supera su cuota mensual ({$this->dimension}).";
    }
}
