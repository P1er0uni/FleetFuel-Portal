<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPreferenceRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The preferences form (web, Spanish). Only the keys the user changes are
 * applied; the manager's default fleet is checked against their company.
 */
class PreferenceRequest extends FormRequest
{
    use HasPreferenceRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->preferenceRules();
    }

    /**
     * @return array<string, string|null>
     */
    public function preferences(): array
    {
        return $this->normalizedPreferences();
    }
}
