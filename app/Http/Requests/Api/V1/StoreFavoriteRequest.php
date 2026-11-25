<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Services\FavoriteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/favorites: type, id and an optional custom label. Unknown
 * fields are refused; the entity's existence and scope are checked by the
 * service (a foreign record is 404).
 */
class StoreFavoriteRequest extends FormRequest
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
            'favoritable_type' => ['required', 'string', Rule::in(FavoriteService::TYPES)],
            'favoritable_id' => ['required', 'integer', 'min:1'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function type(): string
    {
        return (string) $this->validated('favoritable_type');
    }

    public function favoritableId(): int
    {
        return (int) $this->validated('favoritable_id');
    }

    public function label(): ?string
    {
        $value = $this->validated('label');

        return $value === null || $value === '' ? null : (string) $value;
    }
}
