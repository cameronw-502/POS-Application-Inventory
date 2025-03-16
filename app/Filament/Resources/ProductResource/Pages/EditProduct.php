<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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
                                
                                // Create the variant
                                $variant = ProductVariant::create([
                                    'product_id' => $product->id,
                                    'name' => "{$product->name} - {$color->name}, {$size->name}",
                                    'sku' => $sku,
                                    'color_id' => $color->id,
                                    'size_id' => $size->id,
                                    'price' => $product->price,
                                    'stock_quantity' => 0,
                                    'description' => $product->description,
                                    // Inherit parent attributes
                                    'weight' => $product->weight,
                                    'width' => $product->width,
                                    'height' => $product->height,
                                    'length' => $product->length,
                                ]);
                                
                                // Copy supplier relationships if they exist
                                $productSuppliers = $product->suppliers()->get();
                                foreach ($productSuppliers as $supplier) {
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

    // Add this method to properly save the suppliers
    protected function afterSave(): void
    {
        $product = $this->record;
        
        // Handle suppliers relationship
        if (isset($this->data['suppliers']) && is_array($this->data['suppliers'])) {
            $suppliersToSync = [];
            
            foreach ($this->data['suppliers'] as $data) {
                if (!empty($data['supplier_id'])) {
                    // Ensure the supplier_id exists in the suppliers table
                    $supplier = \App\Models\Supplier::find($data['supplier_id']);
                    
                    if ($supplier) {
                        $suppliersToSync[$data['supplier_id']] = [
                            'cost_price' => $data['cost_price'] ?? null,
                            'supplier_sku' => $data['supplier_sku'] ?? null,
                            'is_preferred' => $data['is_preferred'] ?? false,
                        ];
                    }
                }
            }
            
            if (!empty($suppliersToSync)) {
                $product->suppliers()->sync($suppliersToSync);
            }
        }
    }
}
