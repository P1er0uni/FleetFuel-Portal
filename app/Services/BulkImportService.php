<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Enums\CompanyStatus;
use App\Enums\FuelType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Fleet;
use App\Models\FuelCard;
use App\Models\User;
use App\Models\Vehicle;
use App\Rules\DecimalString;
use App\Support\CsvImport\CsvTemplate;
use App\Support\CsvImport\ImportResult;
use App\Support\Decimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bulk CSV import of vehicles, drivers and fuel cards
 * (docs/sdd/fase-2-importacion-notificaciones/spec.md, Part A).
 *
 * Shared by the web screen and the API. The header must match the template
 * exactly; each row is validated with the same rules as the individual forms
 * and written in its own transaction, so a bad row is skipped with its error
 * without losing the good ones (D4). `dry_run` validates everything without
 * writing. One audit row records the whole import, never one per row (D7).
 */
class BulkImportService
{
    /** Maximum data rows per file (D5). */
    public const MAX_ROWS = 5000;

    /** Maximum file size in bytes (5 MB, D5). */
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  list<array<string, mixed>>|null  $rows  API JSON body rows, one associative array per record.
     */
    public function import(
        string $type,
        Company $company,
        User $actor,
        bool $dryRun,
        ?string $csv = null,
        ?array $rows = null,
    ): ImportResult {
        if (! CsvTemplate::isType($type)) {
            throw ValidationException::withMessages(['type' => 'Tipo de importación desconocido.']);
        }

        $this->ensureCompanyActive($company);

        $records = $csv !== null ? $this->parseCsv($type, $csv) : $this->recordsFromRows($type, $rows ?? []);

        if (count($records) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'El archivo supera el máximo de '.self::MAX_ROWS.' filas.']);
        }

        $result = match ($type) {
            CsvTemplate::VEHICLES => $this->importVehicles($records, $company, $dryRun),
            CsvTemplate::DRIVERS => $this->importDrivers($records, $company, $dryRun),
            CsvTemplate::CARDS => $this->importCards($records, $company, $dryRun),
            default => throw new \LogicException('Tipo de importación desconocido.'),
        };

        $this->audit->record('import.completed', $company, $actor, $company->id, null, [
            'type' => $type,
            'total_rows' => $result->totalRows,
            'successful' => $result->successful,
            'failed' => $result->failed,
            'dry_run' => $dryRun,
        ]);

        return $result;
    }

    // ---------------------------------------------------------------- vehicles

    /**
     * @param  list<array{row: int, data: array<string, string>, extra: list<string>}>  $records
     */
    private function importVehicles(array $records, Company $company, bool $dryRun): ImportResult
    {
        $errors = [];
        $successful = 0;
        $failed = 0;
        $seen = [];

        foreach ($records as $record) {
            $row = $record['row'];
            $data = $record['data'];
            $rowErrors = $this->extraColumnErrors($record);

            $plate = Vehicle::normalizePlate((string) ($data['plate_no'] ?? ''));
            $fuelType = strtolower(trim((string) ($data['fuel_type'] ?? '')));
            $fuelType = $fuelType === 'gasoline' ? 'petrol' : $fuelType;

            [$fleetId, $fleetError] = $this->resolveFleet($company, $data['fleet_name'] ?? null);
            if ($fleetError !== null) {
                $rowErrors[] = ['field' => 'fleet_name', 'message' => $fleetError];
            }

            $rowErrors = [...$rowErrors, ...$this->validate([
                'plate_no' => $plate,
                'fuel_type' => $fuelType,
                'tank_capacity_l' => trim((string) ($data['tank_capacity_l'] ?? '')),
                'odometer_km' => $this->nullable($data['odometer_km'] ?? null),
            ], [
                'plate_no' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9 -]*$/', Rule::unique('vehicles', 'plate_no')],
                'fuel_type' => ['required', Rule::in(['petrol', 'diesel'])],
                'tank_capacity_l' => ['required', new DecimalString(8, allowZero: false)],
                'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            ])];

            if ($plate !== '' && isset($seen[$plate])) {
                $rowErrors[] = ['field' => 'plate_no', 'message' => 'Placa duplicada dentro del archivo.'];
            }
            $seen[$plate] = true;

            if ($rowErrors !== []) {
                $failed++;
                $errors = [...$errors, ...$this->tag($row, $rowErrors)];

                continue;
            }

            if (! $dryRun) {
                $writeError = $this->write(fn () => Vehicle::query()->forceCreate([
                    'company_id' => $company->id,
                    'plate_no' => $plate,
                    'fuel_type' => FuelType::from($fuelType),
                    'tank_capacity_l' => Decimal::normalize((string) $data['tank_capacity_l']),
                    'odometer_km' => $this->nullable($data['odometer_km'] ?? null),
                    'fleet_id' => $fleetId,
                    'is_active' => true,
                ]));

                if ($writeError !== null) {
                    $failed++;
                    $errors[] = ['row' => $row, 'field' => 'plate_no', 'message' => $writeError];

                    continue;
                }
            }

            $successful++;
        }

        return new ImportResult(CsvTemplate::VEHICLES, count($records), $successful, $failed, $errors, $dryRun);
    }

    // ----------------------------------------------------------------- drivers

    /**
     * @param  list<array{row: int, data: array<string, string>, extra: list<string>}>  $records
     */
    private function importDrivers(array $records, Company $company, bool $dryRun): ImportResult
    {
        $errors = [];
        $successful = 0;
        $failed = 0;
        $seen = [];

        foreach ($records as $record) {
            $row = $record['row'];
            $data = $record['data'];
            $rowErrors = $this->extraColumnErrors($record);

            $license = Driver::normalizeLicense((string) ($data['license_no'] ?? ''));

            [$fleetId, $fleetError] = $this->resolveFleet($company, $data['fleet_name'] ?? null);
            if ($fleetError !== null) {
                $rowErrors[] = ['field' => 'fleet_name', 'message' => $fleetError];
            }

            $rowErrors = [...$rowErrors, ...$this->validate([
                'name' => trim((string) ($data['name'] ?? '')),
                'phone' => $this->nullable($data['phone'] ?? null),
                'license_no' => $license,
            ], [
                'name' => ['required', 'string', 'max:120'],
                'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() -]+$/'],
                'license_no' => ['required', 'string', 'max:50',
                    Rule::unique('drivers', 'license_no')->where('company_id', $company->id)],
            ])];

            if ($license !== '' && isset($seen[$license])) {
                $rowErrors[] = ['field' => 'license_no', 'message' => 'Número de licencia duplicado dentro del archivo.'];
            }
            $seen[$license] = true;

            if ($rowErrors !== []) {
                $failed++;
                $errors = [...$errors, ...$this->tag($row, $rowErrors)];

                continue;
            }

            if (! $dryRun) {
                $writeError = $this->write(fn () => Driver::query()->forceCreate([
                    'company_id' => $company->id,
                    'name' => trim((string) $data['name']),
                    'license_no' => $license,
                    'phone' => $this->nullable($data['phone'] ?? null),
                    'fleet_id' => $fleetId,
                    'is_active' => true,
                ]));

                if ($writeError !== null) {
                    $failed++;
                    $errors[] = ['row' => $row, 'field' => 'license_no', 'message' => $writeError];

                    continue;
                }
            }

            $successful++;
        }

        return new ImportResult(CsvTemplate::DRIVERS, count($records), $successful, $failed, $errors, $dryRun);
    }

    // ------------------------------------------------------------------- cards

    /**
     * @param  list<array{row: int, data: array<string, string>, extra: list<string>}>  $records
     */
    private function importCards(array $records, Company $company, bool $dryRun): ImportResult
    {
        $errors = [];
        $successful = 0;
        $failed = 0;
        $seen = [];

        foreach ($records as $record) {
            $row = $record['row'];
            $data = $record['data'];
            $rowErrors = $this->extraColumnErrors($record);

            $cardNo = strtoupper(trim((string) ($data['card_no'] ?? '')));
            $status = strtolower(trim((string) ($data['status'] ?? 'active')));

            [$fleetId, $fleetError] = $this->resolveFleet($company, $data['fleet_name'] ?? null);
            if ($fleetError !== null) {
                $rowErrors[] = ['field' => 'fleet_name', 'message' => $fleetError];
            }

            [$vehicleId, $vehicleError] = $this->resolveVehicle($company, $data['vehicle_plate'] ?? null);
            if ($vehicleError !== null) {
                $rowErrors[] = ['field' => 'vehicle_plate', 'message' => $vehicleError];
            }

            [$driverId, $driverError] = $this->resolveDriver($company, $data['driver_license'] ?? null);
            if ($driverError !== null) {
                $rowErrors[] = ['field' => 'driver_license', 'message' => $driverError];
            }

            $rowErrors = [...$rowErrors, ...$this->validate([
                'card_no' => $cardNo,
                'monthly_limit_l' => $this->nullable($data['monthly_limit_l'] ?? null),
                'status' => $status,
            ], [
                'card_no' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/', Rule::unique('fuel_cards', 'card_no')],
                'monthly_limit_l' => ['nullable', new DecimalString(10)],
                'status' => ['required', Rule::in([CardStatus::Active->value, CardStatus::Blocked->value])],
            ])];

            if ($cardNo !== '' && isset($seen[$cardNo])) {
                $rowErrors[] = ['field' => 'card_no', 'message' => 'Número de tarjeta duplicado dentro del archivo.'];
            }
            $seen[$cardNo] = true;

            if ($rowErrors !== []) {
                $failed++;
                $errors = [...$errors, ...$this->tag($row, $rowErrors)];

                continue;
            }

            if (! $dryRun) {
                $writeError = $this->write(fn () => FuelCard::query()->forceCreate([
                    'company_id' => $company->id,
                    'vehicle_id' => $vehicleId,
                    'driver_id' => $driverId,
                    'card_no' => $cardNo,
                    'allowed_product_id' => null,
                    'monthly_limit_l' => Decimal::normalize($this->nullable($data['monthly_limit_l'] ?? null)),
                    'monthly_limit_usd' => null,
                    'fleet_id' => $fleetId,
                    'status' => CardStatus::from($status),
                ]));

                if ($writeError !== null) {
                    $failed++;
                    $errors[] = ['row' => $row, 'field' => 'card_no', 'message' => $writeError];

                    continue;
                }
            }

            $successful++;
        }

        return new ImportResult(CsvTemplate::CARDS, count($records), $successful, $failed, $errors, $dryRun);
    }

    // --------------------------------------------------------------- resolution

    /** @return array{0: int|null, 1: string|null} */
    private function resolveFleet(Company $company, mixed $name): array
    {
        $name = trim((string) $name);

        if ($name === '') {
            return [null, null];
        }

        $id = Fleet::query()->where('company_id', $company->id)->where('name', $name)->value('id');

        return $id === null ? [null, "No existe una flota llamada \"{$name}\" en esta empresa."] : [(int) $id, null];
    }

    /** @return array{0: int|null, 1: string|null} */
    private function resolveVehicle(Company $company, mixed $plate): array
    {
        $plate = Vehicle::normalizePlate((string) $plate);

        if ($plate === '') {
            return [null, null];
        }

        $id = Vehicle::query()->where('company_id', $company->id)->where('plate_no', $plate)->value('id');

        return $id === null ? [null, "No existe un vehículo con la placa \"{$plate}\" en esta empresa."] : [(int) $id, null];
    }

    /** @return array{0: int|null, 1: string|null} */
    private function resolveDriver(Company $company, mixed $license): array
    {
        $license = Driver::normalizeLicense((string) $license);

        if ($license === '') {
            return [null, null];
        }

        $id = Driver::query()->where('company_id', $company->id)->where('license_no', $license)->value('id');

        return $id === null ? [null, "No existe un conductor con la licencia \"{$license}\" en esta empresa."] : [(int) $id, null];
    }

    // ----------------------------------------------------------------- parsing

    /**
     * Parse a CSV string into records, checking the exact header first.
     *
     * @return list<array{row: int, data: array<string, string>, extra: list<string>}>
     */
    private function parseCsv(string $type, string $csv): array
    {
        if (strlen($csv) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'El archivo supera el tamaño máximo de 5 MB.']);
        }

        $csv = $this->stripBom($csv);
        $stream = fopen('php://temp', 'r+');
        assert($stream !== false);
        fwrite($stream, $csv);
        rewind($stream);

        $header = fgetcsv($stream, 0, ',', '"', '\\');
        $header = $header === false ? [] : array_map(fn ($cell): string => trim((string) $cell), $header);
        $expected = CsvTemplate::header($type);

        if ($header !== $expected) {
            fclose($stream);

            throw ValidationException::withMessages([
                'file' => 'El encabezado no coincide con la plantilla. Se esperaba: '.implode(',', $expected).'.',
            ]);
        }

        $records = [];
        $rowNumber = 0;

        while (($cells = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            if ($this->isEmptyLine($cells)) {
                continue;
            }

            $rowNumber++;
            $cells = array_map(fn ($cell): string => trim((string) $cell), $cells);
            $data = [];
            foreach ($expected as $index => $field) {
                $data[$field] = $cells[$index] ?? '';
            }

            $records[] = ['row' => $rowNumber, 'data' => $data, 'extra' => array_slice($cells, count($expected))];
        }

        fclose($stream);

        return $records;
    }

    /**
     * API JSON body rows: one associative array per record.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{row: int, data: array<string, string>, extra: list<string>}>
     */
    private function recordsFromRows(string $type, array $rows): array
    {
        $expected = CsvTemplate::header($type);
        $records = [];

        foreach ($rows as $index => $row) {
            $data = [];
            foreach ($expected as $field) {
                $value = $row[$field] ?? null;
                $data[$field] = is_scalar($value) ? (string) $value : '';
            }

            $records[] = [
                'row' => $index + 1,
                'data' => $data,
                'extra' => array_values(array_diff(array_keys($row), $expected)),
            ];
        }

        return $records;
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @return list<array{field: string, message: string}>
     */
    private function validate(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);

        if ($validator->passes()) {
            return [];
        }

        $errors = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = ['field' => $field, 'message' => $message];
            }
        }

        return $errors;
    }

    /**
     * @param  array{row: int, data: array<string, string>, extra: list<string>}  $record
     * @return list<array{field: string, message: string}>
     */
    private function extraColumnErrors(array $record): array
    {
        $errors = [];
        foreach ($record['extra'] as $column) {
            $errors[] = ['field' => (string) $column, 'message' => 'Columna no esperada.'];
        }

        return $errors;
    }

    /**
     * @param  list<array{field: string, message: string}>  $rowErrors
     * @return list<array{row: int, field: string, message: string}>
     */
    private function tag(int $row, array $rowErrors): array
    {
        return array_map(fn (array $error): array => ['row' => $row, ...$error], $rowErrors);
    }

    /** Run one row's insert in its own transaction; return an error message on a refused write. */
    private function write(callable $insert): ?string
    {
        try {
            DB::transaction(function () use ($insert): bool {
                $insert();

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return 'Ya existe un registro con ese valor.';
        }

        return null;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param  array<int, string|null>  $cells */
    private function isEmptyLine(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stripBom(string $csv): string
    {
        return str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;
    }

    private function ensureCompanyActive(Company $company): void
    {
        if ($company->status !== CompanyStatus::Active) {
            throw BusinessRuleViolation::companyInactive();
        }
    }
}
