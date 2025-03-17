<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('damage_images')
            ->useDisk('public')  // Use the public disk for better URL access
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /**
     * Register media conversions
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->performOnCollections('damage_images');
            
        $this->addMediaConversion('preview')
            ->width(600)
            ->height(600)
            ->performOnCollections('damage_images');
    }
}