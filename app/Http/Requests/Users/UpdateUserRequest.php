<?php

namespace App\Http\Requests\Users;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Edit an account: name, email, role and scope. No password here (that has its
 * own route). Email stays unique ignoring the account being edited.
 */
class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('update', $target) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $ignore = $target instanceof User ? $target->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('status', CompanyStatus::Active->value)],
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')->where('is_active', true)],
        ];
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
        ];
    }
}
