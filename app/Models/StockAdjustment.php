<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'reference_number',
        'notes',
        'user_id',
        'reference_id', // Can be sale_id, purchase_id, etc.
        'reference_type', // Can be 'sale', 'purchase', etc.
        'date',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    // Add this boot method to automatically generate reference_number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($stockAdjustment) {
            // Only generate a reference number if one isn't already set
            if (empty($stockAdjustment->reference_number)) {
                $stockAdjustment->reference_number = 'ADJ-' . Str::random(8);
            }
        });
    }

    /**
     * Get the items for the adjustment.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    /**
     * Get the user who made the adjustment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include adjustments of a certain type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the total quantity adjusted.
     *
     * @return int
     */
    public function getTotalQuantityAttribute()
    {
        return $this->items->sum('quantity');
    }

    /**
     * Get the total cost of adjusted items.
     *
     * @return float
     */
    public function getTotalCostAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->unit_cost * $item->quantity;
        });
    }
}
