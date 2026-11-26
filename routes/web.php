<?php

use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\ChangePasswordController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\CompanyStationController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeliveryOrderController;
use App\Http\Controllers\Web\DriverController;
use App\Http\Controllers\Web\ExchangeRateController;
use App\Http\Controllers\Web\FavoriteController;
use App\Http\Controllers\Web\FleetController;
use App\Http\Controllers\Web\FuelCardController;
use App\Http\Controllers\Web\ImportController;
use App\Http\Controllers\Web\MapController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\NotificationRuleController;
use App\Http\Controllers\Web\PreferenceController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProductPriceController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\StationController;
use App\Http\Controllers\Web\StationHomeController;
use App\Http\Controllers\Web\TransactionController;
use App\Http\Controllers\Web\TransactionExportController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\VehicleController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::view('/', 'home')->name('home');

// Sign-in and sign-out use Fortify's controllers (throttling, session
// regeneration). These are the only Fortify routes: no registration,
// password reset or two-factor endpoints exist (D16).
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Signed-in, active accounts only. `role:` fences off each role's area;
// scoped queries and policies still decide which records a user may see.
// Route parameters such as {vehicle} or {card} are resolved through the
// signed-in user's tenant scope (AppServiceProvider), so another company's
// record is a 404. There are no delete routes: records are deactivated or
// archived, and the ledger keeps referring to them.
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    // Forced password change (available to every signed-in user; the
    // password.changed middleware lets these two routes through).
    Route::get('/password/change', [ChangePasswordController::class, 'edit'])->name('password.change.edit');
    Route::put('/password/change', [ChangePasswordController::class, 'update'])->name('password.change.update');

    Route::get('/dashboard', DashboardController::class)
        ->middleware('role:admin,company_manager')
        ->name('dashboard');

    Route::get('/station', StationHomeController::class)
        ->middleware('role:station_operator')
        ->name('station.home');

    // The purchase ledger for every role, scoped like the API list: all
    // purchases for admins, the own company for managers, the own station
    // for operators. /results is the same listing for page scripts (JSON).
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/results', [TransactionController::class, 'results'])->name('transactions.results');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])
        ->whereNumber('transaction')
        ->name('transactions.show');

    // Favourites (every role): the star toggle and the dashboard reorder
    // (docs/sdd/fase-3-mapa-favoritos-preferencias/, Part B).
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/favorites', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::put('/favorites/reorder', [FavoriteController::class, 'reorder'])->name('favorites.reorder');
    Route::delete('/favorites/{favorite}', [FavoriteController::class, 'destroy'])
        ->whereNumber('favorite')->name('favorites.destroy');

    // Preferences, for every role (Part C).
    Route::get('/preferences', [PreferenceController::class, 'edit'])->name('preferences.edit');
    Route::put('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');

    // Reference data: every role reads it; only admins change it.
    Route::get('/stations', [StationController::class, 'index'])->name('stations.index');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}/prices', [ProductPriceController::class, 'index'])->name('products.prices.index');

    Route::middleware('role:admin')->group(function () {
        Route::resource('companies', CompanyController::class)->except(['show', 'destroy']);
        Route::patch('/companies/{company}/status', [CompanyController::class, 'updateStatus'])->name('companies.status');

        // Which stations a company may use (Fase 6, área 3): admin only.
        Route::get('/companies/{company}/stations', [CompanyStationController::class, 'edit'])
            ->whereNumber('company')->name('companies.stations.edit');
        Route::put('/companies/{company}/stations', [CompanyStationController::class, 'update'])
            ->whereNumber('company')->name('companies.stations.update');

        Route::resource('stations', StationController::class)->except(['index', 'show', 'destroy']);
        Route::patch('/stations/{station}/active', [StationController::class, 'updateActive'])->name('stations.active');

        Route::resource('products', ProductController::class)->only(['edit', 'update']);
        Route::patch('/products/{product}/active', [ProductController::class, 'updateActive'])->name('products.active');
        Route::post('/products/{product}/prices', [ProductPriceController::class, 'store'])->name('products.prices.store');

        // USD/LBP status and manual overrides. Syncing itself is the
        // scheduled `rates:sync` command, never a page request.
        Route::get('/integrations/exchange-rates', [ExchangeRateController::class, 'index'])->name('integrations.exchange-rates');
        Route::post('/integrations/exchange-rates/overrides', [ExchangeRateController::class, 'store'])->name('integrations.exchange-rates.overrides.store');

        // Read-only audit trail with filters; nothing can edit or delete it.
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');

        // User administration (Fase 4): admin only. No delete route.
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->whereNumber('user')->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->whereNumber('user')->name('users.status');
        Route::post('/users/{user}/password', [UserController::class, 'resetPassword'])->whereNumber('user')->name('users.password');
    });

    // Fleet: admins for every company, managers for their own.
    Route::middleware('role:admin,company_manager')->group(function () {
        Route::resource('vehicles', VehicleController::class)->except(['show', 'destroy']);
        Route::patch('/vehicles/{vehicle}/active', [VehicleController::class, 'updateActive'])->name('vehicles.active');

        Route::resource('drivers', DriverController::class)->except(['show', 'destroy']);
        Route::patch('/drivers/{driver}/active', [DriverController::class, 'updateActive'])->name('drivers.active');

        Route::resource('cards', FuelCardController::class)->except(['destroy']);
        Route::patch('/cards/{card}/limits', [FuelCardController::class, 'updateLimits'])->name('cards.limits');
        Route::patch('/cards/{card}/status', [FuelCardController::class, 'updateStatus'])->name('cards.status');

        // Fleets: a company's sub-grouping of vehicles, drivers and cards
        // with an aggregated monthly quota and basic fuel rules
        // (docs/sdd/fase-1-flotas/spec.md). Deleting a fleet only ungroups
        // its members (SET NULL); the audit keeps the history.
        Route::resource('fleets', FleetController::class);
        Route::patch('/fleets/{fleet}/status', [FleetController::class, 'updateStatus'])->name('fleets.status');
        Route::post('/fleets/{fleet}/rules', [FleetController::class, 'storeRule'])->name('fleets.rules.store');
        Route::delete('/fleets/{fleet}/rules/{rule}', [FleetController::class, 'destroyRule'])->name('fleets.rules.destroy');
        Route::post('/fleets/{fleet}/assign', [FleetController::class, 'assign'])->name('fleets.assign');
        Route::post('/fleets/{fleet}/unassign', [FleetController::class, 'unassign'])->name('fleets.unassign');

        // Interactive station map (docs/sdd/fase-3-mapa-favoritos-preferencias/, Part A).
        Route::get('/map', [MapController::class, 'index'])->name('map.index');
        Route::get('/map/stations', [MapController::class, 'data'])->name('map.data');

        // Bulk CSV import of vehicles, drivers and cards
        // (docs/sdd/fase-2-importacion-notificaciones/spec.md, A2). The type is
        // checked in the controller, so an unknown type is a 404 after auth
        // (a guest is sent to sign in, never a bare 404).
        Route::get('/import', [ImportController::class, 'index'])->name('import.index');
        Route::get('/import/{type}/template', [ImportController::class, 'template'])->name('import.template');
        Route::post('/import/{type}', [ImportController::class, 'store'])->name('import.store');

        // Diesel deliveries. Managers request and may cancel their own
        // pending orders; only admins schedule, dispatch and deliver
        // (DeliveryOrderService decides, with the order locked).
        Route::resource('deliveries', DeliveryOrderController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('/deliveries/{delivery}/panel', [DeliveryOrderController::class, 'panel'])->name('deliveries.panel');
        Route::patch('/deliveries/{delivery}/status', [DeliveryOrderController::class, 'updateStatus'])->name('deliveries.status');

        // Reports read ReportRepository through ReportScope, which pins a
        // manager to their own company. The CSV is the API's file, served
        // to the browser session.
        Route::redirect('/reports', '/reports/consumption')->name('reports.index');
        Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
            Route::get('/consumption', 'consumption')->name('consumption');
            Route::get('/top-stations', 'topStations')->name('top-stations');
            Route::get('/quota-exceptions', 'quotaExceptions')->name('quota-exceptions');
            Route::get('/anomalies', 'anomalies')->name('anomalies');
            Route::get('/efficiency', 'efficiency')->name('efficiency');
            Route::get('/delivery-sla', 'deliverySla')->name('delivery-sla');
        });
        Route::get('/exports/transactions.csv', TransactionExportController::class)->name('exports.transactions');

        // Notifications (docs/sdd/fase-2-importacion-notificaciones/spec.md, B3):
        // a manager's inbox and the rules that feed it.
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])
            ->whereNumber('notification')->name('notifications.read');

        Route::get('/notifications/rules', [NotificationRuleController::class, 'index'])->name('notifications.rules.index');
        Route::get('/notifications/rules/create', [NotificationRuleController::class, 'create'])->name('notifications.rules.create');
        Route::post('/notifications/rules', [NotificationRuleController::class, 'store'])->name('notifications.rules.store');
        Route::get('/notifications/rules/{notificationRule}/edit', [NotificationRuleController::class, 'edit'])->name('notifications.rules.edit');
        Route::put('/notifications/rules/{notificationRule}', [NotificationRuleController::class, 'update'])->name('notifications.rules.update');
        Route::delete('/notifications/rules/{notificationRule}', [NotificationRuleController::class, 'destroy'])->name('notifications.rules.destroy');
        Route::patch('/notifications/rules/{notificationRule}/toggle', [NotificationRuleController::class, 'toggle'])->name('notifications.rules.toggle');
    });
});
