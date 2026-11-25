<?php

namespace App\Console\Commands;

use App\Services\UsageReconciliation;
use Illuminate\Console\Command;

class ReconcileUsage extends Command
{
    protected $signature = 'usage:reconcile';

    protected $description = 'Compara cada contador de uso mensual con el libro de combustible que resume (solo lectura)';

    public function handle(UsageReconciliation $reconciliation): int
    {
        $mismatches = $reconciliation->mismatches();

        if ($mismatches === []) {
            $this->info('Todos los contadores de uso mensual coinciden con el libro.');

            return self::SUCCESS;
        }

        // Card IDs, never card numbers: output may end up in logs.
        $this->error(count($mismatches).' contador(es) difieren del libro. No se cambió nada; investiga antes de cualquier corrección.');
        $this->table(
            ['ID de tarjeta', 'Mes', 'Contador L', 'Libro L', 'Contador USD', 'Libro USD'],
            array_map(fn (array $row): array => [
                $row['fuel_card_id'], $row['month'], $row['counter_l'], $row['ledger_l'], $row['counter_usd'], $row['ledger_usd'],
            ], $mismatches),
        );

        return self::FAILURE;
    }
}
