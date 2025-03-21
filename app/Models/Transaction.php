<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'register_number',
        'register_department',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal', // Changed from subtotal_amount
        'discount_amount',
        'tax_amount',
        'total_amount',
        'payment_status',
        'status',
        'notes',
        'customer_id'
    ];

    /**
     * Generate a unique receipt number
     */
    public static function generateReceiptNumber(): string
    {
        $prefix = 'INV-';
        $date = now()->format('Ymd');
        $lastInvoice = self::where('receipt_number', 'like', "{$prefix}{$date}%")
            ->orderBy('created_at', 'desc')
            ->first();

        $sequence = '0001';
        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->receipt_number, -4);
            $sequence = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        }

        return "{$prefix}{$date}{$sequence}";
    }

    /**
     * Get the transaction items
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Get the payment records for this transaction
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }

    /**
     * Get the user (cashier) who processed this transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer associated with this transaction
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Calculate and get the amount paid
     */
    public function getAmountPaidAttribute()
    {
        return $this->payments->sum('amount');
    }

    /**
     * Calculate and get the balance due
     */
    public function getBalanceDueAttribute()
    {
        return $this->total_amount - $this->amount_paid;
    }

    /**
     * Record product history for this transaction
     */
    public function recordProductHistory()
    {
        foreach ($this->items as $item) {
            $product = $item->product;
            if ($product) {
                $product->recordHistory(
                    ProductHistoryEvent::TYPE_SALE,
                    -$item->quantity, // Negative for sales
                    $this,
                    $this->transaction_number,
                    "Sold {$item->quantity} units - Transaction #{$this->transaction_number}"
                );
            }
        }
    }
}