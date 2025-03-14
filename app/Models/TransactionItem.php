<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'subtotal_amount',
        'total_amount',
    ];

    /**
     * Get the transaction this item belongs to
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the product associated with this transaction item
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}