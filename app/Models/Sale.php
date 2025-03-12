<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'subtotal',
        'tax_amount',
        'discount',
        'total',
        'payment_method',
        'payment_status',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'discount' => 'float',
        'total' => 'float',
    ];

    /**
     * Get the items for the sale.
     */
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Get the user who made the sale.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer for the sale.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope a query to only include completed sales.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Generate a receipt number.
     *
     * @return string
     */
    public function getReceiptNumberAttribute()
    {
        return 'R' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get total quantity of items in the sale.
     *
     * @return int
     */
    public function getTotalItemsAttribute()
    {
        return $this->items->sum('quantity');
    }

    /**
     * Calculate the amounts for the sale.
     */
    public function calculateAmounts()
    {
        $subtotal = $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
        
        $this->subtotal = $subtotal;
        $this->tax_amount = $subtotal * 0.07; // Assuming 7% tax rate
        $this->total = $subtotal + $this->tax_amount - ($this->discount ?? 0);
        
        return $this;
    }
}
