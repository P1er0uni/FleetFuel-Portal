<?php

namespace App\Http\Requests\Import;

use App\Http\Requests\Fleet\ResolvesCompany;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The web bulk-import form: an uploaded CSV, an optional dry run, and the
 * company (chosen by an admin, taken from the account for a manager). The
 * header and per-row rules are checked by BulkImportService, which turns a
 * bad header into a validation error on "file".
 */
class ImportRequest extends FormRequest
{
    use ResolvesCompany;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->is_active && ($user->isAdmin() || $user->isCompanyManager());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => $this->companyRules(),
            'dry_run' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->companyMessages() + [
            'file.required' => 'Elige un archivo CSV.',
            'file.mimes' => 'El archivo debe ser CSV.',
            'file.max' => 'El archivo supera el tamaño máximo de 5 MB.',
        ];
    }
}
