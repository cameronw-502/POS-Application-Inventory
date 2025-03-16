<?php

namespace App\Filament\Resources\ReceivingReportResource\Pages;

use App\Filament\Resources\ReceivingReportResource;
use Filament\Resources\Pages\EditRecord;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class EditReceivingReport extends EditRecord
{
    protected static string $resource = ReceivingReportResource::class;

    // Override mount to load related data and fix form state
    public function mount(int|string $record): void
    {
        parent::mount($record);
        
        // Force load all related models with eager loading
        $this->record = $this->record->fresh([
            'purchaseOrder.supplier',
            'items.product',
            'items.purchaseOrderItem',
            'items.media',
            'media',
        ]);
        
        // Force supplier filter value to match the PO's supplier
        if ($this->record->purchaseOrder && $this->record->purchaseOrder->supplier) {
            $this->data['supplier_filter'] = $this->record->purchaseOrder->supplier->id;
        }
        
        // Refresh the form to ensure supplier data is populated before form renders
        $this->fillForm();
    }
    
    /**
     * Fill in calculated fields that might be missing during edit
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Make sure supplier filter is set so the PO dropdown shows correct values
        if (!isset($data['supplier_filter']) && isset($data['purchase_order_id'])) {
            $purchaseOrder = \App\Models\PurchaseOrder::find($data['purchase_order_id']);
            if ($purchaseOrder) {
                $data['supplier_filter'] = $purchaseOrder->supplier_id;
            }
        }
    
        // Make sure the items have the correct quantity values
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                // Get the purchase order item to ensure we have ordered quantity
                if (isset($item['purchase_order_item_id'])) {
                    $poItem = PurchaseOrderItem::with('product')->find($item['purchase_order_item_id']);
                    
                    if ($poItem) {
                        // Set the ordered quantity explicitly
                        $data['items'][$key]['quantity_ordered'] = $poItem->quantity;
                        
                        // Make sure we have the product info
                        $data['items'][$key]['product_id'] = $poItem->product_id;
                        
                        // Set initial values if missing
                        if (!isset($item['quantity_good'])) {
                            $data['items'][$key]['quantity_good'] = $item['quantity_received'] ?? 0;
                        }
                        
                        // Make sure we have damaged quantities
                        if (!isset($item['quantity_damaged'])) {
                            $data['items'][$key]['quantity_damaged'] = 0;
                        }
                        
                        // Calculate missing quantity if not set
                        $quantityGood = (int)($data['items'][$key]['quantity_good'] ?? 0);
                        $quantityDamaged = (int)($data['items'][$key]['quantity_damaged'] ?? 0);
                        $alreadyReceived = $poItem->quantity_received - ($item['quantity_received'] ?? 0);
                        $data['items'][$key]['quantity_missing'] = 
                            max(0, $poItem->quantity - $alreadyReceived - $quantityGood - $quantityDamaged);
                    }
                }
                
                // Load existing damage images
                $itemId = $item['id'] ?? null;
                if ($itemId) {
                    $receivingItem = \App\Models\ReceivingReportItem::find($itemId);
                    if ($receivingItem) {
                        $data['items'][$key]['damage_images'] = $receivingItem->getMedia('damage_images')->map->getUrl()->toArray();
                    }
                }
            }
        }
        
        // Load box damage images
        if (isset($data['id'])) {
            $report = \App\Models\ReceivingReport::find($data['id']);
            if ($report) {
                $data['damaged_box_images'] = $report->getMedia('damaged_box_images')->map->getUrl()->toArray();
            }
        }
        
        return $data;
    }
    
    // Ensure the record saves correctly
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            // Process media separately to avoid losing media relationships
            $boxImages = $data['damaged_box_images'] ?? null;
            $hasBoxDamage = $data['has_damaged_boxes'] ?? false;
            
            // Remove media from being saved in the record itself
            if (isset($data['damaged_box_images'])) {
                unset($data['damaged_box_images']);
            }
            
            // Handle item images the same way
            $itemData = $data['items'] ?? [];
            foreach ($itemData as $key => $item) {
                if (isset($item['damage_images'])) {
                    $data['items'][$key]['damage_images_data'] = $item['damage_images'];
                    unset($data['items'][$key]['damage_images']);
                }
            }
            
            // Now update the record
            $record->update($data);
            
            // Process box damage images if needed
            if ($hasBoxDamage && $boxImages) {
                // Convert URLs back to file paths before processing
                $processedImages = $this->processImageUrls($boxImages);
                
                if (!empty($processedImages)) {
                    // Clear existing collection to avoid duplicates
                    $record->clearMediaCollection('damaged_box_images');
                    
                    // Add new images
                    foreach ($processedImages as $image) {
                        if (file_exists($image)) {
                            $record->addMedia($image)->toMediaCollection('damaged_box_images');
                        }
                    }
                }
            }
            
            // Now process any item damage images
            foreach ($record->items as $index => $item) {
                $itemData = $data['items'][$index] ?? null;
                if (!$itemData) continue;
                
                $damageImages = $itemData['damage_images_data'] ?? null;
                
                if ($damageImages && count($damageImages) > 0) {
                    // Convert URLs back to file paths
                    $processedImages = $this->processImageUrls($damageImages);
                    
                    if (!empty($processedImages)) {
                        // Clear existing collection to avoid duplicates
                        $item->clearMediaCollection('damage_images');
                        
                        foreach ($processedImages as $image) {
                            if (file_exists($image)) {
                                $item->addMedia($image)->toMediaCollection('damage_images');
                            }
                        }
                    }
                }
            }
            
            return $record;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error updating receiving report')
                ->body($e->getMessage())
                ->danger()
                ->send();
                
            throw $e;
        }
    }
    
    // Helper method to process image URLs
    private function processImageUrls(array $images): array
    {
        $result = [];
        
        foreach ($images as $image) {
            // Skip if empty
            if (empty($image)) continue;
            
            // If it's already a file path in the temp directory, use it directly
            if (is_string($image) && str_starts_with($image, 'livewire-tmp')) {
                $result[] = storage_path('app/public/' . $image);
                continue;
            }
            
            // If it's a URL to an existing media item, get the file path
            if (is_string($image) && (str_starts_with($image, 'http://') || str_starts_with($image, 'https://'))) {
                // Extract media ID from URL (this is an example and may need adjusting)
                $parts = explode('/', $image);
                $filename = end($parts);
                
                // Try to find the media by filename
                $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::where('file_name', $filename)->first();
                
                if ($media) {
                    $result[] = $media->getPath();
                }
                
                continue;
            }
            
            // If it's a new upload
            if (is_string($image) && file_exists(storage_path('app/public/' . $image))) {
                $result[] = storage_path('app/public/' . $image);
            }
        }
        
        return $result;
    }
}
