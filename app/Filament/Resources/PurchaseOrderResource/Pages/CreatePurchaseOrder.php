<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\Product;
use App\Models\ProductHistoryEvent;
use Illuminate\Support\Facades\Auth;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
    
    // Add the current user ID to the data before creating
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Add the authenticated user ID
        $data['created_by'] = Auth::id();
        
        // Calculate subtotal for each item
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                if (!empty($item['quantity']) && !empty($item['unit_price'])) {
                    $data['items'][$key]['subtotal'] = $item['quantity'] * $item['unit_price'];
                } else {
                    $data['items'][$key]['subtotal'] = 0;
                }
            }
        }
        
        // Optional: Validate that all products belong to the chosen supplier
        if (isset($data['supplier_id']) && isset($data['items'])) {
            $supplierId = $data['supplier_id'];
            $mismatchedProducts = [];
            
            foreach ($data['items'] as $key => $item) {
                if (empty($item['product_id'])) {
                    continue;
                }
                
                $productId = $item['product_id'];
                $hasSupplier = \DB::table('product_supplier')
                    ->where('product_id', $productId)
                    ->where('supplier_id', $supplierId)
                    ->exists();
                    
                if (!$hasSupplier) {
                    $product = Product::find($productId);
                    if ($product) {
                        $mismatchedProducts[] = $product->name;
                    }
                }
            }
            
            if (count($mismatchedProducts) > 0) {
                Notification::make()
                    ->warning()
                    ->title('Warning: Supplier Mismatch')
                    ->body('The following products are not linked to this supplier: ' . implode(', ', $mismatchedProducts))
                    ->send();
            }
        }
        
        return $data;
    }
    
    // After creation, calculate totals and record product history
    protected function afterCreate(): void
    {
        $purchaseOrder = $this->record;
        
        // Calculate totals
        $this->calculateTotals($purchaseOrder);
        
        // Save the updated totals
        $purchaseOrder->saveQuietly();
        
        // Record history for each product in the PO
        foreach ($purchaseOrder->items as $item) {
            if ($item->product) {
                // Record a history event with the ordered quantity
                $item->product->recordHistory(
                    ProductHistoryEvent::TYPE_PURCHASE_ORDER, 
                    0, // No immediate quantity change
                    $purchaseOrder,
                    $purchaseOrder->po_number,
                    "Ordered {$item->quantity} units on PO {$purchaseOrder->po_number}"
                );
            }
        }
    }
    
    // Helper method to calculate totals
    protected function calculateTotals($purchaseOrder): void
    {
        $subtotal = 0;
        
        foreach ($purchaseOrder->items as $item) {
            $subtotal += $item->quantity * $item->unit_price;
        }
        
        $taxRate = 0.1; // 10% tax rate
        $taxAmount = $subtotal * $taxRate;
        $totalAmount = $subtotal + $taxAmount;
        
        $purchaseOrder->tax_amount = $taxAmount;
        $purchaseOrder->total_amount = $totalAmount;
    }
}
