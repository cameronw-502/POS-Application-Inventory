<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\ProductImportExportController;
use App\Http\Controllers\BarcodeController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\StockAdjustmentController;
use App\Http\Controllers\API\PosController as ApiPosController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReceivingReportController;

// Remove or comment out any Auth::routes() calls

Route::get('/', function () {
    return redirect('/admin');
});

// Keep only the routes you need

// Remove the admin route
// Route::view('admin', 'admin')
//    ->middleware(['auth', 'verified'])
//    ->name('admin');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    
    // Update this route to properly serve report PDFs securely
    Route::get('/reports/preview/{filename}', function ($filename) {
        // Check if file exists in the public disk without the 'public/' prefix
        if (!Storage::disk('public')->exists('reports/' . $filename)) {
            abort(404);
        }
        
        // Get the file content
        $content = Storage::disk('public')->get('reports/' . $filename);
        
        // Return the file as a response
        return Response::make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    })->middleware('auth')->name('reports.preview');
});

// POS Routes
Route::middleware('auth')->group(function () {
    Route::get('/pos', [App\Http\Controllers\PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/search', [App\Http\Controllers\PosController::class, 'search'])->name('pos.search');
    Route::get('/pos/product/{identifier}', [App\Http\Controllers\PosController::class, 'getProduct'])->name('pos.product');
    Route::post('/pos/checkout', [App\Http\Controllers\PosController::class, 'checkout'])->name('pos.checkout');
    Route::get('/pos/receipt/{saleId}', [App\Http\Controllers\PosController::class, 'receipt'])->name('pos.receipt');
    Route::post('/orders', [App\Http\Controllers\OrderController::class, 'store'])->name('orders.store');
    Route::get('/pos/advanced-search', [App\Http\Controllers\PosController::class, 'advancedSearch'])->name('pos.advanced-search');
    Route::post('/pos/set-theme', [App\Http\Controllers\PosController::class, 'setTheme'])
        ->middleware('web')
        ->name('pos.set-theme');
        
    // Add routes for sales history, products, categories, and settings
    Route::get('/sales', [App\Http\Controllers\SaleController::class, 'index'])->name('sales.index');
    Route::get('/products', [App\Http\Controllers\ProductController::class, 'index'])->name('products.index');
    Route::get('/categories', [App\Http\Controllers\CategoryController::class, 'index'])->name('categories.index');
    Route::get('/pos/settings', [App\Http\Controllers\PosSettingController::class, 'index'])->name('pos.settings');
    Route::put('/pos/settings', [App\Http\Controllers\PosSettingController::class, 'update'])->name('pos.settings.update');
    
    // Add this route to the middleware('auth') group
    Route::get('/pos/css-test', [App\Http\Controllers\PosController::class, 'testCss'])
        ->name('pos.css-test');
    
    // Customer Facing Display Route
    Route::get('/pos/customer-display', [App\Http\Controllers\PosSettingController::class, 'customerDisplay'])
        ->name('pos.customer-display');
    
    // Voice Commands API Route
    Route::post('/pos/voice-command', [App\Http\Controllers\PosController::class, 'processVoiceCommand'])
        ->name('pos.voice-command');
    
    // AI Product Suggestions API Route
    Route::get('/pos/ai-suggestions', [App\Http\Controllers\PosController::class, 'getAiSuggestions'])
        ->name('pos.ai-suggestions');
        
    // Inventory Forecasting Route
    Route::get('/pos/inventory-forecast', [App\Http\Controllers\PosController::class, 'getInventoryForecast'])
        ->name('pos.inventory-forecast');
        
    // Test routes for premium features
    Route::middleware(['auth'])->group(function () {
        Route::get('/pos/test/ai-suggestions', [App\Http\Controllers\TestPremiumFeaturesController::class, 'testAiSuggestions']);
        Route::view('/pos/test/customer-display', 'pos.test-customer-display');
        Route::view('/pos/test/inventory-forecast', 'pos.test-inventory-forecast');
        Route::view('/pos/test/voice-commands', 'pos.test-voice-commands');
    });
});

Route::get('/products/export', [ProductImportExportController::class, 'export'])->name('product.export');
Route::post('/products/import', [ProductImportExportController::class, 'import'])->name('product.import');
Route::get('/products/{product}/barcode', [BarcodeController::class, 'show'])->name('product.barcode');
Route::get('/products/barcodes/print', [BarcodeController::class, 'printMultiple'])->name('product.barcode.multiple');

Route::get('/debug/product/{identifier}', function ($identifier) {
    $product = App\Models\Product::where('id', $identifier)
        ->orWhere('sku', $identifier)
        ->first();
        
    if (!$product) {
        return response()->json([
            'message' => 'Product not found',
            'search_term' => $identifier
        ], 404);
    }
    
    return response()->json([
        'message' => 'Product found',
        'product' => $product,
        'search_term' => $identifier
    ]);
})->middleware('auth');

// Add this route to your web.php to help diagnose stock issues
Route::get('/debug/stock-sync/{product}', function (App\Models\Product $product) {
    $saleItems = App\Models\SaleItem::where('product_id', $product->id)->get();
    $totalSold = $saleItems->sum('quantity');
    
    $adjustmentItems = App\Models\StockAdjustmentItem::whereHas('stockAdjustment', function ($query) {
        $query->where('type', 'sale');
    })->where('product_id', $product->id)->get();
    $totalAdjusted = $adjustmentItems->sum('quantity');
    
    return response()->json([
        'product' => [
            'id' => $product->id,
            'name' => $product->name,
            'current_stock' => $product->stock
        ],
        'sales' => [
            'total_sold_quantity' => $totalSold,
            'sale_items_count' => $saleItems->count(),
            'recent_sales' => $saleItems->take(5)->map(function ($item) {
                return [
                    'sale_id' => $item->sale_id,
                    'quantity' => $item->quantity,
                    'date' => $item->created_at
                ];
            })
        ],
        'adjustments' => [
            'total_adjusted_quantity' => $totalAdjusted,
            'adjustment_items_count' => $adjustmentItems->count(),
            'recent_adjustments' => $adjustmentItems->take(5)->map(function ($item) {
                return [
                    'adjustment_id' => $item->stock_adjustment_id,
                    'adjustment_type' => $item->stockAdjustment->type,
                    'quantity' => $item->quantity,
                    'date' => $item->created_at
                ];
            })
        ]
    ]);
})->middleware('auth');

// Add this to your routes/web.php file
Route::get('/debug/clear-pos-cache', function () {
    // Clear current hour's cache
    $key = 'pos_settings_' . date('YmdH');
    Cache::forget($key);
    
    // Force refresh
    $settings = \App\Helpers\PosHelper::refreshSettings();
    
    return response()->json([
        'message' => 'POS settings cache cleared',
        'settings' => $settings
    ]);
})->middleware('auth');

Route::get('/debug/report-type', function () {
    $reportInstance = app()->make(\App\Filament\Pages\Reports::class);
    $formData = $reportInstance->form->getState();
    
    return [
        'selectedReport' => $reportInstance->selectedReport,
        'form_data' => $formData,
        'form_exists' => $reportInstance->form !== null,
        'has_selectedReport_in_form' => isset($formData['selectedReport']),
    ];
})->middleware('auth');

// Receipt printing route
Route::get('/receipts/{transaction}', [ReceiptController::class, 'show'])
    ->name('receipts.show')
    ->middleware(['auth']);

// Route to download receipt PDF
Route::get('/receipts/{transaction}/pdf', [ReceiptController::class, 'downloadPdf'])
    ->name('receipts.pdf')
    ->middleware(['auth']);

// Route for thermal printer-friendly receipt
Route::get('/receipts/{transaction}/thermal', [ReceiptController::class, 'thermalPrint'])
    ->name('receipts.thermal')
    ->middleware(['auth']);

// Make sure auth routes correctly redirect to admin instead of dashboard
require __DIR__.'/auth.php';

// Make sure this route exists
Route::get('/purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'generatePdf'])
    ->name('purchase-orders.pdf');

// Add this to your web routes
Route::get('/receiving-reports/{receivingReport}/pdf', [ReceivingReportController::class, 'generatePdf'])
    ->name('receiving-reports.pdf')
    ->middleware(['auth']);

// Add this to your routes/web.php file
Route::get('/debug-receiving/{id}', function ($id) {
    $report = \App\Models\ReceivingReport::with('items.media', 'media')->find($id);
    if (!$report) return 'Report not found';
    
    $output = "Receiving Report: {$report->receiving_number}<br>";
    $output .= "Items count: " . $report->items->count() . "<br>";
    $output .= "Media count: " . $report->media->count() . "<br>";
    
    $output .= "<h3>Box Images:</h3>";
    foreach ($report->getMedia('damaged_box_images') as $media) {
        $output .= "- {$media->file_name} - Path: {$media->getPath()}<br>";
        $output .= "<img src='{$media->getUrl()}' height='100'><br>";
    }
    
    $output .= "<h3>Items and their images:</h3>";
    foreach ($report->items as $item) {
        $output .= "Item {$item->id} - {$item->product->name} - Damaged qty: {$item->quantity_damaged}<br>";
        foreach ($item->getMedia('damage_images') as $media) {
            $output .= "- {$media->file_name} - Path: {$media->getPath()}<br>";
            $output .= "<img src='{$media->getUrl()}' height='100'><br>";
        }
    }
    
    return $output;
});

// Test route to see raw item data - add at the end of your routes file
Route::get('/test-receiving/{id}', function ($id) {
    $report = \App\Models\ReceivingReport::with(['items.product', 'purchaseOrder'])->find($id);
    if (!$report) return "Report not found";
    
    $output = "<h2>Raw data for Receiving Report #{$report->receiving_number}</h2>";
    $output .= "<p>Items count: " . $report->items->count() . "</p>";
    
    $output .= "<table border='1' cellpadding='5'>
    <tr>
        <th>ID</th>
        <th>Product ID</th>
        <th>Product Name</th>
        <th>Qty Received</th>
        <th>Qty Damaged</th>
        <th>Qty Missing</th>
    </tr>";
    
    foreach ($report->items as $item) {
        $output .= "<tr>
            <td>{$item->id}</td>
            <td>{$item->product_id}</td>
            <td>" . ($item->product ? $item->product->name : 'MISSING PRODUCT') . "</td>
            <td>{$item->quantity_received}</td>
            <td>{$item->quantity_damaged}</td>
            <td>{$item->quantity_missing}</td>
        </tr>";
    }
    $output .= "</table>";
    
    return $output;
})->middleware(['auth']);

// Direct fix for specific receiving report
Route::get('/fix-receiving/{id}', function ($id) {
    $report = \App\Models\ReceivingReport::with(['items.purchaseOrderItem', 'items.product'])->find($id);
    if (!$report) return "Report not found";
    
    $updated = 0;
    
    // Ensure each item has the right relationships
    foreach ($report->items as $item) {
        $poItem = $item->purchaseOrderItem;
        if (!$poItem) continue;
        
        // Ensure product_id is correctly set
        if (!$item->product_id && $poItem->product_id) {
            $item->product_id = $poItem->product_id;
            $item->save();
            $updated++;
        }
        
        // Make sure quantities are correct
        if ($item->quantity_received < ($item->quantity_good + $item->quantity_damaged)) {
            $item->quantity_received = $item->quantity_good + $item->quantity_damaged;
            $item->save();
            $updated++;
        }
    }
    
    return "Fixed $updated items for receiving report #{$report->receiving_number}";
})->middleware(['auth']);
