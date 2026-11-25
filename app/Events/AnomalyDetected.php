<?php

namespace App\Events;

use App\Enums\NotificationEventType;
use App\Support\NotificationEntity;

/**
 * A purchase came less than 30 minutes after the same vehicle's previous fill
 * (the rapid-fill rule of the anomalies report, D9).
 */
final class AnomalyDetected extends DomainNotificationEvent
{
    public function __construct(
        public int $vehicleId,
        public int $cardId,
        public int $companyId,
        public ?int $targetFleetId,
        public int $minutes,
    ) {}

    public function type(): NotificationEventType
    {
        return NotificationEventType::AnomalyDetected;
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
        return NotificationEntity::make('vehicle', $this->vehicleId);
    }

    public function context(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'fuel_card_id' => $this->cardId,
            'minutes' => $this->minutes,
        ];
    }

    public function title(): string
    {
        return 'Carga rápida detectada';
    }

    public function message(): string
    {
        return "El vehículo #{$this->vehicleId} registró dos cargas con {$this->minutes} minutos de diferencia.";
    }
}
