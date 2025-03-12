<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'subtotal' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'discount' => 'float',
        'quantity' => 'integer',
    ];

    /**
     * Get the sale that owns the item.
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the product for this item.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the subtotal for this item.
     */
    public function calculateSubtotal()
    {
        $this->subtotal = $this->unit_price * $this->quantity;
        
        return $this;
    }

    /**
     * Calculate the tax amount for this item.
     */
    public function calculateTaxAmount()
    {
        if (!isset($this->tax_rate)) {
            $this->tax_rate = $this->product->tax_rate ?? 0;
        }
        
        $this->tax_amount = $this->subtotal * ($this->tax_rate / 100);
        
        return $this;
    }
}
