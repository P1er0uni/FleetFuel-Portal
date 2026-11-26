<?php

namespace App\Http\Requests\Fleets;

use App\Enums\FleetRuleType;
use App\Enums\RuleEnforcement;
use App\Http\Requests\Fleets\Concerns\ValidatesFleetRules;
use App\Models\Fleet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Create or replace one of the fleet's rules (Fase 5). The type decides the
 * fields: ids for list rules, value for numeric ones, windows for days/hours.
 * It also carries the enforcement mode and an optional validity range.
 */
class StoreFleetRuleRequest extends FormRequest
{
    use ValidatesFleetRules;

    public function authorize(): bool
    {
        $fleet = $this->route('fleet');

        return $fleet instanceof Fleet && $this->user()->can('update', $fleet);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->ruleValidationRules();
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateRuleShape($validator, $this->all())];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rule_type.required' => 'Elige un tipo de regla.',
        ];
    }

    /**
     * @return array{type: FleetRuleType, value: array<string, mixed>, enforcement: RuleEnforcement, active: bool, valid_from: string|null, valid_to: string|null}
     */
    public function ruleAttributes(): array
    {
        return $this->attributesFromRule($this->all(), defaultActive: false);
    }
}
