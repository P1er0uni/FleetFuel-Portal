<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of GET /api/v1/map/stations: status, one product code and a name
 * search. Unknown query parameters are refused rather than ignored.
 */
class MapStationsRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['all', 'active', 'inactive'])],
            'product' => ['nullable', 'string', Rule::exists('products', 'code')],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function status(): ?string
    {
        $value = $this->validated('status');

        return $value === null || $value === 'all' ? null : (string) $value;
    }

    public function product(): ?string
    {
        $value = $this->validated('product');

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function term(): ?string
    {
        $value = trim((string) ($this->validated('q') ?? ''));

        return $value === '' ? null : $value;
    }
}
