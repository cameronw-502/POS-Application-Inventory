<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\StockAdjustmentController;
use App\Http\Controllers\API\PosController as ApiPosController;
use App\Http\Controllers\API\ApiKeyController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\ReceiptController;

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

// Protected routes - require API key
Route::middleware(['api.key'])->group(function () {
    // User routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Inventory routes
    Route::apiResource('inventory', InventoryController::class);
    
    // Stock adjustment routes
    Route::apiResource('stock-adjustments', StockAdjustmentController::class);
    
    // POS API routes
    Route::get('pos/products', [ApiPosController::class, 'getProducts']);
    Route::post('pos/products', [ApiPosController::class, 'createProduct']);
    Route::post('pos/sales', [ApiPosController::class, 'createSale']);
    Route::get('pos/sales', [ApiPosController::class, 'getSales']);
    Route::get('pos/sales/{id}', [ApiPosController::class, 'getSale']);
    
    // Transaction routes
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::get('transactions/receipt/{receiptNumber}', [TransactionController::class, 'byReceiptNumber']);
    Route::post('transactions/report', [TransactionController::class, 'report']);
    Route::get('transactions/{id}', [TransactionController::class, 'show']);
    Route::get('transactions/{transaction}/receipt', [ReceiptController::class, 'getApiReceipt']);
});

// API Key Management (Admin only)
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function() {
    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::get('/api-keys/{id}', [ApiKeyController::class, 'show']);
    Route::put('/api-keys/{id}', [ApiKeyController::class, 'update']); 
    Route::delete('/api-keys/{id}', [ApiKeyController::class, 'destroy']);
    Route::post('/api-keys/{id}/revoke', [ApiKeyController::class, 'revoke']);
});