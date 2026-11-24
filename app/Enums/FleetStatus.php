<?php

namespace App\Enums;

/**
 * A fleet can be switched back and forth between active and inactive.
 * An inactive fleet keeps its members and history; its rules stop applying
 * to new purchases only when the fleet itself is deleted (members ungroup).
 */
enum FleetStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /** Spanish label for the web screens. */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Inactive => 'Inactiva',
        };
    }
}
