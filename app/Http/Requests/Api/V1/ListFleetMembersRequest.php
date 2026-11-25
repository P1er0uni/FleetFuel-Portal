<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Fleet;

/**
 * GET /api/v1/fleets/{fleet}/vehicles, /drivers and /cards: pagination
 * only, and the caller must be able to view the fleet. The {fleet}
 * binding is tenant-scoped, so another company's fleet is already a 404
 * before the policy runs.
 */
class ListFleetMembersRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        $fleet = $this->route('fleet');

        return $fleet instanceof Fleet && $this->user()?->can('view', $fleet) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
