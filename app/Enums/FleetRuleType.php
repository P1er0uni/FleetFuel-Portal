<?php

namespace App\Enums;

/**
 * The rule types a fleet may define (docs/sdd/fase-1-flotas/spec.md).
 * One rule per type per fleet (unique key); an inactive rule is ignored.
 */
enum FleetRuleType: string
{
    case AllowedProducts = 'allowed_products';
    case AllowedStations = 'allowed_stations';
    case MaxLitersPerTransaction = 'max_liters_per_transaction';
    case MaxTransactionsPerDay = 'max_transactions_per_day';

    /** Spanish label for the web screens. */
    public function label(): string
    {
        return match ($this) {
            self::AllowedProducts => 'Productos permitidos',
            self::AllowedStations => 'Estaciones permitidas',
            self::MaxLitersPerTransaction => 'Máximo de litros por transacción',
            self::MaxTransactionsPerDay => 'Máximo de transacciones por día',
        };
    }

    /** Whether rule_value is a JSON list of model IDs (true) or a decimal string (false). */
    public function isListRule(): bool
    {
        return $this === self::AllowedProducts || $this === self::AllowedStations;
    }
}
