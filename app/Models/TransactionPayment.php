<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPayment extends Model
{
    use HasFactory;
    
    // Add this line to specify the table name
    protected $table = 'payments';

    protected $fillable = [
        'transaction_id',
        'payment_method',
        'amount',
        'reference',
        'change_amount',
        'status',
    ];

    /**
     * Get the transaction this payment belongs to
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}