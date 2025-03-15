<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'order_date',
        'expected_delivery_date',
        'total_amount',
        'tax_amount',
        'tax_rate',
        'shipping_amount',
        'status',
        'created_by',
        'notes',
        'payment_terms',
        'shipping_method',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        
        // Calculate totals before saving
        static::saving(function ($purchaseOrder) {
            $purchaseOrder->calculateTotals();
        });
        
        // Recalculate totals when items are added/changed
        static::updated(function ($purchaseOrder) {
            $purchaseOrder->fresh()->calculateTotals();
            // Save without triggering another update event
            $purchaseOrder->saveQuietly();
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receivingReports()
    {
        return $this->hasMany(ReceivingReport::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalReceivedAttribute()
    {
        return $this->items->sum('quantity_received');
    }

    public function getTotalOrderedAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function getIsFullyReceivedAttribute()
    {
        return $this->getTotalReceivedAttribute() >= $this->getTotalOrderedAttribute();
    }

    public static function generatePONumber()
    {
        $latest = self::orderBy('id', 'desc')->first();
        $number = $latest ? intval(substr($latest->po_number, 3)) + 1 : 1;
        return 'PO-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function getSubtotalAttribute()
    {
        return $this->items->sum('subtotal');
    }

    public function getTaxRateAttribute()
    {
        // Default tax rate, can be customized or stored in the database
        return 0.07; // 7%
    }

    public function getTaxAmountAttribute()
    {
        return $this->getSubtotalAttribute() * $this->getTaxRateAttribute();
    }

    public function getShippingAmountAttribute()
    {
        // Can be customized or stored in the database
        return $this->shipping_amount ?? 0;
    }

    /**
     * Calculate the total amount for the PO
     */
    public function calculateTotalAmount()
    {
        $this->total_amount = $this->getSubtotalAttribute() + $this->getTaxAmountAttribute() + $this->getShippingAmountAttribute();
        return $this;
    }

    /**
     * Calculate all totals for the purchase order
     */
    public function calculateTotals()
    {
        $subtotal = $this->getSubtotalAttribute();
        $taxAmount = $subtotal * $this->getTaxRateAttribute();
        $shippingAmount = $this->shipping_amount ?? 0;
        
        $this->tax_amount = $taxAmount;
        $this->total_amount = $subtotal + $taxAmount + $shippingAmount;
        
        return $this;
    }

    /**
     * Save the model without firing events
     */
    public function saveQuietly(array $options = [])
    {
        return static::withoutEvents(function () use ($options) {
            return $this->save($options);
        });
    }
}