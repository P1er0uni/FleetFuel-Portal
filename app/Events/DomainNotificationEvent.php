<?php

namespace App\Events;

use App\Enums\NotificationEventType;
use App\Support\NotificationEntity;

/**
 * Base of the seven domain events that feed notification rules
 * (docs/sdd/fase-2-importacion-notificaciones/spec.md, B2). Each event carries
 * IDs only; the listener turns them into notifications through the rules that
 * match its company, optional fleet and event type.
 */
abstract class DomainNotificationEvent
{
    abstract public function type(): NotificationEventType;

    abstract public function companyId(): int;

    /** The fleet the event belongs to, when it has one. */
    public function fleetId(): ?int
    {
        return null;
    }

    abstract public function entity(): NotificationEntity;

    /**
     * Extra values for the notification payload, such as a percentage.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [];
    }

    abstract public function title(): string;

    abstract public function message(): string;
}
