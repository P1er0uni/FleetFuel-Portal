<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class SeedDemoData extends Command
{
    protected $signature = 'demo:seed
        {--as-of= : Reloj para los datos demo, un instante ISO-8601 con desplazamiento como 2026-09-28T09:00:00Z (por defecto: ahora)}
        {--force : Permite también un despliegue de producción que sirva como demo pública (aún requiere DEMO_MODE=true y una base de datos vacía)}';

    protected $description = 'Siembra datos demo ficticios en una base de datos vacía (local, o un despliegue demo de producción con --force)';

    public function handle(): int
    {
        $asOf = $this->option('as-of');
        $asOf = is_string($asOf) ? $asOf : null;

        if ($asOf !== null && ($error = $this->invalidAsOf($asOf)) !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        try {
            $seeder = $this->laravel->make(DemoSeeder::class);
            $seeder->setContainer($this->laravel)->setCommand($this)->__invoke(['asOf' => $asOf, 'hostedDemo' => (bool) $this->option('force')]);
        } catch (RuntimeException $e) {
            // Guard refusals (environment, DEMO_MODE, missing password).
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function invalidAsOf(string $value): ?string
    {
        $message = '--as-of debe ser un instante ISO-8601 con desplazamiento, p. ej. 2026-09-28T09:00:00Z';

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            return $message;
        }

        try {
            $parsed = CarbonImmutable::parse($value);
        } catch (Throwable) {
            return $message;
        }

        // Reject impossible dates that PHP would silently roll over (e.g. 02-30).
        if ($parsed->format('Y-m-d\TH:i:s') !== substr($value, 0, 19)) {
            return $message;
        }

        if ($parsed->isAfter(CarbonImmutable::now()->addMinute())) {
            return '--as-of no puede estar en el futuro.';
        }

        return null;
    }
}
