<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * How audit rows are shown on the audit screen (docs/06-UI-SPEC.md, "Audit").
 *
 * Services audit explicitly chosen business fields only, so no secret should
 * ever be stored. The screen still redacts as a second line of defense: a
 * field named like a credential is never shown, and card numbers are masked
 * as they are on every other list.
 */
final class AuditDisplay
{
    /** Field names that are never displayed: password, *_token, secret, api_key… */
    private const SECRET_FIELD = '/(^|_)(password|secret|token|api_key)$/i';

    /** Morph-map alias => [label, route of its page or null]. */
    private const ENTITIES = [
        'company' => ['Empresa', 'companies.edit'],
        'station' => ['Estación', 'stations.edit'],
        'product' => ['Producto', 'products.edit'],
        'product_price' => ['Precio de producto', null],
        'exchange_rate' => ['Tasa de cambio', null],
        'vehicle' => ['Vehículo', 'vehicles.edit'],
        'driver' => ['Conductor', 'drivers.edit'],
        'fuel_card' => ['Tarjeta de combustible', 'cards.show'],
        'fuel_transaction' => ['Compra', 'transactions.show'],
        'delivery_order' => ['Orden de entrega', 'deliveries.show'],
        'fleet' => ['Flota', 'fleets.show'],
        'fleet_rule' => ['Regla de flota', null],
        'notification_rule' => ['Regla de notificación', 'notifications.rules.edit'],
        'user' => ['Cuenta de usuario', null],
    ];

    /** Audit action codes => Spanish label (fallback: readable code). */
    private const ACTIONS = [
        'card.created' => 'Tarjeta creada',
        'card.assignment_changed' => 'Asignación de tarjeta cambiada',
        'card.limits_changed' => 'Límites de tarjeta cambiados',
        'card.status_changed' => 'Estado de tarjeta cambiado',
        'delivery_order.status_changed' => 'Estado de orden de entrega cambiado',
        'exchange_rate.override_created' => 'Ajuste de tasa de cambio creado',
        'vehicle.activated' => 'Vehículo activado',
        'vehicle.deactivated' => 'Vehículo desactivado',
        'driver.activated' => 'Conductor activado',
        'driver.deactivated' => 'Conductor desactivado',
        'product_price.published' => 'Precio de producto publicado',
        'company.activated' => 'Empresa activada',
        'company.deactivated' => 'Empresa desactivada',
        'station.activated' => 'Estación activada',
        'station.deactivated' => 'Estación desactivada',
        'product.activated' => 'Producto activado',
        'product.deactivated' => 'Producto desactivado',
        'user.created' => 'Usuario creado',
        'user.updated' => 'Usuario actualizado',
        'user.role_changed' => 'Rol o alcance de usuario cambiado',
        'user.password_reset' => 'Contraseña de usuario restablecida',
        'user.password_changed' => 'Contraseña de usuario cambiada',
        'user.activated' => 'Usuario activado',
        'user.deactivated' => 'Usuario desactivado',
        'fleet.created' => 'Flota creada',
        'fleet.updated' => 'Flota actualizada',
        'fleet.status_changed' => 'Estado de flota cambiado',
        'fleet.deleted' => 'Flota eliminada',
        'fleet.assignment_changed' => 'Asignación de flota cambiada',
        'fleet.rules_changed' => 'Reglas de flota cambiadas',
        'import.completed' => 'Importación masiva completada',
        'notification.sent' => 'Notificación registrada',
        'notification_rule.created' => 'Regla de notificación creada',
        'notification_rule.updated' => 'Regla de notificación actualizada',
        'notification_rule.deleted' => 'Regla de notificación eliminada',
    ];

    /**
     * One row per field that appears before or after the change. A side that
     * did not record the field is null (shown as a dash).
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @return list<array{field: string, before: ?string, after: ?string}>
     */
    public static function changes(?array $old, ?array $new): array
    {
        $old ??= [];
        $new ??= [];
        $rows = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $field = (string) $field;
            $rows[] = [
                'field' => $field,
                'before' => array_key_exists($field, $old) ? self::value($field, $old[$field]) : null,
                'after' => array_key_exists($field, $new) ? self::value($field, $new[$field]) : null,
            ];
        }

        return $rows;
    }

    public static function value(string $field, mixed $value): string
    {
        if (preg_match(self::SECRET_FIELD, $field) === 1) {
            return '[redactado]';
        }

        return match (true) {
            $value === null => 'ninguno',
            is_bool($value) => $value ? 'sí' : 'no',
            is_string($value) && str_ends_with($field, 'card_no') => Redact::cardNumber($value),
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }

    /** "card.limits_changed" becomes "Límites de tarjeta cambiados". */
    public static function action(string $action): string
    {
        return self::ACTIONS[$action] ?? Str::ucfirst(str_replace(['.', '_'], ' ', $action));
    }

    public static function entityLabel(string $alias): string
    {
        return self::ENTITIES[$alias][0] ?? Str::ucfirst(str_replace('_', ' ', $alias));
    }

    /** The record's page, when the screen has one. */
    public static function entityUrl(string $alias, int $id): ?string
    {
        $route = self::ENTITIES[$alias][1] ?? null;

        return $route === null ? null : route($route, $id);
    }

    /**
     * @return array<string, string> alias => label, for the entity filter
     */
    public static function entityOptions(): array
    {
        return array_map(fn (array $entity): string => $entity[0], self::ENTITIES);
    }
}
