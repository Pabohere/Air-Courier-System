<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\EntryDraftController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentPhotoController;
use App\Http\Controllers\ShipmentWorkflowController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

Route::get('/app.webmanifest', fn () => response()->file(public_path('manifest.webmanifest'), ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']))->name('pwa.manifest');

Route::post('/language', LanguageController::class)->name('language.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});
Route::middleware(['auth', EnsureActiveUser::class, 'auth.session'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/{side}', DashboardController::class)->whereIn('side', ['ygn', 'sg'])->name('dashboard.branch');
    Route::post('/entry-drafts', [EntryDraftController::class, 'store'])->name('entry-drafts.store');
    Route::get('/entry-drafts/{draft}/photos/{photo}', [EntryDraftController::class, 'photo'])->whereUuid('photo')->name('entry-drafts.photo');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/{shipment}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::delete('/inventory/{shipment}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::post('/inventory/{shipment}/restore', [InventoryController::class, 'restore'])->name('inventory.restore');
    Route::post('/inventory/{shipment}/move', [InventoryController::class, 'move'])->name('inventory.move');
    Route::post('/inventory/{shipment}/return', [InventoryController::class, 'returnToSender'])->name('inventory.return');
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::resource('customers', CustomerController::class)->except('destroy');
    Route::resource('shipments', ShipmentController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/shipments/{shipment}/photos', [ShipmentPhotoController::class, 'store'])->name('shipments.photos.store');
    Route::get('/shipments/{shipment}/photos/{photo}', [ShipmentPhotoController::class, 'show'])->whereUuid('photo')->name('shipments.photos.show');
    Route::post('/shipments/{shipment}/receive', [ShipmentWorkflowController::class, 'receive'])->name('shipments.receive');
    Route::post('/shipments/{shipment}/collect', [ShipmentWorkflowController::class, 'collect'])->name('shipments.collect');
    Route::post('/shipments/{shipment}/status', [ShipmentController::class, 'status'])->name('shipments.status');
    Route::get('/shipments/{shipment}/receipt', [ShipmentController::class, 'receipt'])->name('shipments.receipt');
    Route::post('/shipments/{shipment}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/shipments/{shipment}/refunds', [PaymentController::class, 'refund'])->middleware('can:admin')->name('payments.refund');
    Route::resource('dispatches', DispatchController::class)->only(['index', 'store', 'show']);
    Route::post('/dispatches/{dispatch}/handover', [DispatchController::class, 'handover'])->name('dispatches.handover');
    Route::delete('/dispatches/{dispatch}/shipments/{shipment}', [DispatchController::class, 'unassign'])->name('dispatches.unassign');
    Route::get('/dispatches/{dispatch}/manifest', [DispatchController::class, 'manifest'])->name('dispatches.manifest');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::post('/reports/reconcile', [ReportController::class, 'reconcile'])->name('reports.reconcile');
    Route::middleware('can:admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        foreach (['company', 'destination', 'method', 'user'] as $action) {
            Route::post('/settings/'.$action, [SettingsController::class, $action])->name('settings.'.$action);
        }Route::get('/audit', [SettingsController::class, 'audit'])->name('settings.audit');
    });
});
