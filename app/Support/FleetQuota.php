<?php

namespace App\Support;

use App\Models\CardMonthlyUsage;
use App\Models\Fleet;
use App\Models\FuelCard;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A fleet's aggregated monthly consumption against its quotas, from the
 * monthly counters the ledger keeps reconciled (card_monthly_usage). An
 * exceeded quota is a dashboard signal only: it never blocks a purchase
 * (docs/sdd/fase-1-flotas/spec.md, rule 3).
 */
final readonly class FleetQuota
{
    public function __construct(
        public string $month,
        public string $usedL,
        public string $usedUsd,
        public ?string $quotaL,
        public ?string $quotaUsd,
    ) {}

    /** The fleet's consumption in the Beirut month containing $at (default: now). */
    public static function for(Fleet $fleet, ?CarbonInterface $at = null): self
    {
        $month = BusinessMonth::for($at ?? CarbonImmutable::now());

        $sums = CardMonthlyUsage::query()
            ->where('month_start', $month)
            ->whereIn('fuel_card_id', FuelCard::query()->where('fleet_id', $fleet->id)->select('id'))
            ->selectRaw('COALESCE(SUM(used_l), 0) as used_l, COALESCE(SUM(used_usd), 0) as used_usd')
            ->first();

        return new self(
            $month,
            Decimal::normalize((string) $sums->used_l),
            Decimal::normalize((string) $sums->used_usd),
            $fleet->monthly_quota_liters,
            $fleet->monthly_quota_amount,
        );
    }

    /**
     * The same quota for a whole page of fleets in one query, keyed by the
     * fleet's ID. Fleets without consumption still get a zero quota.
     *
     * @param  iterable<Fleet>  $fleets
     * @return array<int, self>
     */
    public static function many(iterable $fleets, ?CarbonInterface $at = null): array
    {
        $fleets = $fleets instanceof \Traversable ? iterator_to_array($fleets) : (array) $fleets;
        $fleetIds = array_values(array_map(fn (Fleet $fleet): int => (int) $fleet->id, $fleets));

        if ($fleetIds === []) {
            return [];
        }

        $month = BusinessMonth::for($at ?? CarbonImmutable::now());

        $sums = CardMonthlyUsage::query()
            ->join('fuel_cards', 'fuel_cards.id', '=', 'card_monthly_usage.fuel_card_id')
            ->where('card_monthly_usage.month_start', $month)
            ->whereIn('fuel_cards.fleet_id', $fleetIds)
            ->groupBy('fuel_cards.fleet_id')
            ->selectRaw('fuel_cards.fleet_id, COALESCE(SUM(card_monthly_usage.used_l), 0) as used_l, COALESCE(SUM(card_monthly_usage.used_usd), 0) as used_usd')
            ->get()
            ->keyBy('fleet_id');

        $quotas = [];

        foreach ($fleets as $fleet) {
            $row = $sums->get($fleet->id);
            $quotas[(int) $fleet->id] = new self(
                $month,
                Decimal::normalize($row === null ? '0' : (string) $row->used_l),
                Decimal::normalize($row === null ? '0' : (string) $row->used_usd),
                $fleet->monthly_quota_liters,
                $fleet->monthly_quota_amount,
            );
        }

        return $quotas;
    }

    public function litersExceeded(): bool
    {
        return $this->quotaL !== null && BigDecimal::of($this->usedL)->isGreaterThan($this->quotaL);
    }

    public function amountExceeded(): bool
    {
        return $this->quotaUsd !== null && BigDecimal::of($this->usedUsd)->isGreaterThan($this->quotaUsd);
    }

    public function exceeded(): bool
    {
        return $this->litersExceeded() || $this->amountExceeded();
    }

    /**
     * The documented usage payload (docs/api/openapi.json, "FleetUsage"),
     * shared by the fleet detail and the /usage endpoint.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'month' => $this->month,
            'used_liters' => $this->usedL,
            'used_usd' => $this->usedUsd,
            'quota_liters' => $this->quotaL,
            'quota_usd' => $this->quotaUsd,
            'liters_percent' => $this->litersPercent(),
            'amount_percent' => $this->amountPercent(),
            'exceeded' => $this->exceeded(),
        ];
    }

    /** 0..n, for the dashboard progress bars; null when the fleet sets no liter quota. */
    public function litersPercent(): ?string
    {
        return $this->percent($this->usedL, $this->quotaL);
    }

    public function amountPercent(): ?string
    {
        return $this->percent($this->usedUsd, $this->quotaUsd);
    }

    private function percent(string $used, ?string $quota): ?string
    {
        if ($quota === null || BigDecimal::of($quota)->isZero()) {
            return null;
        }

        return (string) BigDecimal::of($used)
            ->dividedBy(BigDecimal::of($quota), 4, RoundingMode::HalfUp)
            ->multipliedBy(100)
            ->toScale(1, RoundingMode::HalfUp);
    }
}
