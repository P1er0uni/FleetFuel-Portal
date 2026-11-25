<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\FleetRuleType;
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
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * A fleet's rules: CRUD for the admin screens, and the read-only check the
 * POS ingestion runs inside the card-lock transaction before a purchase is
 * priced and charged (docs/sdd/fase-1-flotas/spec.md, rules 4-7).
 *
 * A rule violation is a ValidationException (HTTP 422 in the API, a flash
 * error on the web), never a business decline: the card, the station and
 * the product are all fine; the fleet's policy just refuses this purchase.
 */
class FleetRuleService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Called by FuelTransactionService::record() after the card's own
     * checks, with the card row lock held. A card without a fleet, or a
     * fleet without active rules, changes nothing.
     */
    public function ensureAccepts(FuelCard $card, Station $station, Product $product, PosPurchase $purchase): void
    {
        if ($card->fleet_id === null) {
            return;
        }

        $rules = FleetRule::query()
            ->where('fleet_id', $card->fleet_id)
            ->where('active', true)
            ->get()
            ->keyBy(fn (FleetRule $rule): string => $rule->rule_type->value);

        $this->ensureProductAllowed($rules->get(FleetRuleType::AllowedProducts->value), $product, $card);
        $this->ensureStationAllowed($rules->get(FleetRuleType::AllowedStations->value), $station, $card);
        $this->ensureWithinTransactionLiters($rules->get(FleetRuleType::MaxLitersPerTransaction->value), $purchase, $card);
        $this->ensureWithinDailyTransactions($rules->get(FleetRuleType::MaxTransactionsPerDay->value), $card, $purchase);
    }

    /**
     * Create or replace the fleet's rule of one type (the unique key allows
     * one rule per type). $value is ['ids' => list] for list rules and
     * ['value' => string] for numeric ones, already validated by the Form
     * Request.
     *
     * @param  array{ids?: list<int>, value?: string}  $value
     */
    public function save(Fleet $fleet, FleetRuleType $type, array $value, bool $active, User $actor): FleetRule
    {
        return DB::transaction(function () use ($fleet, $type, $value, $active, $actor): FleetRule {
            $fleet = Fleet::query()->lockForUpdate()->findOrFail($fleet->id);
            $this->ensureCompanyActive($fleet);

            $rule = FleetRule::query()->updateOrCreate(
                ['fleet_id' => $fleet->id, 'rule_type' => $type],
                ['rule_value' => $value, 'active' => $active],
            );

            $this->audit->record('fleet.rules_changed', $fleet, $actor, $fleet->company_id, null, [
                'rule_type' => $type->value,
                'rule_value' => $value,
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

    private function ensureProductAllowed(?FleetRule $rule, Product $product, FuelCard $card): void
    {
        if ($rule !== null && ! in_array($product->id, $rule->rule_value['ids'] ?? [], true)) {
            throw $this->violation('product_code', 'Product not allowed for this fleet.', $card, FleetRuleType::AllowedProducts);
        }
    }

    private function ensureStationAllowed(?FleetRule $rule, Station $station, FuelCard $card): void
    {
        if ($rule !== null && ! in_array($station->id, $rule->rule_value['ids'] ?? [], true)) {
            throw $this->violation('station', 'This station is not allowed for this fleet.', $card, FleetRuleType::AllowedStations);
        }
    }

    private function ensureWithinTransactionLiters(?FleetRule $rule, PosPurchase $purchase, FuelCard $card): void
    {
        if ($rule === null) {
            return;
        }

        $limit = (string) ($rule->rule_value['value'] ?? '0');

        if (BigDecimal::of($purchase->liters)->isGreaterThan($limit)) {
            throw $this->violation('liters', "This purchase exceeds the fleet's per-transaction limit of {$limit} L.", $card, FleetRuleType::MaxLitersPerTransaction);
        }
    }

    private function ensureWithinDailyTransactions(?FleetRule $rule, FuelCard $card, PosPurchase $purchase): void
    {
        if ($rule === null) {
            return;
        }

        $limit = (int) BigDecimal::of((string) ($rule->rule_value['value'] ?? '0'))->toInt();

        // The Beirut calendar day of the event, so a late event from
        // yesterday counts against yesterday, like the quota month does.
        $timezone = (string) config('fleetfuel.business_timezone');
        $dayStart = $purchase->transactedAt->setTimezone($timezone)->startOfDay()->utc();

        $already = FuelTransaction::query()
            ->where('fuel_card_id', $card->id)
            ->where('transacted_at', '>=', $dayStart)
            ->where('transacted_at', '<', $dayStart->addDay())
            ->count();

        if ($already >= $limit) {
            throw $this->violation('liters', "This card reached the fleet's daily limit of {$limit} transactions.", $card, FleetRuleType::MaxTransactionsPerDay);
        }
    }

    private function violation(string $field, string $message, FuelCard $card, FleetRuleType $type): FleetRuleViolation
    {
        return FleetRuleViolation::forRule($field, $message, (int) $card->company_id, $card->fleet_id, (int) $card->id, $type->value);
    }

    private function ensureCompanyActive(Fleet $fleet): void
    {
        $company = Company::query()->findOrFail($fleet->company_id);

        if ($company->status !== CompanyStatus::Active) {
            throw BusinessRuleViolation::companyInactive();
        }
    }
}
