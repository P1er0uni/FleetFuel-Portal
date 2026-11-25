<?php

namespace App\Events;

use App\Enums\NotificationEventType;
use App\Support\NotificationEntity;

/**
 * An accepted purchase left a card at a percentage of its monthly liter
 * quota. The rule's threshold decides whether this warns or stays quiet.
 */
final class QuotaWarning extends DomainNotificationEvent
{
    public function __construct(
        public int $cardId,
        public int $companyId,
        public ?int $targetFleetId,
        public string $percent,
        public string $usedLiters,
        public string $limitLiters,
    ) {}

    public function type(): NotificationEventType
    {
        return NotificationEventType::QuotaWarning;
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
        return [
            'fuel_card_id' => $this->cardId,
            'percent' => $this->percent,
            'used_liters' => $this->usedLiters,
            'limit_liters' => $this->limitLiters,
        ];
    }

    public function title(): string
    {
        return 'Aviso de cuota';
    }

    public function message(): string
    {
        return "La tarjeta #{$this->cardId} alcanzó el {$this->percent}% de su cuota mensual de {$this->limitLiters} L ({$this->usedLiters} L usados).";
    }
}
