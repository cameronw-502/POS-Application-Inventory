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
                    foreach ($colors as $color) {
                        foreach ($sizes as $size) {
                            // Check if this combination already exists
                            $exists = ProductVariant::where('product_id', $product->id)
                                ->where('color_id', $color->id)
                                ->where('size_id', $size->id)
                                ->exists();
                                
                            if (!$exists) {
                                ProductVariant::create([
                                    'product_id' => $product->id,
                                    'name' => "{$color->name}, {$size->name}",
                                    'color_id' => $color->id,
                                    'size_id' => $size->id,
                                    'stock_quantity' => 0,
                                    // Inherit parent attributes
                                    'weight' => $product->weight,
                                    'width' => $product->width,
                                    'height' => $product->height,
                                    'length' => $product->length,
                                ]);
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
            $suppliersData = [];
            
            foreach ($this->data['suppliers'] as $supplierData) {
                if (!empty($supplierData['supplier_id'])) {
                    $suppliersData[$supplierData['supplier_id']] = [
                        'cost_price' => $supplierData['cost_price'] ?? null,
                        'supplier_sku' => $supplierData['supplier_sku'] ?? null,
                        'is_preferred' => $supplierData['is_preferred'] ?? false,
                    ];
                }
            }
            
            if (!empty($suppliersData)) {
                $product->suppliers()->sync($suppliersData);
            }
        }
    }
}
