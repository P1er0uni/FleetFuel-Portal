<?php

namespace App\Http\Requests\Fleets;

use App\Enums\FleetRuleType;
use App\Enums\RuleEnforcement;
use App\Http\Requests\Fleet\ResolvesCompany;
use App\Http\Requests\Fleets\Concerns\ValidatesFleetRules;
use App\Models\Fleet;
use App\Rules\DecimalString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create a fleet (Fase 1, D6) with an optional initial rule (Fase 5). The
 * company is chosen by the server; the name is unique within it. When the
 * "add_rule" box is ticked, the rule fields are validated like a normal rule
 * and saved in the same transaction as the fleet.
 */
class StoreFleetRequest extends FormRequest
{
    use ResolvesCompany;
    use ValidatesFleetRules;

    public function authorize(): bool
    {
        return $this->actor()->can('create', Fleet::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'company_id' => $this->companyRules(),
            'name' => ['required', 'string', 'max:100',
                Rule::unique('fleets', 'name')->where('company_id', $this->targetCompanyId())],
            'description' => ['nullable', 'string', 'max:255'],
            // DECIMAL(12,3) and DECIMAL(18,2); empty means unlimited.
            'monthly_quota_liters' => ['nullable', new DecimalString(9, 3)],
            'monthly_quota_amount' => ['nullable', new DecimalString(16)],
            'add_rule' => ['nullable', 'boolean'],
        ];

        if ($this->boolean('add_rule')) {
            foreach ($this->ruleValidationRules() as $key => $value) {
                $rules['rule.'.$key] = $value;
            }
        }

        return $rules;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('add_rule')) {
                $this->validateRuleShape($validator, (array) $this->input('rule', []), 'rule.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->companyMessages() + [
            'name.required' => 'El nombre de la flota es obligatorio.',
            'name.unique' => 'Esta empresa ya tiene una flota con ese nombre.',
        ];
    }

    /**
     * The validated attributes for FleetService::createWithRules().
     *
     * @return array{name: string, description: string|null, monthly_quota_liters: string|null, monthly_quota_amount: string|null}
     */
    public function fleetAttributes(): array
    {
        /** @var array{name: string, description?: string|null, monthly_quota_liters?: string|null, monthly_quota_amount?: string|null} $validated */
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'monthly_quota_liters' => $validated['monthly_quota_liters'] ?? null,
            'monthly_quota_amount' => $validated['monthly_quota_amount'] ?? null,
        ];
    }

    /**
     * The initial rules to persist with the fleet (empty when not requested).
     *
     * @return list<array{type: FleetRuleType, value: array<string, mixed>, enforcement: RuleEnforcement, active: bool, valid_from: string|null, valid_to: string|null}>
     */
    public function rulesInput(): array
    {
        if (! $this->boolean('add_rule')) {
            return [];
        }

        return [$this->attributesFromRule((array) $this->input('rule', []), defaultActive: true)];
    }
}
