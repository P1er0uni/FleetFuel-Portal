<?php

namespace App\Enums;

/**
 * How a fleet rule reacts when it is broken (Fase 5): block the purchase, or
 * allow it and send a notification.
 */
enum RuleEnforcement: string
{
    case Block = 'block';
    case Warn = 'warn';

    public function label(): string
    {
        return match ($this) {
            self::Block => 'Bloqueante',
            self::Warn => 'Solo aviso',
        };
    }
}
