<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'receiving_report_id',
        'purchase_order_item_id',
        'quantity_received',
        'condition',
        'notes',
    ];

    public function receivingReport()
    {
        return $this->belongsTo(ReceivingReport::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}