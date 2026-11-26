<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\FleetRuleType;
use App\Enums\RuleEnforcement;
use App\Exceptions\BusinessRuleViolation;
use App\Exceptions\FleetRuleViolation;
use App\Models\Company;
use App\Models\Fleet;
use App\Models\FleetRule;
use App\Models\FuelCard;
use App\Models\FuelTransaction;
use App\Models\Product;
use App\Models\Station;
use App\Models\User;
use App\Support\PosPurchase;
use App\Support\PriceQuote;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A fleet's rules: CRUD for the admin screens, and the read-only check the POS
 * ingestion runs inside the card-lock transaction before a purchase is charged
 * (docs/sdd/fase-5-reglas-limites-flota/spec.md).
 *
 * A broken rule either blocks the purchase (throws FleetRuleViolation, 422) or
 * only warns (returns an entry the caller turns into a notification). Rules
 * outside their validity dates are ignored.
 */
class FleetRuleService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @return list<array{rule_type: string, message: string, enforcement: string}>
     */
    public function ensureAccepts(FuelCard $card, Station $station, Product $product, PosPurchase $purchase, PriceQuote $quote): array
    {
        if ($card->fleet_id === null) {
            return [];
        }

        $timezone = (string) config('fleetfuel.business_timezone');
        $localDate = $purchase->transactedAt->setTimezone($timezone)->toDateString();

        $rules = FleetRule::query()
            ->where('fleet_id', $card->fleet_id)
            ->where('active', true)
            ->get()
            ->filter(fn (FleetRule $rule): bool => $this->withinValidity($rule, $localDate))
            ->values();

        $dayTotals = $this->needsDayTotals($rules)
            ? $this->dayTotals($card, $purchase, $timezone)
            : ['count' => 0, 'liters' => '0', 'usd' => '0'];

        $warnings = [];

        foreach ($rules as $rule) {
            $violation = $this->evaluate($rule, $card, $station, $product, $purchase, $quote, $dayTotals, $timezone);

            if ($violation === null) {
                continue;
            }

            if ($rule->enforcement === RuleEnforcement::Warn) {
                $warnings[] = [
                    'rule_type' => $rule->rule_type->value,
                    'message' => $violation['message'],
                    'enforcement' => 'warn',
                ];

                continue;
            }

            throw FleetRuleViolation::forRule(
                $violation['field'],
                $violation['message'],
                (int) $card->company_id,
                $card->fleet_id,
                (int) $card->id,
                $rule->rule_type->value,
            );
        }

        return $warnings;
    }

    /**
     * Create or replace the fleet's rule of one type (the unique key allows
     * one rule per type). $value is ['ids' => list] for list rules,
     * ['value' => string] for numeric ones and ['windows' => list] for schedules.
     *
     * @param  array{ids?: list<int>, value?: string, windows?: list<array{days: list<int>, start: string, end: string}>}  $value
     */
    public function save(
        Fleet $fleet,
        FleetRuleType $type,
        array $value,
        bool $active,
        User $actor,
        RuleEnforcement $enforcement = RuleEnforcement::Block,
        ?string $validFrom = null,
        ?string $validTo = null,
    ): FleetRule {
        return DB::transaction(function () use ($fleet, $type, $value, $active, $actor, $enforcement, $validFrom, $validTo): FleetRule {
            $fleet = Fleet::query()->lockForUpdate()->findOrFail($fleet->id);
            $this->ensureCompanyActive($fleet);

            $rule = FleetRule::query()->updateOrCreate(
                ['fleet_id' => $fleet->id, 'rule_type' => $type],
                [
                    'rule_value' => $value,
                    'enforcement' => $enforcement,
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                    'active' => $active,
                ],
            );

            $this->audit->record('fleet.rules_changed', $fleet, $actor, $fleet->company_id, null, [
                'rule_type' => $type->value,
                'rule_value' => $value,
                'enforcement' => $enforcement->value,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'active' => $active,
            ]);

            return $rule;
        });
    }

    public function delete(Fleet $fleet, FleetRule $rule, User $actor): void
    {
        DB::transaction(function () use ($fleet, $rule, $actor): void {
            $fleet = Fleet::query()->lockForUpdate()->findOrFail($fleet->id);
            $this->ensureCompanyActive($fleet);

            $rule = FleetRule::query()->where('fleet_id', $fleet->id)->findOrFail($rule->id);

            $this->audit->record('fleet.rules_changed', $fleet, $actor, $fleet->company_id,
                ['rule_type' => $rule->rule_type->value, 'active' => $rule->active],
                null);

            $rule->delete();
        });
    }

    /**
     * @param  array{count: int, liters: string, usd: string}  $dayTotals
     * @return array{field: string, message: string}|null
     */
    private function evaluate(
        FleetRule $rule,
        FuelCard $card,
        Station $station,
        Product $product,
        PosPurchase $purchase,
        PriceQuote $quote,
        array $dayTotals,
        string $timezone,
    ): ?array {
        $value = (string) ($rule->rule_value['value'] ?? '0');

        return match ($rule->rule_type) {
            FleetRuleType::AllowedProducts => in_array($product->id, $rule->rule_value['ids'] ?? [], true)
                ? null
                : ['field' => 'product_code', 'message' => 'Product not allowed for this fleet.'],

            FleetRuleType::AllowedStations => in_array($station->id, $rule->rule_value['ids'] ?? [], true)
                ? null
                : ['field' => 'station', 'message' => 'This station is not allowed for this fleet.'],

            FleetRuleType::MaxLitersPerTransaction => BigDecimal::of($purchase->liters)->isGreaterThan($value)
                ? ['field' => 'liters', 'message' => "This purchase exceeds the fleet's per-transaction limit of {$value} L."]
                : null,

            FleetRuleType::MaxTransactionsPerDay => $dayTotals['count'] >= (int) BigDecimal::of($value)->toInt()
                ? ['field' => 'liters', 'message' => 'This card reached the fleet\'s daily limit of '.$value.' transactions.']
                : null,

            FleetRuleType::MaxLitersPerDay => BigDecimal::of($dayTotals['liters'])->plus($quote->liters)->isGreaterThan($value)
                ? ['field' => 'liters', 'message' => "This purchase exceeds the fleet's daily limit of {$value} L."]
                : null,

            FleetRuleType::MaxAmountPerTransactionUsd => BigDecimal::of($quote->amountUsd)->isGreaterThan($value)
                ? ['field' => 'amount', 'message' => "This purchase exceeds the fleet's per-transaction limit of {$value} USD."]
                : null,

            FleetRuleType::MaxAmountPerDayUsd => BigDecimal::of($dayTotals['usd'])->plus($quote->amountUsd)->isGreaterThan($value)
                ? ['field' => 'amount', 'message' => "This purchase exceeds the fleet's daily limit of {$value} USD."]
                : null,

            FleetRuleType::AllowedTimeWindows => $this->withinTimeWindows($rule, $purchase, $timezone)
                ? null
                : ['field' => 'schedule', 'message' => 'This purchase is outside the fleet\'s allowed days and hours.'],
        };
    }

    private function withinTimeWindows(FleetRule $rule, PosPurchase $purchase, string $timezone): bool
    {
        $local = $purchase->transactedAt->setTimezone($timezone);
        $weekday = $local->isoWeekday();
        $time = $local->format('H:i');

        foreach ((array) ($rule->rule_value['windows'] ?? []) as $window) {
            $days = array_map('intval', (array) ($window['days'] ?? []));
            $start = (string) ($window['start'] ?? '');
            $end = (string) ($window['end'] ?? '');

            if (in_array($weekday, $days, true) && $time >= $start && $time < $end) {
                return true;
            }
        }

        return false;
    }

    private function withinValidity(FleetRule $rule, string $localDate): bool
    {
        if ($rule->valid_from !== null && $localDate < $rule->valid_from->toDateString()) {
            return false;
        }

        if ($rule->valid_to !== null && $localDate > $rule->valid_to->toDateString()) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, FleetRule>  $rules
     */
    private function needsDayTotals(Collection $rules): bool
    {
        return $rules->contains(fn (FleetRule $rule): bool => in_array($rule->rule_type, [
            FleetRuleType::MaxTransactionsPerDay,
            FleetRuleType::MaxLitersPerDay,
            FleetRuleType::MaxAmountPerDayUsd,
        ], true));
    }

    /**
     * @return array{count: int, liters: string, usd: string}
     */
    private function dayTotals(FuelCard $card, PosPurchase $purchase, string $timezone): array
    {
        $dayStart = $purchase->transactedAt->setTimezone($timezone)->startOfDay()->utc();
        $dayEnd = $dayStart->addDay();

        $row = FuelTransaction::query()
            ->where('fuel_card_id', $card->id)
            ->where('transacted_at', '>=', $dayStart)
            ->where('transacted_at', '<', $dayEnd)
            ->toBase()
            ->selectRaw('COUNT(*) AS purchases, COALESCE(SUM(liters), 0) AS liters, COALESCE(SUM(amount_usd), 0) AS usd')
            ->first();

        return [
            'count' => (int) ($row->purchases ?? 0),
            'liters' => (string) ($row->liters ?? '0'),
            'usd' => (string) ($row->usd ?? '0'),
        ];
    }

    private function ensureCompanyActive(Fleet $fleet): void
    {
        $company = Company::query()->findOrFail($fleet->company_id);

        if ($company->status !== CompanyStatus::Active) {
            throw BusinessRuleViolation::companyInactive();
        }
    }
}
