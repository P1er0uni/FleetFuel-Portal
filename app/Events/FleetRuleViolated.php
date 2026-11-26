<?php

namespace App\Events;

use App\Enums\NotificationEventType;
use App\Support\NotificationEntity;

/**
 * A purchase broke one of the card's fleet rules. `enforcement` is 'block' when
 * the purchase was rejected, or 'warn' when it was allowed and only notified
 * (Fase 5). The notification event type stays the same for both.
 */
final class FleetRuleViolated extends DomainNotificationEvent
{
    public function __construct(
        public int $cardId,
        public int $companyId,
        public ?int $targetFleetId,
        public string $rule,
        public string $enforcement = 'block',
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
        return ['fuel_card_id' => $this->cardId, 'rule' => $this->rule, 'enforcement' => $this->enforcement];
    }

    public function title(): string
    {
        return $this->enforcement === 'warn' ? 'Aviso de regla de flota' : 'Regla de flota incumplida';
    }

    public function message(): string
    {
        $suffix = $this->enforcement === 'warn' ? ' (aviso: la compra se permitió)' : '';

        return "Una compra de la tarjeta #{$this->cardId} incumplió una regla de flota ({$this->rule}){$suffix}.";
    }
}
