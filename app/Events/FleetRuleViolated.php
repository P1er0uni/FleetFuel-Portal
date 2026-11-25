<?php

namespace App\Events;

use App\Enums\NotificationEventType;
use App\Support\NotificationEntity;

/** A purchase was refused by one of the card's fleet rules. */
final class FleetRuleViolated extends DomainNotificationEvent
{
    public function __construct(
        public int $cardId,
        public int $companyId,
        public ?int $targetFleetId,
        public string $rule,
    ) {}

    public function type(): NotificationEventType
    {
        return NotificationEventType::FleetRuleViolated;
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
        return ['fuel_card_id' => $this->cardId, 'rule' => $this->rule];
    }

    public function title(): string
    {
        return 'Regla de flota incumplida';
    }

    public function message(): string
    {
        return "Una compra de la tarjeta #{$this->cardId} fue rechazada por una regla de flota ({$this->rule}).";
    }
}
