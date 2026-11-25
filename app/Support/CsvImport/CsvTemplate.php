<?php

namespace App\Support\CsvImport;

/**
 * The CSV templates of the bulk import, shared by the web screens and the API
 * (docs/sdd/fase-2-importacion-notificaciones/spec.md). The header is exact:
 * an uploaded file whose first line differs is refused with a 422.
 *
 * Columns follow the real schema (D3): vehicle_type and license_expiry do not
 * exist and are omitted; a card's monthly_quota_liters maps to monthly_limit_l;
 * gasoline is accepted as a synonym of petrol.
 */
final class CsvTemplate
{
    public const VEHICLES = 'vehicles';

    public const DRIVERS = 'drivers';

    public const CARDS = 'cards';

    /** @var list<string> */
    public const TYPES = [self::VEHICLES, self::DRIVERS, self::CARDS];

    /** @var array<string, list<string>> */
    private const HEADERS = [
        self::VEHICLES => ['plate_no', 'fuel_type', 'tank_capacity_l', 'odometer_km', 'fleet_name'],
        self::DRIVERS => ['name', 'phone', 'license_no', 'fleet_name'],
        self::CARDS => ['card_no', 'vehicle_plate', 'driver_license', 'monthly_limit_l', 'status', 'fleet_name'],
    ];

    /** @var array<string, list<string>> */
    private const EXAMPLES = [
        self::VEHICLES => ['ABC-123', 'diesel', '200', '150000', 'Camiones'],
        self::DRIVERS => ['Juan Perez', '+56912345678', 'ABC12345', 'Camiones'],
        self::CARDS => ['FC-0001', 'ABC-123', 'ABC12345', '500', 'active', 'Camiones'],
    ];

    public static function isType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /**
     * @return list<string>
     */
    public static function header(string $type): array
    {
        return self::HEADERS[$type];
    }

    /**
     * @return list<string>
     */
    public static function example(string $type): array
    {
        return self::EXAMPLES[$type];
    }

    /** The downloadable template: the header followed by one example row. */
    public static function csv(string $type): string
    {
        $stream = fopen('php://temp', 'r+');
        assert($stream !== false);

        fputcsv($stream, self::header($type), ',', '"', '\\');
        fputcsv($stream, self::example($type), ',', '"', '\\');
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /** A stable file name such as plantilla-vehiculos.csv. */
    public static function filename(string $type): string
    {
        $names = [self::VEHICLES => 'vehiculos', self::DRIVERS => 'conductores', self::CARDS => 'tarjetas'];

        return 'plantilla-'.$names[$type].'.csv';
    }
}
