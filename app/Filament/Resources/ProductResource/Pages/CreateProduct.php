<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
    
    // Make this form full width correctly
    protected ?string $maxContentWidth = 'full';
    
    // Change this to match Filament's expected property
    protected ?int $columnSpan = 12;
    
    protected ?string $maxWidth = 'full';
    protected ?string $contentWidth = 'full';
    
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
    
    // Replace the existing method with this improved version
    protected function handleSupplierRelationship($product, $supplierData)
    {
        $suppliersToSync = [];
        
        foreach ($supplierData as $data) {
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
            $product->suppliers()->attach($suppliersToSync);
        }
    }

    // Then update your afterCreate and afterSave methods:
    protected function afterCreate(): void
    {
        $product = $this->record;
        
        // Handle suppliers relationship
        if (isset($this->data['suppliers']) && is_array($this->data['suppliers'])) {
            $this->handleSupplierRelationship($product, $this->data['suppliers']);
        }
    }
}
