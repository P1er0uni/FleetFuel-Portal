<?php

namespace App\Http\Resources;

use App\Models\FleetRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/api/openapi.json, "FleetRule". rule_value is the stored JSON object:
 * {"ids": [...]} for the list rules, {"value": "50.00"} for the numeric ones.
 *
 * @mixin FleetRule
 */
class FleetRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_type' => $this->rule_type->value,
            'rule_value' => $this->rule_value,
            'active' => $this->active,
        ];
    }
}
