<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;

/**
 * GET /api/v1/notifications/rules: paginated, optionally narrowed to a
 * company by an admin (prohibited for a manager).
 */
class ListNotificationRulesRequest extends PaginatedListRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isAdmin = $user instanceof User && $user->isAdmin();

        return [
            ...$this->paginationRules(),
            'company_id' => $isAdmin ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
        ];
    }

    public function companyFilter(): ?int
    {
        $value = $this->validated('company_id');

        return $value === null || $value === '' ? null : (int) $value;
    }
}
