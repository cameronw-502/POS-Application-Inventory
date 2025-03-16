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
// Make sure to use the fully qualified namespace for Placeholder
use Filament\Forms\Components\Placeholder as FilamentPlaceholder;
// or simply replace the original import with:
// use Filament\Forms\Components\Placeholder;

class CreateReceivingReport extends CreateRecord
{
    protected static string $resource = ReceivingReportResource::class;
    
    /**
     * When the component is first loaded
     */
    public function mount(): void
    {
        parent::mount();
        
        // Handle purchase order selection from query parameters
        $purchaseOrderId = request()->query('purchase_order_id');
        if ($purchaseOrderId) {
            $purchaseOrder = PurchaseOrder::with([
                'supplier', 
                'items.product' // Ensure products are eager loaded
            ])->find($purchaseOrderId);
            
            if ($purchaseOrder) {
                // Set the supplier and purchase order in the form
                $this->form->fill([
                    'supplier_filter' => $purchaseOrder->supplier_id,
                    'purchase_order_id' => $purchaseOrder->id,
                ]);
                
                // Get all outstanding PO items with products
                $poItems = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)
                    ->whereRaw('quantity_received < quantity')
                    ->with('product')
                    ->get();
                
                \Log::info('Found ' . $poItems->count() . ' items to receive');
                
                $items = [];
                foreach ($poItems as $poItem) {
                    // Log debug information 
                    \Log::info('Processing PO item', [
                        'poItem_id' => $poItem->id,
                        'product_id' => $poItem->product_id,
                        'has_product' => isset($poItem->product),
                        'product_name' => $poItem->product ? $poItem->product->name : 'NULL'
                    ]);
                    
                    if (!$poItem->product) {
                        \Log::error("Product {$poItem->product_id} not found for PO item {$poItem->id}");
                        continue;
                    }
                    
                    $items[] = [
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $poItem->product_id,
                        'quantity_ordered' => $poItem->quantity,
                        'quantity_received' => max(0, $poItem->quantity - $poItem->quantity_received),
                        'quantity_damaged' => 0,
                        'quantity_missing' => 0,
                        'notes' => '',
                    ];
                }
                
                if (count($items) > 0) {
                    $this->data['items'] = $items;
                    $this->fillForm();
                }
            }
        }
    }
    
    /**
     * Handle data before create
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Process the items to combine received and damaged quantities
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                $goodQty = (int)($item['quantity_received'] ?? 0);
                $damagedQty = (int)($item['quantity_damaged'] ?? 0);
                
                // Update the total received quantity to include both good and damaged
                $data['items'][$key]['quantity_received'] = $goodQty + $damagedQty;
            }
        }

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
        $receivingReport = $this->record;
        
        // First, handle any box damage images
        if ($this->data['has_damaged_boxes'] && !empty($this->data['damaged_box_images'])) {
            // Log the images being processed
            \Log::info('Processing damaged box images', [
                'report_id' => $receivingReport->id,
                'images' => $this->data['damaged_box_images']
            ]);
            
            foreach ($this->data['damaged_box_images'] as $image) {
                try {
                    if (file_exists(storage_path('app/public/' . $image))) {
                        $receivingReport->addMedia(storage_path('app/public/' . $image))
                            ->toMediaCollection('damaged_box_images');
                        
                        \Log::info('Added box damage image', ['path' => $image]);
                    } else {
                        \Log::error('Box damage image not found', ['path' => $image]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error adding box damage image', [
                        'error' => $e->getMessage(), 
                        'path' => $image
                    ]);
                }
            }
        }
        
        // Then handle item damage images for each item
        foreach ($receivingReport->items as $index => $item) {
            $itemData = $this->data['items'][$index] ?? null;
            if (!$itemData) continue;
            
            $damageImages = $itemData['damage_images'] ?? [];
            
            if (!empty($damageImages)) {
                \Log::info('Processing item damage images', [
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'image_count' => count($damageImages)
                ]);
                
                foreach ($damageImages as $image) {
                    try {
                        if (file_exists(storage_path('app/public/' . $image))) {
                            $item->addMedia(storage_path('app/public/' . $image))
                                ->toMediaCollection('damage_images');
                            
                            \Log::info('Added item damage image', ['path' => $image]);
                        } else {
                            \Log::error('Item damage image not found', ['path' => $image]);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error adding item damage image', [
                            'error' => $e->getMessage(), 
                            'path' => $image
                        ]);
                    }
                }
            }
        }

        $purchaseOrder = PurchaseOrder::findOrFail($receivingReport->purchase_order_id);
        
        DB::beginTransaction();
        try {
            $allItemsReceived = true;
            
            foreach ($this->data['items'] as $itemData) {
                // Skip items with zero total received
                $quantityGood = (int)($itemData['quantity_received'] ?? 0);
                $quantityDamaged = (int)($itemData['quantity_damaged'] ?? 0);
                $totalReceived = $quantityGood + $quantityDamaged;
                $quantityMissing = (int)($itemData['quantity_missing'] ?? 0);
                
                if ($totalReceived <= 0) {
                    continue;
                }
                
                // Get the purchase order item for reference
                $poItem = PurchaseOrderItem::findOrFail($itemData['purchase_order_item_id']);
                $orderedQty = $poItem->quantity;
                
                // Double-check missing qty calculation
                $quantityMissing = max(0, $orderedQty - $quantityGood - $quantityDamaged);
                
                // Create receiving report item with corrected values
                $item = $receivingReport->items()->create([
                    'purchase_order_item_id' => $itemData['purchase_order_item_id'],
                    'product_id' => $itemData['product_id'],
                    'quantity_received' => $totalReceived,
                    'quantity_good' => $quantityGood,
                    'quantity_damaged' => $quantityDamaged,
                    'quantity_missing' => $quantityMissing,
                    'notes' => $itemData['notes'] ?? null,
                ]);
                
                // Update purchase order item's received quantity
                $poItem->increment('quantity_received', $totalReceived);
                
                // Check if the PO item is fully received
                if ($poItem->quantity_received < $poItem->quantity) {
                    $allItemsReceived = false;
                }
                
                // Update product stock - only add good condition items
                $product = Product::findOrFail($itemData['product_id']);
                $product->stock_quantity += $quantityGood;
                $product->stock = $product->stock_quantity; // Keep both fields in sync
                $product->save();
                
                // Log inventory transaction for good items
                if ($quantityGood > 0) {
                    InventoryTransaction::create([
                        'product_id' => $itemData['product_id'],
                        'quantity' => $quantityGood,
                        'type' => 'purchase',
                        'reference_type' => get_class($receivingReport),
                        'reference_id' => $receivingReport->id,
                        'notes' => "Received from PO #{$purchaseOrder->po_number} (Good condition)",
                        'user_id' => auth()->id(),
                    ]);
                }
                
                // Process damage images if any
                if ($quantityDamaged > 0 && isset($itemData['damage_images']) && count($itemData['damage_images']) > 0) {
                    foreach ($itemData['damage_images'] as $image) {
                        $item->addMedia(storage_path('app/public/' . $image))
                            ->toMediaCollection('damage_images');
                    }
                }
            }
            
            // Handle damaged box images
            if ($this->data['has_damaged_boxes'] && isset($this->data['damaged_box_images'])) {
                foreach ($this->data['damaged_box_images'] as $image) {
                    $this->record->addMedia(storage_path('app/public/' . $image))
                        ->toMediaCollection('damaged_box_images');
                }
            }
            
            // Handle item damage images
            foreach ($this->data['items'] as $index => $itemData) {
                if (isset($itemData['is_damaged']) && $itemData['is_damaged'] && isset($itemData['damage_images'])) {
                    $item = $this->record->items[$index] ?? null;
                    if ($item) {
                        foreach ($itemData['damage_images'] as $image) {
                            $item->addMedia(storage_path('app/public/' . $image))
                                ->toMediaCollection('damage_images');
                        }
                    }
                }
            }

            // Process damaged box images
            if ($this->data['has_damaged_boxes'] && !empty($this->data['damaged_box_images'])) {
                // Add images to the media collection
                foreach ($this->data['damaged_box_images'] as $image) {
                    $this->record->addMedia(storage_path('app/public/' . $image))
                        ->toMediaCollection('damaged_box_images');
                }
            }

            // Process item damage images
            foreach ($this->record->items as $item) {
                if (!empty($itemData['damage_images'])) {
                    foreach ($itemData['damage_images'] as $image) {
                        $item->addMedia(storage_path('app/public/' . $image))
                            ->toMediaCollection('damage_images');
                    }
                }
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
