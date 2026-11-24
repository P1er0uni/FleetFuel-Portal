<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ActivateUser extends Command
{
    protected $signature = 'users:activate {email : Email of the account to re-enable}';

    protected $description = 'Reactiva una cuenta desactivada (los tokens de API revocados siguen revocados)';

    public function handle(UserAccountService $accounts): int
    {
        $user = User::query()->where('email', Str::lower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('Ninguna cuenta tiene ese correo.');

            return self::FAILURE;
        }

        if ($user->is_active) {
            $this->info('Esa cuenta ya está activa; no cambió nada.');

            return self::SUCCESS;
        }

        $accounts->activate($user);

        $this->info("Se reactivó {$user->email}. El usuario inicia sesión de nuevo con su contraseña existente.");

        return self::SUCCESS;
    }
}
