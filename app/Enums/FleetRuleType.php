<?php

namespace App\Enums;

/**
 * The rule types a fleet may define (docs/sdd/fase-1-flotas/spec.md and
 * fase-5-reglas-limites-flota). One rule per type per fleet (unique key); an
 * inactive rule is ignored.
 */
enum FleetRuleType: string
{
    case AllowedProducts = 'allowed_products';
    case AllowedStations = 'allowed_stations';
    case MaxLitersPerTransaction = 'max_liters_per_transaction';
    case MaxTransactionsPerDay = 'max_transactions_per_day';
    case MaxLitersPerDay = 'max_liters_per_day';
    case MaxAmountPerTransactionUsd = 'max_amount_per_transaction_usd';
    case MaxAmountPerDayUsd = 'max_amount_per_day_usd';
    case AllowedTimeWindows = 'allowed_time_windows';

    public function label(): string
    {
        return match ($this) {
            self::AllowedProducts => 'Productos permitidos',
            self::AllowedStations => 'Estaciones permitidas',
            self::MaxLitersPerTransaction => 'Máximo de litros por transacción',
            self::MaxTransactionsPerDay => 'Máximo de transacciones por día',
            self::MaxLitersPerDay => 'Máximo de litros por día',
            self::MaxAmountPerTransactionUsd => 'Máximo de importe por transacción (USD)',
            self::MaxAmountPerDayUsd => 'Máximo de importe por día (USD)',
            self::AllowedTimeWindows => 'Días y horarios permitidos',
        };
    }

    /** Whether rule_value is a JSON list of model IDs. */
    public function isListRule(): bool
    {
        return $this === self::AllowedProducts || $this === self::AllowedStations;
    }

    /** Whether rule_value is {"value": "..."} with a decimal limit. */
    public function isNumericRule(): bool
    {
        return $this === self::MaxLitersPerTransaction
            || $this === self::MaxTransactionsPerDay
            || $this === self::MaxLitersPerDay
            || $this === self::MaxAmountPerTransactionUsd
            || $this === self::MaxAmountPerDayUsd;
    }

    /** Whether rule_value is {"windows": [...]} with allowed days and hours. */
    public function isWindowsRule(): bool
    {
        return $this === self::AllowedTimeWindows;
    }

    /** Unit shown next to a numeric rule's value. */
    public function unit(): string
    {
        return match ($this) {
            self::MaxLitersPerTransaction, self::MaxLitersPerDay => 'L',
            self::MaxTransactionsPerDay => 'transacciones',
            self::MaxAmountPerTransactionUsd, self::MaxAmountPerDayUsd => 'USD',
            default => '',
        };
    }
}
