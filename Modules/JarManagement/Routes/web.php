<?php

use Illuminate\Support\Facades\Route;
use Modules\JarManagement\Controllers\Api\JarLookupController;
use Modules\JarManagement\Controllers\DashboardController;
use Modules\JarManagement\Controllers\DeliveryController;
use Modules\JarManagement\Controllers\FactoryReceiptController;
use Modules\JarManagement\Controllers\JarCustomerController;
use Modules\JarManagement\Controllers\JarRegistrationController;
use Modules\JarManagement\Controllers\JarTraceController;
use Modules\JarManagement\Controllers\JarVehicleController;
use Modules\JarManagement\Controllers\ProductionBatchController;
use Modules\JarManagement\Controllers\QuarantineController;
use Modules\JarManagement\Controllers\VehicleLotController;

Route::middleware(['auth', 'verified'])->prefix('jar')->name('jar.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:jar.dashboard.view')->name('dashboard');

    // Lightweight JSON used by JarScanInput.vue and the delivery customer
    // typeahead — kept on 'web' (session auth) rather than a separate api.php.
    // Gated on holding any jar-module permission, not just being logged in,
    // since jar codes and customer phone numbers aren't meant for every
    // authenticated user across the wider system.
    Route::middleware('permission:jar.production.manage|jar.dispatch.manage|jar.receipt.manage|jar.delivery.manage|jar.inventory.view|jar.inventory.manage')
        ->get('/lookup/jars/{jarCode}', [JarLookupController::class, 'jar'])->name('lookup.jar');
    Route::middleware('permission:jar.delivery.manage|jar.customer.manage')
        ->get('/lookup/customers', [JarLookupController::class, 'customers'])->name('lookup.customers');

    Route::middleware('permission:jar.production.manage')->prefix('production')->name('production.')->group(function () {
        Route::get('/', [ProductionBatchController::class, 'index'])->name('index');
        Route::post('/', [ProductionBatchController::class, 'store'])->name('store');
        Route::get('/{batch}', [ProductionBatchController::class, 'show'])->whereNumber('batch')->name('show');
        Route::post('/{batch}/scan', [ProductionBatchController::class, 'scanJar'])->whereNumber('batch')->name('scan');
        Route::post('/{batch}/cleaning', [ProductionBatchController::class, 'recordCleaning'])->whereNumber('batch')->name('cleaning');
        Route::post('/{batch}/refilling', [ProductionBatchController::class, 'recordRefilling'])->whereNumber('batch')->name('refilling');
        Route::post('/{batch}/sealing', [ProductionBatchController::class, 'recordSealing'])->whereNumber('batch')->name('sealing');
        Route::post('/{batch}/quality-approval', [ProductionBatchController::class, 'submitQualityApproval'])->whereNumber('batch')->name('quality-approval');
        Route::post('/register-jars', [JarRegistrationController::class, 'store'])->name('register-jars');
    });

    Route::middleware('permission:jar.receipt.manage')->prefix('receipts')->name('receipts.')->group(function () {
        Route::get('/', [FactoryReceiptController::class, 'index'])->name('index');
        Route::post('/open/{lot}', [FactoryReceiptController::class, 'open'])->whereNumber('lot')->name('open');
        Route::get('/{receipt}', [FactoryReceiptController::class, 'show'])->whereNumber('receipt')->name('show');
        Route::post('/{receipt}/scan', [FactoryReceiptController::class, 'scan'])->whereNumber('receipt')->name('scan');
        Route::post('/{receipt}/close', [FactoryReceiptController::class, 'close'])->whereNumber('receipt')->name('close');
    });

    Route::middleware('permission:jar.inventory.view|jar.inventory.manage|jar.receipt.manage')
        ->prefix('quarantine')->name('quarantine.')->group(function () {
            Route::get('/', [QuarantineController::class, 'index'])->name('index');
            Route::post('/{jar}/resolve', [QuarantineController::class, 'resolve'])->whereNumber('jar')->name('resolve');
            Route::post('/returns/{return}/verify', [QuarantineController::class, 'verifyReturn'])->whereNumber('return')->name('returns.verify');
        });

    Route::middleware('permission:jar.dispatch.manage|jar.dispatch.view')->prefix('dispatch')->name('dispatch.')->group(function () {
        Route::get('/', [VehicleLotController::class, 'index'])->name('index');
        Route::get('/{lot}', [VehicleLotController::class, 'show'])->whereNumber('lot')->name('show');
    });
    Route::middleware('permission:jar.dispatch.manage')->prefix('dispatch')->name('dispatch.')->group(function () {
        Route::post('/', [VehicleLotController::class, 'store'])->name('store');
        Route::post('/{lot}/load', [VehicleLotController::class, 'loadJar'])->whereNumber('lot')->name('load');
        Route::post('/{lot}/dispatch', [VehicleLotController::class, 'dispatch'])->whereNumber('lot')->name('dispatch');
    });

    Route::middleware('permission:jar.delivery.manage')->prefix('delivery')->name('delivery.')->group(function () {
        Route::get('/', [DeliveryController::class, 'myLots'])->name('my-lots');
        Route::post('/{lot}', [DeliveryController::class, 'store'])->whereNumber('lot')->name('store');
    });

    Route::middleware('permission:jar.customer.manage')->prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [JarCustomerController::class, 'index'])->name('index');
        Route::post('/', [JarCustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [JarCustomerController::class, 'show'])->whereNumber('customer')->name('show');
        Route::put('/{customer}', [JarCustomerController::class, 'update'])->whereNumber('customer')->name('update');
    });

    Route::middleware('permission:jar.vehicle.manage')->prefix('vehicles')->name('vehicles.')->group(function () {
        Route::get('/', [JarVehicleController::class, 'index'])->name('index');
        Route::post('/', [JarVehicleController::class, 'storeVehicle'])->name('store');
        Route::put('/{jarVehicle}', [JarVehicleController::class, 'updateVehicle'])->whereNumber('jarVehicle')->name('update');
        Route::post('/drivers', [JarVehicleController::class, 'storeDriver'])->name('drivers.store');
        Route::put('/drivers/{jarDriver}', [JarVehicleController::class, 'updateDriver'])->whereNumber('jarDriver')->name('drivers.update');
    });

    Route::middleware('permission:jar.trace.view')->get('/trace', [JarTraceController::class, 'index'])->name('trace');
});
