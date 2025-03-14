<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\StockAdjustmentController;
use App\Http\Controllers\API\PosController as ApiPosController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/ping', function() {
    return response()->json(['message' => 'API is working!', 'status' => 'success'], 200);
});
Route::get('/test', function() {
    return response()->json([
        'message' => 'API test endpoint reached successfully',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Inventory routes
    Route::apiResource('inventory', InventoryController::class);
    
    // Stock adjustment routes
    Route::apiResource('stock-adjustments', StockAdjustmentController::class);
    
    // POS API routes
    Route::get('pos/products', [ApiPosController::class, 'getProducts']);
    Route::post('pos/sales', [ApiPosController::class, 'createSale']);
    Route::get('pos/sales', [ApiPosController::class, 'getSales']);
    Route::get('pos/sales/{id}', [ApiPosController::class, 'getSale']);
});