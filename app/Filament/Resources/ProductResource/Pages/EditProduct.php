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
            Actions\Action::make('generateVariations')
                ->label('Generate Variations')
                ->color('success')
                ->icon('heroicon-o-cube')
                ->requiresConfirmation()
                ->action(function () {
                    $product = $this->record;
                    
                    // Get all colors and sizes
                    $colors = Color::all();
                    $sizes = Size::orderBy('display_order')->get();
                    
                    if ($colors->isEmpty() || $sizes->isEmpty()) {
                        Notification::make()
                            ->title('Cannot generate variations')
                            ->body('You need at least one color and one size')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    // Create a variation for each color/size combination
                    $count = 0;
                    $baseSkuPrefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $product->name), 0, 3));
                    $baseSkuNumber = str_pad($product->id, 6, '0', STR_PAD_LEFT);
                    
                    foreach ($colors as $color) {
                        foreach ($sizes as $size) {
                            // Check if this combination already exists
                            $exists = ProductVariant::where('product_id', $product->id)
                                ->where('color_id', $color->id)
                                ->where('size_id', $size->id)
                                ->exists();
                                
                            if (!$exists) {
                                // Generate a unique SKU for this variation
                                $colorCode = strtoupper(substr($color->name, 0, 2));
                                $sizeCode = strtoupper(substr($size->name, 0, 2));
                                $sku = "{$baseSkuPrefix}-{$baseSkuNumber}-{$colorCode}{$sizeCode}";
                                
                                // Create the variant with inherited attributes from parent
                                $variant = ProductVariant::create([
                                    'product_id' => $product->id,
                                    'name' => "{$product->name} - {$color->name}, {$size->name}",
                                    'sku' => $sku,
                                    'color_id' => $color->id,
                                    'size_id' => $size->id,
                                    'price' => $product->price,
                                    'stock_quantity' => 0,
                                    'description' => $product->description,
                                    // Inherit all parent attributes
                                    'weight' => $product->weight,
                                    'width' => $product->width,
                                    'height' => $product->height,
                                    'length' => $product->length,
                                    // Add any other attributes you want to inherit
                                ]);
                                
                                // Copy supplier relationships if they exist
                                $productSuppliers = $product->suppliers()->get();
                                foreach ($productSuppliers as $supplier) {
                                    // For each variant, maintain the same supplier but with a suffix for supplier_sku
                                    $pivotData = [
                                        'cost_price' => $supplier->pivot->cost_price,
                                        'supplier_sku' => $supplier->pivot->supplier_sku . "-{$colorCode}{$sizeCode}",
                                        'is_preferred' => $supplier->pivot->is_preferred,
                                    ];
                                    $variant->suppliers()->attach($supplier->id, $pivotData);
                                }
                                
                                $count++;
                            }
                        }
                    }
                    
                    // Enable variations flag
                    if ($count > 0) {
                        $product->has_variations = true;
                        $product->save();
                    }
                    
                    Notification::make()
                        ->title('Variations generated')
                        ->body("Created {$count} new variations")
                        ->success()
                        ->send();
                        
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $product]));
                })
                ->visible(fn () => $this->record instanceof \App\Models\Product),
            
            // Other actions...
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
