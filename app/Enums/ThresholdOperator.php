<?php

namespace App\Enums;

use Brick\Math\BigDecimal;

/**
 * How a quota_warning rule compares the card's usage percentage with its
 * threshold (D12): at least (>=), at most (<=) or exactly (==).
 */
enum ThresholdOperator: string
{
    case Gte = 'gte';
    case Lte = 'lte';
    case Eq = 'eq';

    public function symbol(): string
    {
        return match ($this) {
            self::Gte => '≥',
            self::Lte => '≤',
            self::Eq => '=',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Gte => 'Al menos',
            self::Lte => 'Como máximo',
            self::Eq => 'Igual a',
        };
    }

    /** Whether $percent (a decimal string) satisfies this operator against $threshold. */
    public function satisfiedBy(string $percent, string $threshold): bool
    {
        $left = BigDecimal::of($percent);
        $right = BigDecimal::of($threshold);

        return match ($this) {
            self::Gte => $left->isGreaterThanOrEqualTo($right),
            self::Lte => $left->isLessThanOrEqualTo($right),
            self::Eq => $left->isEqualTo($right),
        };
    }
}
