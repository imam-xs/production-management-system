<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BatchController;
use App\Http\Controllers\Api\V1\FinishedProductController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductionEventController;
use App\Http\Controllers\Api\V1\ProductionOrderController;
use App\Http\Controllers\Api\V1\RawMaterialController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\SemiFinishedProductController;
use Illuminate\Support\Facades\Route;

/*
| Explicit routes, not Route::apiResource(): a plain (non-model) route
| parameter binds to a controller argument by NAME, and apiResource would
| generate `raw_material` where the controller expects `$rawMaterial`. Writing
| them out also leaves room for the non-CRUD actions (execute, trace, receipts).
*/

Route::prefix('v1')->name('v1.')->group(function (): void {

    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {

        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });

        // The three item resources are the same six routes with a different
        // controller — the placeholder is spelled like the controller argument.
        Route::prefix('raw-materials')->name('raw-materials.')->group(function (): void {
            Route::get('/', [RawMaterialController::class, 'index'])->name('index');
            Route::post('/', [RawMaterialController::class, 'store'])->name('store');
            Route::get('{rawMaterial}', [RawMaterialController::class, 'show'])->name('show');
            Route::match(['put', 'patch'], '{rawMaterial}', [RawMaterialController::class, 'update'])->name('update');
            Route::delete('{rawMaterial}', [RawMaterialController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('semi-finished-products')->name('semi-finished-products.')->group(function (): void {
            Route::get('/', [SemiFinishedProductController::class, 'index'])->name('index');
            Route::post('/', [SemiFinishedProductController::class, 'store'])->name('store');
            Route::get('{semiFinishedProduct}', [SemiFinishedProductController::class, 'show'])->name('show');
            Route::match(['put', 'patch'], '{semiFinishedProduct}', [SemiFinishedProductController::class, 'update'])->name('update');
            Route::delete('{semiFinishedProduct}', [SemiFinishedProductController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('finished-products')->name('finished-products.')->group(function (): void {
            Route::get('/', [FinishedProductController::class, 'index'])->name('index');
            Route::post('/', [FinishedProductController::class, 'store'])->name('store');
            Route::get('{finishedProduct}', [FinishedProductController::class, 'show'])->name('show');
            Route::match(['put', 'patch'], '{finishedProduct}', [FinishedProductController::class, 'update'])->name('update');
            Route::delete('{finishedProduct}', [FinishedProductController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('inventory')->name('inventory.')->group(function (): void {
            Route::get('/', [InventoryController::class, 'index'])->name('index');
            Route::get('low-stock', [InventoryController::class, 'lowStock'])->name('low-stock');
            Route::get('stage/{stage}', [InventoryController::class, 'byStage'])->name('by-stage');
            Route::post('receipts', [InventoryController::class, 'receive'])->name('receipts');
        });

        // Both hang off a single item; the recipe is read-only — see RecipeController.
        Route::prefix('items/{item}')->name('items.')->group(function (): void {
            Route::get('movements', [InventoryController::class, 'movements'])->name('movements');
            Route::get('recipe', [RecipeController::class, 'show'])->name('recipe');
        });

        Route::prefix('production-orders')->name('production-orders.')->group(function (): void {
            Route::get('/', [ProductionOrderController::class, 'index'])->name('index');
            Route::post('/', [ProductionOrderController::class, 'store'])->name('store');
            Route::get('{productionOrder}', [ProductionOrderController::class, 'show'])->name('show');
            Route::post('{productionOrder}/execute', [ProductionOrderController::class, 'execute'])->name('execute');
        });

        // Written by the queue worker, exposed so the async path is observable.
        Route::get('production-events', [ProductionEventController::class, 'index'])->name('production-events.index');

        Route::prefix('batches')->name('batches.')->group(function (): void {
            Route::get('/', [BatchController::class, 'index'])->name('index');
            Route::get('{batch}', [BatchController::class, 'show'])->name('show');
            Route::get('{batch}/trace', [BatchController::class, 'trace'])->name('trace');
            Route::get('{batch}/trace-downstream', [BatchController::class, 'traceDownstream'])->name('trace-downstream');
        });
    });
});
