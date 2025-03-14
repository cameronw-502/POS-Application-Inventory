<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
        DB::beginTransaction();
        
        try {
            $product = $this->product;
            
            if (!$product) {
                Log::error('Product not found for stock adjustment item', [
                    'stock_adjustment_item_id' => $this->id,
                    'product_id' => $this->product_id,
                ]);
                return;
            }

            $stockAdjustment = $this->stockAdjustment;
            if (!$stockAdjustment) {
                Log::error('Stock adjustment not found for item', [
                    'stock_adjustment_item_id' => $this->id,
                    'stock_adjustment_id' => $this->stock_adjustment_id,
                ]);
                return;
            }

            $adjustmentType = $stockAdjustment->type;
            $oldStock = $product->stock;

            // Update the product stock based on the adjustment type
            switch ($adjustmentType) {
                case 'addition':
                case 'purchase':
                case 'return':
                    $product->stock_quantity += $this->quantity;
                    $product->stock = $product->stock_quantity; // Ensure both fields are updated
                    break;

                case 'subtraction':
                case 'sale':
                case 'damage':
                case 'loss':
                    $product->stock_quantity -= $this->quantity;
                    $product->stock = $product->stock_quantity; // Ensure both fields are updated
                    break;
                    
                default:
                    Log::warning('Unknown stock adjustment type', [
                        'type' => $adjustmentType,
                        'stock_adjustment_id' => $this->stock_adjustment_id,
                    ]);
                    break;
            }

            // Save the product
            $product->save();
            
            DB::commit();

            Log::info('Product stock updated by adjustment', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'adjustment_type' => $adjustmentType,
                'quantity' => $this->quantity,
                'old_stock' => $oldStock,
                'new_stock' => $product->stock,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateProductStock', [
                'error' => $e->getMessage(),
                'stock_adjustment_item_id' => $this->id,
                'product_id' => $this->product_id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
