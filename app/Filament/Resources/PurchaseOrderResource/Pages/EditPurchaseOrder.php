<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Add this method to ensure totals are calculated after editing
    protected function afterSave(): void
    {
        // Refresh the model to get all relationships
        $purchaseOrder = $this->record->fresh();
        
        // Calculate totals
        $purchaseOrder->calculateTotals();
        
        // Save the updated totals
        $purchaseOrder->saveQuietly();
    }
}
