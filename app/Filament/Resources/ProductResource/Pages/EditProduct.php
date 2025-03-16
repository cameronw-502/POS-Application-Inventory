<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Color;
use App\Models\Size;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    // Add these properties to make the form full width
    protected ?string $maxContentWidth = 'full';
    
    // Use 12 column span (full width in Filament's grid)
    protected ?int $columnSpan = 12;

    protected function getHeaderActions(): array
    {
        return [
            // Other actions...
            Actions\Action::make('cloneProduct')
                ->label('Clone as New Product')
                ->icon('heroicon-o-document-duplicate')  // Changed from 'heroicon-o-duplicate'
                ->color('gray')
                ->action(function () {
                    $originalProduct = $this->record;
                    
                    // Create a duplicate but change the name and SKU
                    $newProduct = $originalProduct->replicate();
                    $newProduct->name = $originalProduct->name . ' (Copy)';
                    $newProduct->sku = null; // Will be auto-generated
                    $newProduct->slug = Str::slug($newProduct->name);
                    $newProduct->parent_product_id = $originalProduct->id; // Set relationship
                    $newProduct->save();
                    
                    // Optionally copy relations like suppliers
                    if ($originalProduct->suppliers()->exists()) {
                        foreach ($originalProduct->suppliers as $supplier) {
                            $newProduct->suppliers()->attach($supplier->id, [
                                'cost_price' => $supplier->pivot->cost_price,
                                'supplier_sku' => $supplier->pivot->supplier_sku . '-COPY',
                                'is_preferred' => $supplier->pivot->is_preferred,
                            ]);
                        }
                    }
                    
                    Notification::make()
                        ->title('Product Cloned')
                        ->success()
                        ->body('The product was cloned successfully. You can now edit the copy.')
                        ->send();
                        
                    // Redirect to edit the new product
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $newProduct]));
                })
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $supplier = $this->record->suppliers()->first();
    
        if ($supplier) {
            $data['single_supplier_id'] = $supplier->id;
            $data['supplier_price'] = $supplier->pivot->cost_price;
            $data['supplier_sku'] = $supplier->pivot->supplier_sku;
            
            // Calculate margin if price and cost are available
            if (!empty($data['price']) && !empty($supplier->pivot->cost_price)) {
                $price = floatval($data['price']);
                $cost = floatval($supplier->pivot->cost_price);
                
                if ($cost > 0 && $price > 0 && $cost < $price) {
                    $data['margin_percentage'] = round((1 - ($cost / $price)) * 100, 2);
                } else {
                    $data['margin_percentage'] = 0;
                }
            }
        }
        
        // Load existing product-supplier relationships into the form
        $supplierData = $this->record->suppliers()
            ->withPivot(['cost_price', 'supplier_sku', 'is_preferred', 'sort'])
            ->get()
            ->map(function ($supplier) {
                return [
                    'supplier_id' => $supplier->id,
                    'cost_price' => $supplier->pivot->cost_price,
                    'supplier_sku' => $supplier->pivot->supplier_sku,
                    'is_preferred' => (bool) $supplier->pivot->is_preferred,
                    'sort' => $supplier->pivot->sort ?? 0,
                ];
            })
            ->toArray();
        
        $data['supplier_data'] = $supplierData;
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove supplier_data from the main form data
        $this->supplierData = $data['supplier_data'] ?? [];
        unset($data['supplier_data']);
        
        return $data;
    }

    protected function afterSave(): void
    {
        $product = $this->record;
        $supplierId = $this->data['single_supplier_id'] ?? null;
        
        // First detach all suppliers (since we're only allowing one)
        $product->suppliers()->detach();
        
        if ($supplierId) {
            try {
                // Attach the new supplier with explicit values for all fields
                $product->suppliers()->attach($supplierId, [
                    'cost_price' => $this->data['supplier_price'] ?? 0,
                    'supplier_sku' => $this->data['supplier_sku'] ?? '',
                    'is_preferred' => true,
                    'sort' => 0,
                ]);
                
                // Update supplier unit information on the product
                $product->update([
                    'supplier_unit_type' => $this->data['supplier_unit_type'] ?? 'single',
                    'supplier_unit_quantity' => $this->data['supplier_unit_quantity'] ?? 1,
                ]);
                
                \Log::info('Supplier updated successfully', [
                    'product_id' => $product->id,
                    'supplier_id' => $supplierId
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to update supplier: ' . $e->getMessage(), [
                    'product_id' => $product->id,
                    'supplier_id' => $supplierId,
                    'trace' => $e->getTraceAsString()
                ]);
                
                Notification::make()
                    ->title('Supplier Not Updated')
                    ->body('There was a problem updating the supplier information: ' . $e->getMessage())
                    ->warning()
                    ->send();
            }
        }
    }
}
