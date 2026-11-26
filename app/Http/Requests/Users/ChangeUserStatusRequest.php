<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ChangeUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('updateStatus', $target) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['is_active.required' => 'Indica si la cuenta queda activa o inactiva.'];
    }
}
