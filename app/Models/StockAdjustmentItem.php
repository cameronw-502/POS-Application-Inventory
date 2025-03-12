<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'quantity',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'float',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function ($item) {
            $item->updateProductStock();
        });
    }

    /**
     * Get the adjustment that owns the item.
     */
    public function stockAdjustment()
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    /**
     * Get the product for this item.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Update the product stock based on the adjustment type.
     */
    public function updateProductStock()
    {
        $product = $this->product;
        
        if (!$product) {
            Log::error('Product not found for stock adjustment item', [
                'stock_adjustment_item_id' => $this->id,
                'product_id' => $this->product_id,
            ]);
            return;
        }

        $adjustmentType = optional($this->stockAdjustment)->type;
        $oldStock = $product->stock;

        // Update the product stock based on the adjustment type
        switch ($adjustmentType) {
            case 'addition':
            case 'purchase':
            case 'return':
                $product->stock += $this->quantity;
                break;

            case 'subtraction':
            case 'sale':
            case 'damage':
            case 'loss':
                $product->stock -= $this->quantity;
                break;
                
            default:
                Log::warning('Unknown stock adjustment type', [
                    'type' => $adjustmentType,
                    'stock_adjustment_id' => $this->stock_adjustment_id,
                ]);
                break;
        }

        $product->save();

        Log::info('Product stock updated by adjustment', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'adjustment_type' => $adjustmentType,
            'quantity' => $this->quantity,
            'old_stock' => $oldStock,
            'new_stock' => $product->stock,
        ]);
    }
}
