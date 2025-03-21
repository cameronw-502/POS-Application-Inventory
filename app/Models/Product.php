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
use Illuminate\Support\Facades\DB;

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
        // Add new fields
        'weight',
        'width',
        'height',
        'length',
        'color_id',
        'size_id',
        'upc',
        'has_variations',
        // Keep existing fields
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
        // Existing casts
        'id' => 'integer',
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'category_id' => 'integer',
        // New casts
        'weight' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'length' => 'decimal:2',
        'color_id' => 'integer',
        'size_id' => 'integer',
        'has_variations' => 'boolean',
        // Existing casts
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

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_images')
            ->useDisk('public')  // Explicitly set disk
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
            ->performOnCollections('product_images');
            
        $this->addMediaConversion('preview')
            ->width(600)
            ->height(600)
            ->performOnCollections('product_images');
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

    /**
     * Get the history events for this product
     */
    public function historyEvents()
    {
        return $this->hasMany(ProductHistoryEvent::class)->orderByDesc('created_at');
    }
    
    /**
     * Record a history event for this product
     */
    public function recordHistory(
        string $eventType,
        float $quantityChange,
        ?Model $source = null,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): ProductHistoryEvent {
        return $this->historyEvents()->create([
            'event_type' => $eventType,
            'event_source_type' => $source ? get_class($source) : null,
            'event_source_id' => $source?->id,
            'quantity_change' => $quantityChange,
            'quantity_after' => $this->stock_quantity,
            'user_id' => auth()->id(),
            'reference_number' => $referenceNumber,
            'notes' => $notes,
        ]);
    }

    // Update stock_quantity setter to record history
    public function setStockQuantityAttribute($value)
    {
        $oldValue = $this->stock_quantity ?? 0;
        $change = $value - $oldValue;
        
        $this->attributes['stock_quantity'] = $value;
        $this->attributes['stock'] = $value;
        
        // Only record history if this is an existing product being updated
        if ($this->exists && $change !== 0) {
            $this->recordHistory(
                ProductHistoryEvent::TYPE_SYSTEM,
                $change,
                null,
                null,
                'Stock quantity updated directly'
            );
        }
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

    // Make sure your suppliers() relationship method looks exactly like this:
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot(['cost_price', 'supplier_sku', 'is_preferred', 'sort'])
            ->withTimestamps();
    }

    // Also add a simpler accessor for the primary supplier if needed
    public function primarySupplier()
    {
        return $this->suppliers()->first();
    }

    // Add relationship methods for color and size
    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    // Add relationship for variations
    public function variations()
    {
        return $this->hasMany(ProductVariant::class);
    }

    // Add these relationships to the Product model

    public function parentProduct()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function childProducts()
    {
        return $this->hasMany(Product::class, 'parent_product_id');
    }

    public function relatedProducts()
    {
        return $this->belongsToMany(
            Product::class, 
            'related_products',
            'product_id',
            'related_product_id'
        )->withTimestamps();
    }

    // Override the setAttribute method to prevent changing SKU
    public function setAttribute($key, $value)
    {
        if ($key === 'sku' && $this->exists && $this->sku) {
            // Don't allow SKU changes on existing products
            return $this;
        }
        
        return parent::setAttribute($key, $value);
    }

    // Auto-generate SKU when needed
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            if (empty($product->sku)) {
                // Generate SKU based on a prefix and the next product ID
                $nextId = (self::max('id') ?? 0) + 1;
                $product->sku = 'P' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
            }
            
            // Set default UPC to SKU if not provided
            if (empty($product->upc)) {
                $product->upc = $product->sku;
            }
        });
    }

    /**
     * Get purchase order items related to this product
     */
    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Calculate the quantity currently on order
     */
    public function getQuantityOnOrderAttribute()
    {
        // Get the sum of quantities from open purchase orders
        return $this->purchaseOrderItems()
            ->whereHas('purchaseOrder', function($query) {
                $query->whereIn('status', ['ordered', 'partially_received']);
            })
            ->sum(DB::raw('quantity - COALESCE(quantity_received, 0)'));
    }

    /**
     * Check if product has any pending orders
     */
    public function getHasPendingOrdersAttribute()
    {
        return $this->quantity_on_order > 0;
    }
}
