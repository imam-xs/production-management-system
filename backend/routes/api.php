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
|---------------------------------------------------------------------------
| Explicit routes, not Route::apiResource().
|---------------------------------------------------------------------------
|
| Laravel resolves a plain (non-model) route parameter to a controller method
| argument by matching NAMES, not position — apiResource's auto-generated
| parameter name for `raw-materials` would be `raw_material`, which would not
| bind to a `$rawMaterial` method argument. Writing routes explicitly, with a
| URI placeholder that's spelled exactly like the controller argument,
| sidesteps that mismatch entirely and also leaves room for the
| non-CRUD actions (execute, trace, receipts) apiResource can't express.
|
*/

Route::prefix('v1')->name('v1.')->group(function (): void {

    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        // Raw materials
        Route::get('raw-materials', [RawMaterialController::class, 'index'])->name('raw-materials.index');
        Route::post('raw-materials', [RawMaterialController::class, 'store'])->name('raw-materials.store');
        Route::get('raw-materials/{rawMaterial}', [RawMaterialController::class, 'show'])->name('raw-materials.show');
        Route::put('raw-materials/{rawMaterial}', [RawMaterialController::class, 'update'])->name('raw-materials.update');
        Route::patch('raw-materials/{rawMaterial}', [RawMaterialController::class, 'update']);
        Route::delete('raw-materials/{rawMaterial}', [RawMaterialController::class, 'destroy'])->name('raw-materials.destroy');

        // Semi-finished products
        Route::get('semi-finished-products', [SemiFinishedProductController::class, 'index'])->name('semi-finished-products.index');
        Route::post('semi-finished-products', [SemiFinishedProductController::class, 'store'])->name('semi-finished-products.store');
        Route::get('semi-finished-products/{semiFinishedProduct}', [SemiFinishedProductController::class, 'show'])->name('semi-finished-products.show');
        Route::put('semi-finished-products/{semiFinishedProduct}', [SemiFinishedProductController::class, 'update'])->name('semi-finished-products.update');
        Route::patch('semi-finished-products/{semiFinishedProduct}', [SemiFinishedProductController::class, 'update']);
        Route::delete('semi-finished-products/{semiFinishedProduct}', [SemiFinishedProductController::class, 'destroy'])->name('semi-finished-products.destroy');

        // Finished products
        Route::get('finished-products', [FinishedProductController::class, 'index'])->name('finished-products.index');
        Route::post('finished-products', [FinishedProductController::class, 'store'])->name('finished-products.store');
        Route::get('finished-products/{finishedProduct}', [FinishedProductController::class, 'show'])->name('finished-products.show');
        Route::put('finished-products/{finishedProduct}', [FinishedProductController::class, 'update'])->name('finished-products.update');
        Route::patch('finished-products/{finishedProduct}', [FinishedProductController::class, 'update']);
        Route::delete('finished-products/{finishedProduct}', [FinishedProductController::class, 'destroy'])->name('finished-products.destroy');

        // Inventory
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
        Route::get('inventory/stage/{stage}', [InventoryController::class, 'byStage'])->name('inventory.by-stage');
        Route::post('inventory/receipts', [InventoryController::class, 'receive'])->name('inventory.receipts');
        Route::get('items/{item}/movements', [InventoryController::class, 'movements'])->name('items.movements');

        // Recipe (bill of materials) — read-only; see RecipeController for why.
        Route::get('items/{item}/recipe', [RecipeController::class, 'show'])->name('items.recipe');

        // Production orders
        Route::get('production-orders', [ProductionOrderController::class, 'index'])->name('production-orders.index');
        Route::post('production-orders', [ProductionOrderController::class, 'store'])->name('production-orders.store');
        Route::get('production-orders/{productionOrder}', [ProductionOrderController::class, 'show'])->name('production-orders.show');
        Route::post('production-orders/{productionOrder}/execute', [ProductionOrderController::class, 'execute'])->name('production-orders.execute');

        // Production events recorded by the RabbitMQ consumer
        Route::get('production-events', [ProductionEventController::class, 'index'])->name('production-events.index');

        // Batches & traceability
        Route::get('batches', [BatchController::class, 'index'])->name('batches.index');
        Route::get('batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
        Route::get('batches/{batch}/trace', [BatchController::class, 'trace'])->name('batches.trace');
        Route::get('batches/{batch}/trace-downstream', [BatchController::class, 'traceDownstream'])->name('batches.trace-downstream');
    });
});
