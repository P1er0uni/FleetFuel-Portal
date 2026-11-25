<?php

namespace App\Support\CsvImport;

/**
 * The outcome of one bulk import (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * rule 4): how many rows were read, how many were written, and every row that
 * was refused with the field and message that explain why.
 *
 * Row numbers count data rows only, starting at 1 (the header is not a row),
 * so a user can point straight at their spreadsheet.
 */
final readonly class ImportResult
{
    /**
     * @param  list<array{row: int, field: string, message: string}>  $errors
     */
    public function __construct(
        public string $type,
        public int $totalRows,
        public int $successful,
        public int $failed,
        public array $errors,
        public bool $dryRun,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array{type: string, dry_run: bool, total_rows: int, successful: int, failed: int, errors: list<array{row: int, field: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'dry_run' => $this->dryRun,
            'total_rows' => $this->totalRows,
            'successful' => $this->successful,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }
}
