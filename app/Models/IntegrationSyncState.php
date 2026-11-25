<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Last attempt/success of an external sync such as rates:sync, one row per
 * exchange-rate mode. last_error_code is a safe short code (null after a
 * clean success); next_attempt_at holds the provider's next-update time or
 * its 429 retry advice.
 */
#[Fillable(['name', 'last_attempt_at', 'last_success_at', 'last_error_code', 'next_attempt_at'])]
class IntegrationSyncState extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_attempt_at' => 'datetime',
            'last_success_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }

    /** The last error code in words for the admin status page. */
    public function errorDescription(): ?string
    {
        return match ($this->last_error_code) {
            null => null,
            'connection_failed' => 'No se pudo contactar al proveedor (error de conexión o timeout), incluso tras reintentos.',
            'server_error' => 'El proveedor siguió respondiendo con un error de servidor (HTTP 5xx), incluso tras reintentos.',
            'rate_limited' => 'El proveedor limitó las solicitudes de este servidor (HTTP 429); el próximo intento espera su indicación de reintento.',
            'http_error' => 'El proveedor rechazó la solicitud (estado HTTP inesperado).',
            'invalid_response', 'provider_error' => 'La respuesta del proveedor no fue una respuesta de tasa exitosa.',
            'unexpected_base' => 'La respuesta del proveedor no estaba basada en USD.',
            'invalid_rate' => 'La respuesta del proveedor no tenía una tasa LBP positiva utilizable.',
            'invalid_timestamp' => 'La respuesta del proveedor tenía una hora de observación ausente o futura.',
            'stale_observation' => 'La observación del proveedor ya tenía más de 72 horas de antigüedad.',
            'conflicting_observation' => 'El proveedor envió un valor distinto para una observación ya almacenada; se conservó el valor almacenado.',
            default => "Error de sincronización: {$this->last_error_code}.",
        };
    }
}
