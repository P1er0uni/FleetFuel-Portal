<?php

namespace App\Models;

use App\Enums\FleetRuleType;
use App\Enums\RuleEnforcement;
use Database\Factories\FleetRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fleet_id', 'rule_type', 'rule_value', 'enforcement', 'valid_from', 'valid_to', 'active'])]
class FleetRule extends Model
{
    /** @use HasFactory<FleetRuleFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'active' => true,
        'enforcement' => 'block',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rule_type' => FleetRuleType::class,
            'rule_value' => 'array',
            'enforcement' => RuleEnforcement::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Fleet, $this> */
    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
