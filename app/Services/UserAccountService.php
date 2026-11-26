<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Provisioning and administration of accounts. There is no self-registration;
 * an administrator provisions users from the web screens (or the users:*
 * commands), and only an admin may change roles or reset passwords (Fase 4,
 * docs/sdd/fase-4-usuarios-admin/).
 */
class UserAccountService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(
        string $name,
        string $email,
        string $password,
        UserRole $role,
        ?Company $company = null,
        ?Station $station = null,
        ?User $actor = null,
    ): User {
        $this->ensureValidScope($role, $company, $station);

        return DB::transaction(function () use ($name, $email, $password, $role, $company, $station, $actor): User {
            $user = new User([
                'name' => $name,
                'email' => Str::lower(trim($email)),
                'password' => $password,
            ]);

            // Role and tenant are set explicitly, never mass-assigned.
            $user->forceFill([
                'role' => $role,
                'company_id' => $company?->id,
                'station_id' => $station?->id,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();

            $this->audit->record('user.created', $user, $actor, $company?->id, null, [
                'role' => $role->value,
                'company_id' => $company?->id,
                'station_id' => $station?->id,
                'is_active' => true,
            ]);

            return $user;
        });
    }

    /**
     * Change name/email and/or role-scope. Changing the role or scope revokes
     * the affected user's tokens and sessions, since their privileges change.
     *
     * @param  array{name: string, email: string, role: UserRole, company: ?Company, station: ?Station}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        $this->ensureValidScope($data['role'], $data['company'], $data['station']);

        return DB::transaction(function () use ($user, $data, $actor): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $oldBasic = ['name' => $user->name, 'email' => $user->email];
            $oldScope = $this->scopeValues($user);

            $newEmail = Str::lower(trim($data['email']));
            $newScope = [
                'role' => $data['role']->value,
                'company_id' => $data['company']?->id,
                'station_id' => $data['station']?->id,
            ];
            $scopeChanged = $oldScope !== $newScope;
            $basicChanged = $oldBasic['name'] !== $data['name'] || $oldBasic['email'] !== $newEmail;

            if ($scopeChanged && (int) $actor->id === (int) $user->id) {
                throw ValidationException::withMessages(['role' => 'No puedes cambiar tu propio rol ni tu alcance.']);
            }

            if (! $scopeChanged && ! $basicChanged) {
                return $user;
            }

            $user->forceFill([
                'name' => $data['name'],
                'email' => $newEmail,
                'role' => $data['role'],
                'company_id' => $data['company']?->id,
                'station_id' => $data['station']?->id,
            ])->save();

            if ($basicChanged) {
                $this->audit->record('user.updated', $user, $actor, $user->company_id, $oldBasic, [
                    'name' => $user->name,
                    'email' => $user->email,
                ]);
            }

            if ($scopeChanged) {
                $this->audit->record('user.role_changed', $user, $actor, $user->company_id, $oldScope, $newScope);
                $this->revokeAccess($user);
            }

            return $user;
        });
    }

    /**
     * Set a temporary password, force a change on next sign-in and cut any
     * active access. The password is never audited or logged.
     */
    public function resetPassword(User $user, string $password, User $actor): void
    {
        DB::transaction(function () use ($user, $password, $actor): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $user->forceFill([
                'password' => $password,
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            ['tokens' => $tokens] = $this->revokeAccess($user);

            $this->audit->record('user.password_reset', $user, $actor, $user->company_id, null, [
                'must_change_password' => true,
                'tokens_revoked' => $tokens,
            ]);
        });
    }

    /** A signed-in user changes their own password (used by the forced-change flow). */
    public function changeOwnPassword(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
        }

        if (Hash::check($new, $user->password)) {
            throw ValidationException::withMessages(['password' => 'La nueva contraseña debe ser distinta de la actual.']);
        }

        DB::transaction(function () use ($user, $new): void {
            $user->forceFill([
                'password' => $new,
                'must_change_password' => false,
            ])->save();

            $this->audit->record('user.password_changed', $user, $user, $user->company_id, null, [
                'must_change_password' => false,
            ]);
        });
    }

    /**
     * Disable an account everywhere at once: sign-in is refused, every API
     * token is deleted and every stored browser session ends.
     *
     * @return array{tokens: int, sessions: int}
     */
    public function deactivate(User $user, ?User $actor = null): array
    {
        return DB::transaction(function () use ($user, $actor): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($actor !== null && (int) $actor->id === (int) $user->id) {
                throw ValidationException::withMessages(['user' => 'No puedes desactivar tu propia cuenta.']);
            }

            if ($user->role === UserRole::Admin && $user->is_active) {
                $activeAdmins = User::query()->where('role', UserRole::Admin->value)->where('is_active', true)->count();

                if ($activeAdmins <= 1) {
                    throw ValidationException::withMessages(['user' => 'No puedes dejar el sistema sin administradores activos.']);
                }
            }

            // A new remember token invalidates any "remember me" cookie.
            $user->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();

            ['tokens' => $tokens, 'sessions' => $sessions] = $this->revokeAccess($user);

            $this->audit->record('user.deactivated', $user, $actor, $user->company_id,
                ['is_active' => true],
                ['is_active' => false, 'tokens_revoked' => $tokens],
            );

            return ['tokens' => $tokens, 'sessions' => $sessions];
        });
    }

    public function activate(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $user->forceFill(['is_active' => true])->save();

            $this->audit->record('user.activated', $user, $actor, $user->company_id,
                ['is_active' => false],
                ['is_active' => true],
            );
        });
    }

    /**
     * Delete the user's API tokens and (with the database session driver) its
     * browser sessions.
     *
     * @return array{tokens: int, sessions: int}
     */
    public function revokeAccess(User $user): array
    {
        $tokens = (int) $user->tokens()->delete();
        $sessions = $this->endSessions($user);

        return ['tokens' => $tokens, 'sessions' => $sessions];
    }

    /**
     * The same rule as the users_role_scope_check constraint, checked first
     * so the caller gets a readable message; plus active tenant checks.
     *
     * @throws InvalidArgumentException
     */
    public function ensureValidScope(UserRole $role, ?Company $company, ?Station $station): void
    {
        match ($role) {
            UserRole::Admin => $company === null && $station === null
                ?: throw new InvalidArgumentException('Un administrador no pertenece a ninguna empresa ni estación.'),
            UserRole::CompanyManager => $company !== null && $station === null
                ?: throw new InvalidArgumentException('Un gerente de empresa necesita exactamente una empresa y ninguna estación.'),
            UserRole::StationOperator => $station !== null && $company === null
                ?: throw new InvalidArgumentException('Un operador de estación necesita exactamente una estación y ninguna empresa.'),
        };

        if ($company !== null && $company->status !== CompanyStatus::Active) {
            throw new InvalidArgumentException("La empresa {$company->id} está inactiva.");
        }

        if ($station !== null && ! $station->is_active) {
            throw new InvalidArgumentException("La estación {$station->id} está inactiva.");
        }
    }

    /**
     * @return array{role: string, company_id: int|null, station_id: int|null}
     */
    private function scopeValues(User $user): array
    {
        return [
            'role' => $user->role->value,
            'company_id' => $user->company_id,
            'station_id' => $user->station_id,
        ];
    }

    private function endSessions(User $user): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
