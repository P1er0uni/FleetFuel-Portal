<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateSync;
use Illuminate\Console\Command;

class SyncExchangeRates extends Command
{
    protected $signature = 'rates:sync {--force : Descarga aunque la próxima actualización del proveedor no esté vencida}';

    protected $description = 'Descarga y almacena la última observación USD/LBP (proveedor en vivo, o un fixture sintético en modo fixture)';

    public function handle(ExchangeRateSync $sync): int
    {
        $result = $sync->run((bool) $this->option('force'));

        if ($result->failed()) {
            $this->error($result->message);

            return self::FAILURE;
        }

        $this->info($result->message);

        return self::SUCCESS;
    }
}
