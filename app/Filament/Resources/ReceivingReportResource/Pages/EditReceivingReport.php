<?php

namespace App\Filament\Resources\ReceivingReportResource\Pages;

use App\Filament\Resources\ReceivingReportResource;
use Filament\Resources\Pages\EditRecord;
use App\Models\PurchaseOrderItem;

class EditReceivingReport extends EditRecord
{
    protected static string $resource = ReceivingReportResource::class;

    /**
     * Fill in calculated fields that might be missing during edit
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Make sure the items have the correct quantity values
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                // Get the purchase order item to ensure we have ordered quantity
                if (isset($item['purchase_order_item_id'])) {
                    $poItem = PurchaseOrderItem::with('product')->find($item['purchase_order_item_id']);
                    
                    if ($poItem) {
                        // Set the ordered quantity explicitly
                        $data['items'][$key]['quantity_ordered'] = $poItem->quantity;
                        
                        // Make sure we have the product info
                        $data['items'][$key]['product_id'] = $poItem->product_id;
                        
                        // Set initial values if missing
                        if (!isset($item['quantity_good'])) {
                            $data['items'][$key]['quantity_good'] = $item['quantity_received'] ?? 0;
                        }
                        
                        // Make sure we have damaged quantities
                        if (!isset($item['quantity_damaged'])) {
                            $data['items'][$key]['quantity_damaged'] = 0;
                        }
                        
                        // Calculate missing quantity if not set
                        $quantityGood = (int)($data['items'][$key]['quantity_good'] ?? 0);
                        $quantityDamaged = (int)($data['items'][$key]['quantity_damaged'] ?? 0);
                        $data['items'][$key]['quantity_missing'] = 
                            max(0, $poItem->quantity - $quantityGood - $quantityDamaged);
                    }
                }
            }
        }
        
        return $data;
    }
}
