<?php

use App\Http\Controllers\Api\V1\CardBalanceController;
use App\Http\Controllers\Api\V1\DeliveryOrderController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\FleetController;
use App\Http\Controllers\Api\V1\FuelCardController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\MapController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationRuleController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ProductPriceController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\StationController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransactionExportController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

// Versioned API (docs/05-API-CONTRACT.md, docs/api/openapi.json). Only
// bearer tokens authenticate here: config/sanctum.php has no session guard,
// so a browser cookie never counts. Token abilities are necessary but never
// sufficient: role middleware, scoped lookups and policies still apply.

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public: credentials in, token out; 5 requests/minute per email + IP.
    Route::post('auth/token', [TokenController::class, 'store'])
        ->middleware('throttle:token-issue')
        ->name('auth.token.store');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::delete('auth/token', [TokenController::class, 'destroy'])->name('auth.token.destroy');

        // Reference data for every role.
        Route::middleware('abilities:reference:read')->group(function () {
            Route::get('stations', [StationController::class, 'index'])->name('stations.index');
            Route::get('products/prices', [ProductPriceController::class, 'index'])->name('products.prices');
        });

        // Favourites, for every role (docs/sdd/fase-3-mapa-favoritos-preferencias/, Part B).
        Route::get('favorites', [FavoriteController::class, 'index'])->middleware('abilities:favorites:read')->name('favorites.index');
        Route::post('favorites', [FavoriteController::class, 'store'])->middleware('abilities:favorites:write')->name('favorites.store');
        Route::put('favorites/reorder', [FavoriteController::class, 'reorder'])->middleware('abilities:favorites:write')->name('favorites.reorder');
        Route::delete('favorites/{favorite}', [FavoriteController::class, 'destroy'])
            ->middleware('abilities:favorites:write')->whereNumber('favorite')->name('favorites.destroy');

        // Preferences, for every role (Part C).
        Route::get('preferences', [PreferenceController::class, 'index'])->middleware('abilities:preferences:read')->name('preferences.index');
        Route::put('preferences', [PreferenceController::class, 'update'])->middleware('abilities:preferences:write')->name('preferences.update');
        Route::get('preferences/{key}', [PreferenceController::class, 'showKey'])
            ->middleware('abilities:preferences:read')->name('preferences.show');
        Route::put('preferences/{key}', [PreferenceController::class, 'updateKey'])
            ->middleware('abilities:preferences:write')->name('preferences.update-key');

        // POS ingestion: station operators only, 60 requests/minute per station.
        Route::post('transactions', [TransactionController::class, 'store'])
            ->middleware(['role:station_operator', 'abilities:transactions:create', 'throttle:pos-writes'])
            ->name('transactions.store');

        Route::middleware('abilities:transactions:read')->group(function () {
            Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
            Route::get('transactions/{transaction}', [TransactionController::class, 'show'])
                ->whereNumber('transaction')
                ->name('transactions.show');
        });

        Route::get('cards/{card_no}/balance', [CardBalanceController::class, 'show'])
            ->middleware('abilities:cards:read')
            ->where('card_no', '[A-Za-z0-9-]{1,40}')
            ->name('cards.balance');

        // Company data: admins and company managers only. {card} and
        // {delivery} are looked up through the caller's tenant scope
        // (AppServiceProvider).
        Route::middleware('role:admin,company_manager')->group(function () {
            Route::patch('cards/{card}', [FuelCardController::class, 'update'])
                ->middleware('abilities:cards:write')
                ->whereNumber('card')
                ->name('cards.update');

            Route::get('vehicles', [VehicleController::class, 'index'])->middleware('abilities:fleet:read')->name('vehicles.index');
            Route::post('vehicles', [VehicleController::class, 'store'])->middleware('abilities:fleet:write')->name('vehicles.store');
            Route::get('drivers', [DriverController::class, 'index'])->middleware('abilities:fleet:read')->name('drivers.index');
            Route::post('drivers', [DriverController::class, 'store'])->middleware('abilities:fleet:write')->name('drivers.store');

            // Station map markers (docs/sdd/fase-3-mapa-favoritos-preferencias/, Part A).
            Route::get('map/stations', [MapController::class, 'stations'])->middleware('abilities:map:read')->name('map.stations');

            // Bulk CSV import (docs/sdd/fase-2-importacion-notificaciones/spec.md, D8):
            // a multipart file or a JSON rows array; a template download. The
            // type is checked in the controller, so an unknown type is a 404
            // after authentication (never a bare 404 before the auth check).
            Route::post('import/{type}', [ImportController::class, 'store'])
                ->middleware('abilities:fleet:write')
                ->name('import.store');
            Route::get('import/templates/{type}', [ImportController::class, 'template'])
                ->middleware('abilities:fleet:read')
                ->name('import.template');

            // Fleets (docs/sdd/fase-1-flotas/spec.md): a company's
            // sub-grouping of vehicles, drivers and cards. {fleet} is
            // tenant-scoped like {card}.
            Route::get('fleets', [FleetController::class, 'index'])->middleware('abilities:fleet:read')->name('fleets.index');
            Route::post('fleets', [FleetController::class, 'store'])->middleware('abilities:fleet:write')->name('fleets.store');
            Route::get('fleets/{fleet}', [FleetController::class, 'show'])->middleware('abilities:fleet:read')->whereNumber('fleet')->name('fleets.show');
            Route::put('fleets/{fleet}', [FleetController::class, 'update'])->middleware('abilities:fleet:write')->whereNumber('fleet')->name('fleets.update');
            Route::delete('fleets/{fleet}', [FleetController::class, 'destroy'])->middleware('abilities:fleet:write')->whereNumber('fleet')->name('fleets.destroy');
            Route::get('fleets/{fleet}/usage', [FleetController::class, 'usage'])->middleware('abilities:fleet:read')->whereNumber('fleet')->name('fleets.usage');
            Route::get('fleets/{fleet}/vehicles', [FleetController::class, 'vehicles'])->middleware('abilities:fleet:read')->whereNumber('fleet')->name('fleets.vehicles');
            Route::get('fleets/{fleet}/drivers', [FleetController::class, 'drivers'])->middleware('abilities:fleet:read')->whereNumber('fleet')->name('fleets.drivers');
            Route::get('fleets/{fleet}/cards', [FleetController::class, 'cards'])->middleware('abilities:fleet:read')->whereNumber('fleet')->name('fleets.cards');

            Route::get('delivery-orders', [DeliveryOrderController::class, 'index'])->middleware('abilities:deliveries:read')->name('delivery-orders.index');
            Route::post('delivery-orders', [DeliveryOrderController::class, 'store'])->middleware('abilities:deliveries:write')->name('delivery-orders.store');
            Route::get('delivery-orders/{delivery}', [DeliveryOrderController::class, 'show'])
                ->middleware('abilities:deliveries:read')
                ->whereNumber('delivery')
                ->name('delivery-orders.show');
            // Either ability gets past the route; the request then requires
            // deliveries:status from an admin and deliveries:write from a
            // manager, who may only cancel their own pending order.
            Route::patch('delivery-orders/{delivery}/status', [DeliveryOrderController::class, 'updateStatus'])
                ->middleware('ability:deliveries:status,deliveries:write')
                ->whereNumber('delivery')
                ->name('delivery-orders.status');

            // Reports and the accounting CSV, scoped like the ledger list.
            Route::get('reports/consumption', [ReportController::class, 'consumption'])->middleware('abilities:reports:read')->name('reports.consumption');
            Route::get('exports/transactions.csv', TransactionExportController::class)->middleware('abilities:exports:read')->name('exports.transactions');

            // Notifications (docs/sdd/fase-2-importacion-notificaciones/spec.md, B4):
            // the caller's inbox and the rules that feed it.
            Route::get('notifications', [NotificationController::class, 'index'])->middleware('abilities:notifications:read')->name('notifications.index');
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->middleware('abilities:notifications:read')->name('notifications.unread-count');
            Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->middleware('abilities:notifications:write')->name('notifications.mark-all-read');
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])
                ->middleware('abilities:notifications:write')->whereNumber('notification')->name('notifications.read');

            Route::get('notifications/rules', [NotificationRuleController::class, 'index'])->middleware('abilities:notifications:read')->name('notifications.rules.index');
            Route::post('notifications/rules', [NotificationRuleController::class, 'store'])->middleware('abilities:notifications:write')->name('notifications.rules.store');
            Route::get('notifications/rules/{notificationRule}', [NotificationRuleController::class, 'show'])->middleware('abilities:notifications:read')->name('notifications.rules.show');
            Route::put('notifications/rules/{notificationRule}', [NotificationRuleController::class, 'update'])->middleware('abilities:notifications:write')->name('notifications.rules.update');
            Route::delete('notifications/rules/{notificationRule}', [NotificationRuleController::class, 'destroy'])->middleware('abilities:notifications:write')->name('notifications.rules.destroy');
        });
    });
});
