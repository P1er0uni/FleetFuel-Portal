<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DeactivateUser extends Command
{
    protected $signature = 'users:deactivate {email : Email of the account to disable}';

    protected $description = 'Desactiva una cuenta: rechaza el inicio de sesión, revoca sus tokens de API y cierra sus sesiones';

    public function handle(UserAccountService $accounts): int
    {
        $user = User::query()->where('email', Str::lower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('Ninguna cuenta tiene ese correo.');

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->info('Esa cuenta ya está desactivada; no cambió nada.');

            return self::SUCCESS;
        }

        $result = $accounts->deactivate($user);

        $this->info("Se desactivó {$user->email}: se revocaron {$result['tokens']} token(s) de API y se cerraron {$result['sessions']} sesión(es).");

        return self::SUCCESS;
    }
}
