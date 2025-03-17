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
use App\Models\ProductHistoryEvent;

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
        
        // Set the current user as the one who received the items
        $data['received_by_user_id'] = auth()->id();
        
        return $data;
    }
    
    /**
     * Process after create
     */
    protected function afterCreate(): void
    {
        $receivingReport = $this->record;
        
        // Debug information
        \Log::info("Processing ReceivingReport after create", [
            'id' => $receivingReport->id,
            'number' => $receivingReport->receiving_number,
            'has_damaged_boxes' => $this->data['has_damaged_boxes'] ?? false,
            'has_images' => !empty($this->data['damaged_box_images'] ?? []),
            'image_count' => count($this->data['damaged_box_images'] ?? [])
        ]);
        
        // Process damaged box images with better error handling
        if ($this->data['has_damaged_boxes'] && !empty($this->data['damaged_box_images'])) {
            foreach ($this->data['damaged_box_images'] as $image) {
                try {
                    // More reliable approach using addMediaFromDisk
                    \Log::info("Processing image: " . $image);
                    
                    // Get real path from storage
                    $path = storage_path('app/public/' . $image);
                    $tmpPath = storage_path('app/livewire-tmp/' . basename($image));
                    
                    if (file_exists($path)) {
                        \Log::info("File exists at: " . $path);
                        $receivingReport->addMedia($path)
                            ->preservingOriginal()
                            ->toMediaCollection('damaged_box_images', 'public');
                    } elseif (file_exists($tmpPath)) {
                        \Log::info("File exists at temp path: " . $tmpPath);
                        $receivingReport->addMedia($tmpPath)
                            ->preservingOriginal()
                            ->toMediaCollection('damaged_box_images', 'public');
                    } else {
                        \Log::warning("File not found at expected paths", [
                            'original' => $image,
                            'path' => $path,
                            'tmpPath' => $tmpPath
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error("Error adding damaged box image", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'image' => $image
                    ]);
                }
            }
            
            // Verify media was added
            $mediaCount = $receivingReport->getMedia('damaged_box_images')->count();
            \Log::info("Media added to damaged_box_images: " . $mediaCount);
        }
        
        // Process damage images for each item with better error handling
        foreach ($receivingReport->items as $index => $item) {
            $itemData = $this->data['items'][$index] ?? null;
            if (!$itemData) continue;
            
            $damageImages = $itemData['damage_images'] ?? [];
            \Log::info("Processing item damage images", [
                'item_id' => $item->id,
                'image_count' => count($damageImages)
            ]);
            
            foreach ($damageImages as $image) {
                try {
                    $path = storage_path('app/public/' . $image);
                    $tmpPath = storage_path('app/livewire-tmp/' . basename($image));
                    
                    if (file_exists($path)) {
                        $item->addMedia($path)
                            ->preservingOriginal()
                            ->toMediaCollection('damage_images', 'public');
                        \Log::info("Added damage image from: " . $path);
                    } elseif (file_exists($tmpPath)) {
                        $item->addMedia($tmpPath)
                            ->preservingOriginal()
                            ->toMediaCollection('damage_images', 'public');
                        \Log::info("Added damage image from tmp: " . $tmpPath);
                    } else {
                        \Log::warning("Item damage image not found", [
                            'original' => $image,
                            'path' => $path,
                            'tmpPath' => $tmpPath
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to add item damage image", [
                        'error' => $e->getMessage(),
                        'item_id' => $item->id,
                        'image' => $image
                    ]);
                }
            }
            
            // Verify media was added
            $mediaCount = $item->getMedia('damage_images')->count();
            \Log::info("Media added to item damage_images: " . $mediaCount);
        }
        
        // Update purchase order items with received quantities 
        // Wrap in transaction to ensure all updates are atomic
        \DB::transaction(function() use ($receivingReport) {
            foreach ($receivingReport->items as $item) {
                if ($item->purchase_order_item_id && ($item->quantity_good > 0 || $item->quantity_damaged > 0)) {
                    $poItem = PurchaseOrderItem::find($item->purchase_order_item_id);
                    if ($poItem) {
                        // Get current value before update for logging
                        $currentReceived = $poItem->quantity_received ?? 0;
                        $newReceived = $currentReceived + $item->quantity_good + $item->quantity_damaged;
                        
                        // Update the quantity_received field in the purchase_order_item
                        $poItem->quantity_received = $newReceived;
                        $result = $poItem->save();
                        
                        \Log::info('Updated PO Item received quantity', [
                            'po_item_id' => $poItem->id,
                            'previous_qty_received' => $currentReceived,
                            'additional_qty' => ($item->quantity_good + $item->quantity_damaged),
                            'new_qty_received' => $newReceived,
                            'total_ordered' => $poItem->quantity,
                            'save_result' => $result,
                            'poItem_after_save' => $poItem->fresh()->quantity_received
                        ]);
                    }
                }
            }
            
            // Update purchase order status after receiving
            $purchaseOrder = $receivingReport->purchaseOrder;
            if ($purchaseOrder) {
                $allItemsReceived = true;
                $anyItemsReceived = false;
                $totalItems = 0;
                $fullyReceivedItems = 0;
                
                // Reload purchase order with fresh related data
                $purchaseOrder->load('items');
                
                foreach ($purchaseOrder->items as $poItem) {
                    $totalItems++;
                    if ($poItem->quantity_received > 0) {
                        $anyItemsReceived = true;
                    }
                    
                    if ($poItem->quantity_received >= $poItem->quantity) {
                        $fullyReceivedItems++;
                    } else {
                        $allItemsReceived = false;
                    }
                }
                
                \Log::info('Checking PO status', [
                    'po_id' => $purchaseOrder->id,
                    'po_number' => $purchaseOrder->po_number,
                    'total_items' => $totalItems,
                    'fully_received_items' => $fullyReceivedItems,
                    'all_items_received' => $allItemsReceived,
                    'any_items_received' => $anyItemsReceived
                ]);
                
                if ($allItemsReceived && $totalItems > 0) {
                    $purchaseOrder->status = 'received';
                    \Log::info("Setting PO status to 'received'");
                } elseif ($anyItemsReceived) {
                    $purchaseOrder->status = 'partially_received';
                    \Log::info("Setting PO status to 'partially_received'");
                } else {
                    $purchaseOrder->status = 'ordered';
                    \Log::info("Setting PO status to 'ordered'");
                }
                
                $purchaseOrder->save();
            }
        });
        
        // Update inventory quantities and record history
        foreach ($receivingReport->items as $item) {
            // Only add good items to inventory
            if ($item->quantity_good > 0) {
                $product = $item->product;
                if ($product) {
                    $oldQuantity = $product->stock_quantity;
                    $product->stock_quantity += $item->quantity_good;
                    $product->save();
                    
                    // Record history event
                    $product->recordHistory(
                        ProductHistoryEvent::TYPE_RECEIVING,
                        $item->quantity_good,
                        $receivingReport,
                        $receivingReport->receiving_number,
                        "Received {$item->quantity_good} good units from PO {$receivingReport->purchaseOrder->po_number}"
                    );
                }
            }
            
            // Record damaged items in history without adding to inventory
            if ($item->quantity_damaged > 0) {
                $product = $item->product;
                if ($product) {
                    $product->recordHistory(
                        ProductHistoryEvent::TYPE_RECEIVING,
                        0, // No quantity change for damaged items
                        $receivingReport,
                        $receivingReport->receiving_number,
                        "Received {$item->quantity_damaged} damaged units from PO {$receivingReport->purchaseOrder->po_number}"
                    );
                }
            }
        }
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
