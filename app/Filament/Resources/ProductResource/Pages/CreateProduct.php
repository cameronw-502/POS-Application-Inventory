<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
    
    // Before the record is created, validate supplier data
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Check if there are suppliers to be linked
        if (isset($data['suppliers']) && is_array($data['suppliers'])) {
            foreach ($data['suppliers'] as $key => $supplierData) {
                // If supplier_id is empty or null, remove this entry to prevent creating a new supplier
                if (empty($supplierData['supplier_id'])) {
                    unset($data['suppliers'][$key]);
                }
            }
        }
        
        return $data;
    }
    
    protected function handleRecordCreation(array $data): Model
    {
        try {
            // Log the data being sent to the create method
            \Log::info('Creating product with data:', [
                'data' => $data,
                'suppliers' => $data['suppliers'] ?? 'none'
            ]);
            
            return static::getModel()::create($data);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Product creation failed: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show a notification to the user
            Notification::make()
                ->title('Error creating product')
                ->body('There was a problem creating this product: ' . $e->getMessage())
                ->danger()
                ->send();
                
            // Re-throw the exception
            throw $e;
        }
    }
    
    // After the record is created, handle the supplier relationships
    protected function afterCreate(): void
    {
        $product = $this->record;
        
        // If suppliers were provided, ensure they're correctly attached
        if (isset($this->data['suppliers']) && is_array($this->data['suppliers'])) {
            foreach ($this->data['suppliers'] as $supplierData) {
                // Only process items with a supplier_id
                if (!empty($supplierData['supplier_id'])) {
                    // Direct DB approach to avoid model issues
                    \DB::table('product_supplier')->insert([
                        'product_id' => $product->id,
                        'supplier_id' => $supplierData['supplier_id'],
                        'cost_price' => $supplierData['cost_price'] ?? null,
                        'supplier_sku' => $supplierData['supplier_sku'] ?? null,
                        'is_preferred' => $supplierData['is_preferred'] ?? false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
