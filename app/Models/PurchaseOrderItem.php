<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'quantity_received',
        'note',
        'supplier_sku',
        'selling_price'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
        'quantity_received' => 'integer', 
    ];

    /**
     * Ensure quantity_received defaults to 0
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (is_null($model->quantity_received)) {
                $model->quantity_received = 0;
            }
            $model->subtotal = $model->quantity * $model->unit_price;
        });

        static::updating(function ($model) {
            if (is_null($model->quantity_received)) {
                $model->quantity_received = 0;
            }
            $model->subtotal = $model->quantity * $model->unit_price;
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function receivingItems()
    {
        return $this->hasMany(ReceivingItem::class);
    }

    /**
     * Calculate remaining quantity to be received
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - ($this->quantity_received ?? 0));
    }

    /**
     * Is this item fully received?
     */
    public function getIsFullyReceivedAttribute(): bool
    {
        return ($this->quantity_received ?? 0) >= $this->quantity;
    }

    /**
     * Make sure quantity_received is never null
     */
    public function getQuantityReceivedAttribute($value)
    {
        return $value ?? 0;
    }
}