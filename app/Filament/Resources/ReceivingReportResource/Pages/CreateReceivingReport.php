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
        
        // Process damaged box images
        if ($this->data['has_damaged_boxes'] && !empty($this->data['damaged_box_images'])) {
            foreach ($this->data['damaged_box_images'] as $image) {
                try {
                    // Find the file, trying multiple potential locations
                    $possiblePaths = [
                        storage_path('app/public/' . $image),
                        storage_path('app/livewire-tmp/' . basename($image)),
                        storage_path('app/livewire-tmp/' . $image),
                        public_path('storage/' . $image),
                        $image // For absolute paths
                    ];
                    
                    $foundFile = null;
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path)) {
                            $foundFile = $path;
                            break;
                        }
                    }
                    
                    if ($foundFile) {
                        \Log::info("Adding image from: " . $foundFile);
                        
                        // Use copyMedia instead of addMedia (more reliable)
                        $receivingReport->copyMedia($foundFile)
                            ->toMediaCollection('damaged_box_images', 'public');
                    } else {
                        \Log::error("Could not find file at any potential location: " . $image);
                    }
                } catch (\Exception $e) {
                    \Log::error("Error adding damaged box image", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }
        
        // Process damage images for each item
        foreach ($receivingReport->items as $index => $item) {
            $itemData = $this->data['items'][$index] ?? null;
            if (!$itemData) continue;
            
            $damageImages = $itemData['damage_images'] ?? [];
            
            foreach ($damageImages as $image) {
                try {
                    // Same path handling as above
                    $filePath = storage_path('app/public/' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $image));
                    $tempPath = storage_path('app/livewire-tmp/' . basename($image));
                    
                    $possiblePaths = [
                        $filePath,
                        $tempPath,
                        storage_path('app/public/livewire-tmp/' . basename($image))
                    ];
                    
                    $validPath = null;
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path)) {
                            $validPath = $path;
                            break;
                        }
                    }
                    
                    if ($validPath) {
                        $item->addMedia($validPath)
                             ->preservingOriginal()
                             ->toMediaCollection('damage_images');
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to add item damage image", [
                        'error' => $e->getMessage(),
                        'item_id' => $item->id
                    ]);
                }
            }
        }
        
        // Update purchase order status after receiving
        $purchaseOrder = $receivingReport->purchaseOrder;
        if ($purchaseOrder) {
            $allItemsReceived = true;
            foreach ($purchaseOrder->items as $poItem) {
                if ($poItem->quantity_received < $poItem->quantity) {
                    $allItemsReceived = false;
                    break;
                }
            }
            
            if ($allItemsReceived) {
                $purchaseOrder->status = 'received';
            } else {
                $purchaseOrder->status = 'partially_received';
            }
            
            $purchaseOrder->save();
        }
        
        // Update inventory quantities
        foreach ($receivingReport->items as $item) {
            // Only add good items to inventory
            if ($item->quantity_good > 0) {
                $product = $item->product;
                if ($product) {
                    $product->stock_quantity += $item->quantity_good;
                    $product->save();
                }
            }
        }
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
