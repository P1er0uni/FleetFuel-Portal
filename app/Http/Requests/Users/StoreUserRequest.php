<?php

namespace App\Http\Requests\Users;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create an account (Fase 4, admin only). Role and scope must match the
 * users_role_scope_check rule: admin without a tenant, a manager with exactly
 * one active company, an operator with exactly one active station.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('status', CompanyStatus::Active->value)],
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')->where('is_active', true)],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateScope($validator);
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Escribe un correo válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'role.required' => 'Elige un rol.',
            'company_id.exists' => 'Elige una empresa activa.',
            'station_id.exists' => 'Elige una estación activa.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }

    public function role(): UserRole
    {
        return UserRole::from((string) $this->validated('role'));
    }

    public function company(): ?Company
    {
        $id = $this->validated('company_id');

        return $id === null ? null : Company::query()->findOrFail((int) $id);
    }

    public function station(): ?Station
    {
        $id = $this->validated('station_id');

        return $id === null ? null : Station::query()->findOrFail((int) $id);
    }

    protected function validateScope(Validator $validator): void
    {
        $role = (string) $this->input('role');
        $hasCompany = filled($this->input('company_id'));
        $hasStation = filled($this->input('station_id'));

        if ($role === UserRole::Admin->value) {
            if ($hasCompany) {
                $validator->errors()->add('company_id', 'Un administrador no pertenece a ninguna empresa.');
            }
            if ($hasStation) {
                $validator->errors()->add('station_id', 'Un administrador no pertenece a ninguna estación.');
            }
        } elseif ($role === UserRole::CompanyManager->value) {
            if (! $hasCompany) {
                $validator->errors()->add('company_id', 'Un gerente necesita una empresa.');
            }
            if ($hasStation) {
                $validator->errors()->add('station_id', 'Un gerente no pertenece a una estación.');
            }
        } elseif ($role === UserRole::StationOperator->value) {
            if (! $hasStation) {
                $validator->errors()->add('station_id', 'Un operador necesita una estación.');
            }
            if ($hasCompany) {
                $validator->errors()->add('company_id', 'Un operador no pertenece a una empresa.');
            }
        }
    }
}
