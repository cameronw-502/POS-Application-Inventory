<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductHistoryEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'event_type',
        'event_source_type',
        'event_source_id',
        'quantity_change',
        'quantity_after',
        'user_id',
        'reference_number',
        'notes',
    ];

    // Event type constants
    public const TYPE_PURCHASE_ORDER = 'PO';
    public const TYPE_RECEIVING = 'RCV';
    public const TYPE_ADJUSTMENT = 'ADJ';
    public const TYPE_SALE = 'SALE';
    public const TYPE_RETURN = 'RTN';
    public const TYPE_SYSTEM = 'SYS';
    
    /**
     * Get the product associated with the history event
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    /**
     * Get the user associated with the history event
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the polymorphic relation to the source of this event
     */
    public function eventSource(): MorphTo
    {
        return $this->morphTo();
    }
    
    /**
     * Get formatted event type description
     */
    public function getEventTypeDescriptionAttribute(): string
    {
        return match($this->event_type) {
            self::TYPE_PURCHASE_ORDER => 'Purchase Order',
            self::TYPE_RECEIVING => 'Receiving',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_SALE => 'Sale',
            self::TYPE_RETURN => 'Return',
            self::TYPE_SYSTEM => 'System',
            default => $this->event_type,
        };
    }
    
    /**
     * Get icon for this event type
     */
    public function getEventIconAttribute(): string
    {
        return match($this->event_type) {
            self::TYPE_PURCHASE_ORDER => 'heroicon-o-clipboard-document',
            self::TYPE_RECEIVING => 'heroicon-o-truck',
            self::TYPE_ADJUSTMENT => 'heroicon-o-adjustments-horizontal',
            self::TYPE_SALE => 'heroicon-o-shopping-cart',
            self::TYPE_RETURN => 'heroicon-o-arrow-uturn-left',
            self::TYPE_SYSTEM => 'heroicon-o-cog',
            default => 'heroicon-o-document-text',
        };
    }
    
    /**
     * Get the user's initials
     */
    public function getUserInitialsAttribute(): string
    {
        if (!$this->user) {
            return 'SYS';
        }
        
        $name = $this->user->name;
        $parts = explode(' ', trim($name));
        
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
        }
        
        return strtoupper(substr($name, 0, 2));
    }
}
