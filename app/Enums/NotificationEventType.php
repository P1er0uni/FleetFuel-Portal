<?php

namespace App\Enums;

/**
 * The seven events a notification rule can listen to
 * (docs/sdd/fase-2-importacion-notificaciones/spec.md, Part B). Only
 * quota_warning compares a threshold; the rest fire on every occurrence.
 */
enum NotificationEventType: string
{
    case QuotaExceeded = 'quota_exceeded';
    case QuotaWarning = 'quota_warning';
    case CardBlocked = 'card_blocked';
    case AnomalyDetected = 'anomaly_detected';
    case DeliveryCompleted = 'delivery_completed';
    case DeliveryCancelled = 'delivery_cancelled';
    case FleetRuleViolated = 'fleet_rule_violated';

    public function label(): string
    {
        return match ($this) {
            self::QuotaExceeded => 'Cuota excedida',
            self::QuotaWarning => 'Aviso de cuota',
            self::CardBlocked => 'Tarjeta bloqueada',
            self::AnomalyDetected => 'Anomalía detectada',
            self::DeliveryCompleted => 'Entrega completada',
            self::DeliveryCancelled => 'Entrega cancelada',
            self::FleetRuleViolated => 'Regla de flota incumplida',
        };
    }

    /** Whether a rule's threshold_value/threshold_operator applies. */
    public function usesThreshold(): bool
    {
        return $this === self::QuotaWarning;
    }
}
