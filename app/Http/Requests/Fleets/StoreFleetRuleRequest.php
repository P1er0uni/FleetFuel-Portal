<?php

namespace App\Http\Requests\Fleets;

use App\Enums\FleetRuleType;
use App\Models\Fleet;
use App\Rules\DecimalString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or replace one of the fleet's rules. List rules (allowed_products,
 * allowed_stations) send `ids`, a list of active product or station IDs;
 * numeric rules send `value`, a decimal string. The request normalizes
 * both into the rule_value the service stores.
 */
class StoreFleetRuleRequest extends FormRequest
{
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
        $type = FleetRuleType::tryFrom((string) $this->input('rule_type'));

        return [
            'rule_type' => ['required', Rule::enum(FleetRuleType::class)],
            'active' => ['nullable', 'boolean'],
            // List rules: which products or stations the fleet allows.
            'ids' => ['required_if:rule_type,'.FleetRuleType::AllowedProducts->value.','.FleetRuleType::AllowedStations->value, 'array', 'max:100'],
            'ids.*' => $type === FleetRuleType::AllowedProducts
                ? ['integer', Rule::exists('products', 'id')->where('is_active', true)]
                : ['integer', Rule::exists('stations', 'id')->where('is_active', true)],
            // Numeric rules: the limit itself.
            'value' => [
                'required_unless:rule_type,'.FleetRuleType::AllowedProducts->value.','.FleetRuleType::AllowedStations->value,
                'nullable',
                new DecimalString(9),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rule_type.required' => 'Elige un tipo de regla.',
            'ids.required_if' => 'Marca al menos un elemento permitido.',
            'ids.*.exists' => 'Todos los elementos marcados deben existir y estar activos.',
            'value.required_unless' => 'El valor del límite es obligatorio.',
        ];
    }

    public function ruleType(): FleetRuleType
    {
        return FleetRuleType::from((string) $this->validated('rule_type'));
    }

    /** @return array{ids?: list<int>, value?: string} */
    public function ruleValue(): array
    {
        $validated = $this->validated();

        if ($this->ruleType()->isListRule()) {
            /** @var list<int> $ids */
            $ids = array_values(array_map('intval', (array) ($validated['ids'] ?? [])));

            return ['ids' => $ids];
        }

        return ['value' => (string) ($validated['value'] ?? '')];
    }

    public function active(): bool
    {
        return $this->boolean('active');
    }
}
