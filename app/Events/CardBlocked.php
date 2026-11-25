<?php

namespace App\Events;

use App\Enums\NotificationEventType;
use App\Support\NotificationEntity;

/** A fuel card was blocked. */
final class CardBlocked extends DomainNotificationEvent
{
    public function __construct(
        public int $cardId,
        public int $companyId,
        public ?int $targetFleetId,
        public string $cardNo,
    ) {}

    public function type(): NotificationEventType
    {
        return NotificationEventType::CardBlocked;
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
        return ['fuel_card_id' => $this->cardId];
    }

    public function title(): string
    {
        return 'Tarjeta bloqueada';
    }

    public function message(): string
    {
        return 'La tarjeta '.substr($this->cardNo, -4).' fue bloqueada.';
    }
}
