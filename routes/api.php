<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\StockAdjustmentController;
use App\Http\Controllers\API\PosController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Inventory routes
    Route::apiResource('inventory', InventoryController::class);
    
    // Stock adjustment routes
    Route::apiResource('stock-adjustments', StockAdjustmentController::class);
    
    // POS routes
    Route::get('pos/products', [PosController::class, 'getProducts']);
    Route::post('pos/sales', [PosController::class, 'createSale']);
    Route::get('pos/sales', [PosController::class, 'getSales']);
    Route::get('pos/sales/{id}', [PosController::class, 'getSale']);
});