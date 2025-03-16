<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Supplier;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
    
    protected ?string $maxContentWidth = 'full';
    protected ?int $columnSpan = 12;
    protected ?string $maxWidth = 'full';
    protected ?string $contentWidth = 'full';

    // Remove the mutateFormDataBeforeCreate method - we'll handle all supplier logic in afterCreate

    protected function handleRecordCreation(array $data): Model
    {
        try {
            // Log the data being sent to the create method
            \Log::info('Creating product with data:', [
                'data' => $data,
                'suppliers' => $data['single_supplier_id'] ?? 'none'
            ]);
            
            // Remove supplier fields from data before creating product
            $supplierFields = ['single_supplier_id', 'supplier_price', 'supplier_sku', 'supplier_unit_type', 'supplier_unit_quantity'];
            $productData = array_diff_key($data, array_flip($supplierFields));
            
            return static::getModel()::create($productData);
        } catch (\Exception $e) {
            \Log::error('Product creation failed: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);
            
            Notification::make()
                ->title('Error creating product')
                ->body('There was a problem creating this product: ' . $e->getMessage())
                ->danger()
                ->send();
                
            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        $product = $this->record;
        $supplierId = $this->data['single_supplier_id'] ?? null;
        
        if ($supplierId) {
            try {
                // Clear any existing suppliers just to be safe
                $product->suppliers()->detach();
                
                // Save to the pivot table with explicit values
                $product->suppliers()->attach($supplierId, [
                    'cost_price' => $this->data['supplier_price'] ?? 0,
                    'supplier_sku' => $this->data['supplier_sku'] ?? '',
                    'is_preferred' => true,
                    'sort' => 0,
                ]);
                
                // Save supplier unit information directly on the product
                $product->update([
                    'supplier_unit_type' => $this->data['supplier_unit_type'] ?? 'single',
                    'supplier_unit_quantity' => $this->data['supplier_unit_quantity'] ?? 1,
                ]);
                
                \Log::info('Supplier attached successfully', [
                    'product_id' => $product->id,
                    'supplier_id' => $supplierId
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to attach supplier: ' . $e->getMessage(), [
                    'product_id' => $product->id,
                    'supplier_id' => $supplierId,
                    'trace' => $e->getTraceAsString()
                ]);
                
                Notification::make()
                    ->title('Supplier Not Saved')
                    ->body('There was a problem saving the supplier information: ' . $e->getMessage())
                    ->warning()
                    ->send();
            }
        }
    }
}
