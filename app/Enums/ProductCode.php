<?php

namespace App\Enums;

/**
 * The product codes accepted by the API contract (docs/05-API-CONTRACT.md).
 */
enum ProductCode: string
{
    case Ulp95 = 'ULP95';
    case Ulp98 = 'ULP98';
    case Diesel = 'DIESEL';

    public function label(): string
    {
        return match ($this) {
            self::Ulp95 => 'Sin plomo 95',
            self::Ulp98 => 'Sin plomo 98',
            self::Diesel => 'Diésel',
        };
    }

    public function fuelType(): FuelType
    {
        return match ($this) {
            self::Ulp95, self::Ulp98 => FuelType::Petrol,
            self::Diesel => FuelType::Diesel,
        };
    }
}
