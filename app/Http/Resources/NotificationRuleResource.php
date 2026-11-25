<?php

namespace App\Http\Resources;

use App\Models\NotificationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/api/openapi.json, "NotificationRule".
 *
 * @mixin NotificationRule
 */
class NotificationRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'fleet_id' => $this->fleet_id,
            'event_type' => $this->event_type->value,
            'channel' => $this->channel->value,
            'threshold_value' => $this->threshold_value,
            'threshold_operator' => $this->threshold_operator?->value,
            'active' => $this->active,
        ];
    }
}
