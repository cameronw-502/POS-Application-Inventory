<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'upc',
        'price',
        'stock_quantity',
        'color_id',
        'size_id',
        'weight',
        'width',
        'height',
        'length',
        'image',
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'color_id' => 'integer',
        'size_id' => 'integer',
        'weight' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'length' => 'decimal:2',
    ];
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    public function color()
    {
        return $this->belongsTo(Color::class);
    }
    
    public function size()
    {
        return $this->belongsTo(Size::class);
    }
    
    // Auto-generate SKU when needed
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($variant) {
            if (empty($variant->sku)) {
                $product = Product::find($variant->product_id);
                if ($product) {
                    // Use parent SKU + variant suffix
                    $variantCount = self::where('product_id', $variant->product_id)->count() + 1;
                    $variant->sku = $product->sku . '-V' . $variantCount;
                }
            }
            
            // Set default UPC to SKU if not provided
            if (empty($variant->upc)) {
                $variant->upc = $variant->sku;
            }
        });
    }
}
