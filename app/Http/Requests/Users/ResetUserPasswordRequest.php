<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin sets a temporary password (Fase 4, D7). The user must change it on her
 * next sign-in; sessions and tokens are revoked by the service.
 */
class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('resetPassword', $target) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'min:12', 'confirmed']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'La contraseña temporal es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
