<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\EditRecord;
use App\Models\ProductHistoryEvent;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $purchaseOrder = $this->record;

        // Check if status changed to ordered or other relevant status
        if ($purchaseOrder->wasChanged('status') && $purchaseOrder->status === 'ordered') {
            // Record history for each product
            foreach ($purchaseOrder->items as $item) {
                if ($item->product) {
                    $item->product->recordHistory(
                        ProductHistoryEvent::TYPE_PURCHASE_ORDER,
                        0, // No immediate quantity change
                        $purchaseOrder,
                        $purchaseOrder->po_number,
                        "Purchase order {$purchaseOrder->po_number} status changed to ordered"
                    );
                }
            }
        }

        // Refresh the model to get all relationships
        $purchaseOrder = $this->record->fresh();
        
        // Calculate totals
        $purchaseOrder->calculateTotals();
        
        // Save the updated totals
        $purchaseOrder->saveQuietly();
    }
}
