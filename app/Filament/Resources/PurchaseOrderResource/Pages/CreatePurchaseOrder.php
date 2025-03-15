<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    // Add this method to ensure totals are calculated on creation
    protected function afterCreate(): void
    {
        // Refresh the model to get all relationships
        $purchaseOrder = $this->record->fresh();
        
        // Calculate totals
        $purchaseOrder->calculateTotals();
        
        // Save the updated totals
        $purchaseOrder->saveQuietly();
    }
}
