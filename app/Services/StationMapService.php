<?php

namespace App\Services;

use App\Models\FuelTransaction;
use App\Models\Station;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Builds the station markers for the interactive map
 * (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md, Part A), shared by
 * the JSON API (token) and the map page (session).
 *
 * Only stations with both coordinates are included. `address` is the
 * composed district + governorate (D3); `products` are the codes actually
 * sold there (D5); the day's figures follow the Beirut calendar (D6).
 */
class StationMapService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user, ?string $status, ?string $product, ?string $term): array
    {
        $stations = Station::query()
            ->visibleTo($user)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($term !== null, fn ($query) => $query->where('name', 'like', '%'.$term.'%'))
            ->when($product !== null, fn ($query) => $query->whereExists(function ($sub) use ($product): void {
                $sub->selectRaw('1')
                    ->from('fuel_transactions')
                    ->join('products', 'products.id', '=', 'fuel_transactions.product_id')
                    ->whereColumn('fuel_transactions.station_id', 'stations.id')
                    ->where('products.code', $product);
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $ids = $stations->pluck('id')->all();

        $timezone = (string) config('fleetfuel.business_timezone');
        $dayStart = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $dayEnd = $dayStart->addDay();

        $today = FuelTransaction::query()
            ->whereIn('station_id', $ids)
            ->where('transacted_at', '>=', $dayStart)
            ->where('transacted_at', '<', $dayEnd)
            ->toBase()
            ->select('station_id')
            ->selectRaw('COUNT(*) AS transactions, COALESCE(SUM(liters), 0) AS liters')
            ->groupBy('station_id')
            ->get()
            ->keyBy('station_id');

        $last = FuelTransaction::query()
            ->whereIn('station_id', $ids)
            ->toBase()
            ->select('station_id')
            ->selectRaw('MAX(transacted_at) AS last_at')
            ->groupBy('station_id')
            ->get()
            ->keyBy('station_id');

        $products = FuelTransaction::query()
            ->whereIn('fuel_transactions.station_id', $ids)
            ->join('products', 'products.id', '=', 'fuel_transactions.product_id')
            ->toBase()
            ->distinct()
            ->select('fuel_transactions.station_id', 'products.code')
            ->get()
            ->groupBy('station_id')
            ->map(fn ($rows) => $rows->pluck('code')->sort()->values()->all());

        return $stations->map(function (Station $station) use ($today, $last, $products): array {
            $metrics = $today->get($station->id);
            $lastAt = $last->get($station->id)?->last_at;

            return [
                'id' => $station->id,
                'name' => $station->name,
                'address' => trim($station->district.', '.$station->governorate, ', '),
                'latitude' => (float) $station->latitude,
                'longitude' => (float) $station->longitude,
                'status' => $station->is_active ? 'active' : 'inactive',
                'products' => $products->get($station->id, []),
                'transactions_today' => (int) ($metrics->transactions ?? 0),
                'liters_today' => number_format((float) ($metrics->liters ?? 0), 2, '.', ''),
                'last_transaction_at' => $lastAt === null ? null : CarbonImmutable::parse((string) $lastAt)->utc()->toIso8601ZuluString(),
            ];
        })->values()->all();
    }
}
