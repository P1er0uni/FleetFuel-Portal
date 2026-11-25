<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Fleet\ResolvesCompany;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/import/{type}: either a multipart "file" (a CSV upload) or a
 * JSON "rows" array, plus an optional dry_run. An admin sends company_id (a
 * JSON integer in a JSON body, a numeric field in a multipart form); a
 * manager must omit it. Unknown top-level fields are refused; unknown keys
 * inside a row become that row's error in the import result.
 */
class ImportRequest extends FormRequest
{
    use RejectsUnknownFields;
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
            'company_id' => $this->companyRules(jsonInteger: $this->isJson()),
            'dry_run' => ['nullable', 'boolean'],
            'file' => ['required_without:rows', 'file', 'max:5120', 'mimes:csv,txt'],
            'rows' => ['required_without:file', 'array', 'min:1', 'max:5000'],
            'rows.*' => ['array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.required' => 'Choose a company.',
            'company_id.exists' => 'Choose an active company.',
            'company_id.prohibited' => 'The company is set from your account and cannot be chosen.',
            'file.required_without' => 'Send a CSV file or a rows array.',
            'file.mimes' => 'The file must be a CSV.',
            'file.max' => 'The file must be at most 5 MB.',
            'rows.required_without' => 'Send a CSV file or a rows array.',
            'rows.max' => 'At most 5000 rows are allowed.',
        ];
    }
}
