<?php

namespace App\Filament\Resources\ReceivingReportResource\Pages;

use App\Filament\Resources\ReceivingReportResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\InventoryTransaction;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;

class CreateReceivingReport extends CreateRecord
{
    protected static string $resource = ReceivingReportResource::class;
    
    /**
     * When the component is first loaded
     */
    public function mount(): void
    {
        parent::mount();
        
        // Get purchase order ID from URL query parameter
        $purchaseOrderId = request()->query('purchaseOrderId');
        
        if ($purchaseOrderId) {
            \Log::info('Receiving report created with PO ID: ' . $purchaseOrderId);
            
            // Get the purchase order details for display
            $purchaseOrder = PurchaseOrder::with(['supplier', 'items.product'])->find($purchaseOrderId);
            
            if ($purchaseOrder) {
                // Add summary information about the PO
                $this->form->fill([
                    'purchase_order_id' => $purchaseOrderId,
                ]);
                
                // Get all outstanding PO items with detailed information
                $poItems = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderId)
                    ->whereRaw('quantity_received < quantity')
                    ->with('product')
                    ->get();
                
                \Log::info('Found ' . $poItems->count() . ' items to receive');
                
                // Create an array of form items to match the repeater structure
                $items = [];
                foreach ($poItems as $poItem) {
                    $product = $poItem->product;
                    
                    if (!$product) {
                        \Log::warning('Product not found for PO item: ' . $poItem->id);
                        continue;
                    }
                    
                    \Log::info('Adding item to receiving form', [
                        'po_item_id' => $poItem->id,
                        'product_id' => $poItem->product_id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'ordered' => $poItem->quantity,
                        'received' => $poItem->quantity_received,
                        'remaining' => $poItem->quantity - $poItem->quantity_received
                    ]);
                    
                    $items[] = [
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $poItem->product_id,
                        'quantity_received' => $poItem->quantity - $poItem->quantity_received,
                        'notes' => '',
                    ];
                }
                
                // If we have items, set them in the form
                if (count($items) > 0) {
                    $this->data['items'] = $items;
                    
                    // Important: this refreshes the form with the data
                    $this->fillForm();
                    
                    \Log::info('Pre-filled ' . count($items) . ' items for receiving');
                } else {
                    \Log::warning('No items to receive for PO #' . $purchaseOrder->po_number);
                    
                    Notification::make()
                        ->title('No items to receive')
                        ->body('This purchase order has no remaining items to receive.')
                        ->warning()
                        ->send();
                }
            } else {
                \Log::warning('Purchase order not found: ' . $purchaseOrderId);
            }
        }
    }
    
    /**
     * Handle data before create
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate a unique receiving number
        $data['receiving_number'] = 'RR-' . date('Ymd') . '-' . Str::upper(Str::random(5));
        $data['received_by'] = auth()->id();
        $data['received_date'] = $data['received_date'] ?? now()->format('Y-m-d');
        
        return $data;
    }
    
    /**
     * Process after create
     */
    protected function afterCreate(): void
    {
        // Get the receiving report that was just created
        $receivingReport = $this->record;
        $purchaseOrder = PurchaseOrder::findOrFail($receivingReport->purchase_order_id);
        
        DB::beginTransaction();
        try {
            $totalReceived = 0;
            $allItemsReceived = true;
            
            foreach ($this->data['items'] as $itemData) {
                // Skip items with zero quantity received
                if ($itemData['quantity_received'] <= 0) {
                    continue;
                }
                
                // Create receiving report item
                $receivingReport->items()->create([
                    'purchase_order_item_id' => $itemData['purchase_order_item_id'],
                    'product_id' => $itemData['product_id'],
                    'quantity_received' => $itemData['quantity_received'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
                
                // Update purchase order item's received quantity
                $poItem = PurchaseOrderItem::findOrFail($itemData['purchase_order_item_id']);
                $poItem->increment('quantity_received', $itemData['quantity_received']);
                $totalReceived += $itemData['quantity_received'];
                
                // Check if the PO item is fully received
                if ($poItem->quantity_received < $poItem->quantity) {
                    $allItemsReceived = false;
                }
                
                // Update product stock
                $product = Product::findOrFail($itemData['product_id']);
                $product->stock_quantity += $itemData['quantity_received'];
                $product->stock = $product->stock_quantity; // Keep both fields in sync
                $product->save();
                
                // Log this inventory update
                \Log::info('Updated product stock from receiving', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_received' => $itemData['quantity_received'],
                    'new_stock' => $product->stock_quantity
                ]);
                
                // Log inventory transaction
                InventoryTransaction::create([
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity_received'],
                    'type' => 'purchase',
                    'reference_type' => get_class($receivingReport),
                    'reference_id' => $receivingReport->id,
                    'notes' => "Received from PO #{$purchaseOrder->po_number}",
                    'user_id' => auth()->id(),
                ]);
            }
            
            // Update purchase order status based on receiving status
            if ($allItemsReceived && $totalReceived > 0) {
                $purchaseOrder->status = 'received';
            } elseif ($totalReceived > 0) {
                $purchaseOrder->status = 'partially_received';
            }
            $purchaseOrder->save();
            
            DB::commit();
            
            Notification::make()
                ->title('Receiving report created successfully')
                ->body('The items have been received and inventory has been updated.')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error creating receiving report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Notification::make()
                ->title('Error processing receiving report')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
