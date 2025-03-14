<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\StockAdjustmentController;
use App\Http\Controllers\API\PosController as ApiPosController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\ReceiptController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\RegisterPosController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/ping', function() {
    return response()->json(['message' => 'API is working!', 'status' => 'success'], 200);
});

// Protected routes with Sanctum authentication
Route::middleware('auth:sanctum')->group(function () {
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
    
    // CRM API routes
    Route::get('customers/search', [CustomerController::class, 'searchByPhone']);
    Route::get('customers/{id}/transactions', [CustomerController::class, 'getTransactions']);
    Route::post('customers/{id}/notes', [CustomerController::class, 'addNote']);
    Route::apiResource('customers', CustomerController::class);
});

// Register-specific API routes
Route::middleware('auth:register')->prefix('register')->group(function () {
    // Status management
    Route::post('/status', [RegisterController::class, 'updateStatus']);
    Route::get('/settings', [RegisterController::class, 'getSettings']);
    Route::post('/login', [RegisterController::class, 'login']);
    Route::post('/logout', [RegisterController::class, 'logout']);
    
    // Cash management
    Route::post('/cash', [RegisterController::class, 'updateCashAmount']);
    
    // Transaction management
    Route::get('/transactions', [RegisterController::class, 'getTransactions']);
    
    // POS operations
    Route::post('/sales', [RegisterPosController::class, 'createSale']);
    Route::get('/sales', [RegisterPosController::class, 'getSales']);
    
    // Heartbeat and monitoring
    Route::post('/heartbeat', [RegisterController::class, 'heartbeat']);
});