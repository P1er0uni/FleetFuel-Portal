<?php

namespace App\Http\Requests\Fleets\Concerns;

use App\Enums\FleetRuleType;
use App\Enums\RuleEnforcement;
use App\Models\Product;
use App\Models\Station;
use App\Rules\DecimalString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation and normalization for a single fleet rule (Fase 5). Used by
 * the per-rule form (top-level fields) and by the initial rules of the create
 * fleet form (under a prefix such as "rule.").
 */
trait ValidatesFleetRules
{
    /**
     * @return array<string, mixed>
     */
    protected function ruleValidationRules(): array
    {
        return [
            'rule_type' => ['required', Rule::enum(FleetRuleType::class)],
            'enforcement' => ['nullable', Rule::enum(RuleEnforcement::class)],
            'active' => ['nullable', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['integer', 'min:1'],
            'value' => ['nullable', new DecimalString(9)],
            'windows' => ['nullable', 'array', 'max:20'],
            'windows.*.days' => ['nullable', 'array'],
            'windows.*.days.*' => ['integer', 'between:1,7'],
            'windows.*.start' => ['nullable', 'date_format:H:i'],
            'windows.*.end' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Cross-field checks that depend on the chosen rule type.
     *
     * @param  array<string, mixed>  $input
     */
    protected function validateRuleShape(Validator $validator, array $input, string $prefix = ''): void
    {
        $type = FleetRuleType::tryFrom((string) ($input['rule_type'] ?? ''));

        if ($type === null) {
            return;
        }

        $add = fn (string $field, string $message) => $validator->errors()->add($prefix.$field, $message);

        if ($type->isListRule()) {
            $ids = $input['ids'] ?? null;

            if (! is_array($ids) || $ids === []) {
                $add('ids', 'Marca al menos un elemento permitido.');

                return;
            }

            $model = $type === FleetRuleType::AllowedProducts ? Product::query() : Station::query();
            $found = $model->whereIn('id', array_map('intval', $ids))->where('is_active', true)->count();

            if ($found !== count($ids)) {
                $add('ids', 'Todos los elementos marcados deben existir y estar activos.');
            }

            return;
        }

        if ($type->isNumericRule()) {
            $value = trim((string) ($input['value'] ?? ''));

            if ($value === '') {
                $add('value', 'El valor del límite es obligatorio.');
            } elseif ($type === FleetRuleType::MaxTransactionsPerDay && ! ctype_digit($value)) {
                $add('value', 'El máximo de transacciones por día debe ser un número entero.');
            }

            return;
        }

        if ($type->isWindowsRule()) {
            $windows = $input['windows'] ?? null;

            if (! is_array($windows) || $windows === []) {
                $add('windows', 'Agrega al menos una franja de días y horas.');

                return;
            }

            foreach ($windows as $index => $window) {
                $days = (array) ($window['days'] ?? []);
                $start = (string) ($window['start'] ?? '');
                $end = (string) ($window['end'] ?? '');

                if ($days === []) {
                    $add("windows.{$index}.days", 'Elige al menos un día.');
                }

                if ($start === '' || $end === '') {
                    $add("windows.{$index}.start", 'Indica la hora de inicio y fin.');
                } elseif ($end <= $start) {
                    $add("windows.{$index}.end", 'La hora de fin debe ser posterior a la de inicio.');
                }
            }
        }
    }

    /**
     * Normalize one validated rule into the shape the service stores.
     *
     * @param  array<string, mixed>  $input
     * @return array{type: FleetRuleType, value: array<string, mixed>, enforcement: RuleEnforcement, active: bool, valid_from: string|null, valid_to: string|null}
     */
    protected function attributesFromRule(array $input, bool $defaultActive = true): array
    {
        $type = FleetRuleType::from((string) $input['rule_type']);

        $value = match (true) {
            $type->isListRule() => ['ids' => array_values(array_map('intval', (array) ($input['ids'] ?? [])))],
            $type->isWindowsRule() => ['windows' => $this->normalizeWindows((array) ($input['windows'] ?? []))],
            default => ['value' => (string) ($input['value'] ?? '')],
        };

        return [
            'type' => $type,
            'value' => $value,
            'enforcement' => RuleEnforcement::tryFrom((string) ($input['enforcement'] ?? '')) ?? RuleEnforcement::Block,
            'active' => array_key_exists('active', $input) ? (bool) $input['active'] : $defaultActive,
            'valid_from' => ($input['valid_from'] ?? null) ?: null,
            'valid_to' => ($input['valid_to'] ?? null) ?: null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $windows
     * @return list<array{days: list<int>, start: string, end: string}>
     */
    private function normalizeWindows(array $windows): array
    {
        return array_map(fn (array $window): array => [
            'days' => array_values(array_map('intval', (array) ($window['days'] ?? []))),
            'start' => (string) ($window['start'] ?? ''),
            'end' => (string) ($window['end'] ?? ''),
        ], $windows);
    }
}
