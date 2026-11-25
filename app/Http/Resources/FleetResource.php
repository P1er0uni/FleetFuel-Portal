<?php

namespace App\Http\Resources;

use App\Models\Fleet;
use App\Support\FleetQuota;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/api/openapi.json, "Fleet" and "FleetDetail". Quotas are fixed-scale
 * decimal strings; null means unlimited. The list and write responses
 * carry the plain fields; the detail response additionally loads the
 * fleet's rules and sets $usage, which adds both keys.
 *
 * @mixin Fleet
 */
class FleetResource extends JsonResource
{
    /** Set by the detail endpoint; null keeps the key out of list responses. */
    public ?FleetQuota $usage = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'monthly_quota_liters' => $this->monthly_quota_liters,
            'monthly_quota_amount' => $this->monthly_quota_amount,
            'rules' => FleetRuleResource::collection($this->whenLoaded('rules')),
            'usage' => $this->when($this->usage !== null, fn () => $this->usage->toArray()),
        ];
    }
}
