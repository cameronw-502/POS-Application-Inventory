<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory, HasSlug, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'stock_quantity',
        'sku',
        'status',
        'category_id',
        'traits',
        'cost_price',
        'stock',
        'image',
        'featured',
        'barcode',
        'tax_rate',
        'min_stock',
        'max_stock',
        'is_active',
        'cost'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'category_id' => 'integer',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'featured' => 'boolean',
        'tax_rate' => 'float',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'is_active' => 'boolean',
        'cost' => 'decimal:2'
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product-images')
            ->useFallbackUrl('/images/placeholder.jpg')
            ->useFallbackPath(public_path('/images/placeholder.jpg'))
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('thumb')
                    ->width(200)
                    ->height(200);
                
                $this->addMediaConversion('preview')
                    ->width(400)
                    ->height(400);
            });
    }

    public function generateBarcode()
    {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        return $generator->getBarcode($this->sku, $generator::TYPE_CODE_128);
    }

    // Add mutators to keep both fields in sync
    public function setStockAttribute($value)
    {
        $this->attributes['stock'] = $value;
        $this->attributes['stock_quantity'] = $value;
        
        // Log the stock change
        \Log::info("Product stock updated directly", [
            'product_id' => $this->id ?? 'new',
            'product_name' => $this->name ?? 'unknown',
            'old_stock' => $this->getOriginal('stock') ?? 0,
            'new_stock' => $value,
        ]);
    }
    
    public function setStockQuantityAttribute($value)
    {
        $this->attributes['stock_quantity'] = $value;
        $this->attributes['stock'] = $value;
        
        // Log the stock change
        \Log::info("Product stock_quantity updated", [
            'product_id' => $this->id ?? 'new',
            'product_name' => $this->name ?? 'unknown',
            'old_stock_quantity' => $this->getOriginal('stock_quantity') ?? 0,
            'new_stock_quantity' => $value,
        ]);
    }

    /**
     * Scope a query to only include featured products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope a query to only include in-stock products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Scope a query to only include low stock products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0);
    }

    /**
     * Scope a query to only include out-of-stock products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('stock', '<=', 0);
    }

    /**
     * Get all transaction items for this product.
     */
    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
