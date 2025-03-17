<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ReceivingReportItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'receiving_report_id',
        'purchase_order_item_id',
        'product_id',
        'quantity_received',
        'quantity_good',
        'quantity_damaged',
        'quantity_missing',
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

    // Register media collections
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('damage_images');
    }
}