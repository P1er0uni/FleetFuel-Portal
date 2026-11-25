<?php

namespace App\Enums;

/**
 * How a notification rule delivers its message (D10). in_app fills the
 * managers' inbox, email sends a Mailable (array mailer in tests), and log
 * writes to audit_logs and the Laravel log.
 */
enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';
    case Log = 'log';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'En la aplicación',
            self::Email => 'Correo electrónico',
            self::Log => 'Registro (auditoría y log)',
        };
    }
}
