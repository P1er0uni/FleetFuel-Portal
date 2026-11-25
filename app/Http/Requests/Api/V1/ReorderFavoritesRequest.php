<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /api/v1/favorites/reorder: the caller's favourite ids in the new order.
 */
class ReorderFavoritesRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', (array) $this->validated('ids'));

        return $ids;
    }
}
