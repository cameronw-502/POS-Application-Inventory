<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivingReportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'receiving_report_id',
        'purchase_order_item_id',
        'product_id',
        'quantity_received',
        'notes',
    ];

    public function receivingReport(): BelongsTo
    {
        return $this->belongsTo(ReceivingReport::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}