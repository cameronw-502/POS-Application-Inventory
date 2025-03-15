<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Register extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'location',
        'register_number',
        'is_active',
        'settings',
        'last_activity',
        'current_user_id',
        'status',
        'current_cash_amount',
        'opening_amount',
        'expected_cash_amount',
        'session_started_at',
        'session_transaction_count',
        'session_revenue',
    ];
    
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
        'session_started_at' => 'datetime',
        'current_cash_amount' => 'decimal:2',
        'opening_amount' => 'decimal:2',
        'expected_cash_amount' => 'decimal:2',
        'session_revenue' => 'decimal:2',
    ];
    
    /**
     * Get the API keys associated with the register.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(RegisterApiKey::class);
    }
    
    /**
     * Get the transactions associated with the register.
     */
    public function transactions(): HasMany
    {
        // Use register_number instead of register_id for the relationship
        return $this->hasMany(Transaction::class, 'register_number', 'register_number');
    }
    
    /**
     * Get the current user logged into the register.
     */
    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }
    
    /**
     * Get the active API key for this register.
     */
    public function getActiveApiKey()
    {
        return $this->apiKeys()
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();
    }
    
    /**
     * Determine if the register is currently online.
     */
    public function isOnline(): bool
    {
        return $this->last_activity && $this->last_activity->gt(now()->subMinutes(5));
    }
    
    /**
     * Get the register's status.
     */
    public function getStatusAttribute($value): string
    {
        if (!$this->is_active) {
            return 'disabled';
        }
        
        return $value ?? ($this->isOnline() ? 'online' : 'offline');
    }
    
    /**
     * Get today's transactions.
     */
    public function getTodaysTransactions()
    {
        return $this->transactions()
            ->whereDate('created_at', today())
            ->get();
    }
    
    /**
     * Get today's revenue.
     */
    public function getTodaysRevenueAttribute()
    {
        return $this->transactions()
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('total_amount');
    }
    
    /**
     * Get today's transaction count.
     */
    public function getTodaysTransactionCountAttribute()
    {
        return $this->transactions()
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->count();
    }
    
    /**
     * Get the cash drawer difference.
     */
    public function getCashDifferenceAttribute()
    {
        return $this->current_cash_amount - $this->expected_cash_amount;
    }
    
    /**
     * Get the average transaction value today.
     */
    public function getAverageTransactionValueAttribute()
    {
        $count = $this->todays_transaction_count;
        return $count > 0 ? $this->todays_revenue / $count : 0;
    }
    
    /**
     * Get percentage of transactions by payment method.
     */
    public function getCreditCardTransactionsPercentAttribute()
    {
        $total = $this->todays_transaction_count;
        if ($total <= 0) return '0%';
        
        // Update this to join with payments table or use a different approach
        // depending on how payment methods are stored
        $query = $this->transactions()
            ->whereDate('created_at', today())
            ->where('status', 'completed');
            
        // If payment_method is on the transaction table:
        $cardCount = $query->where('payment_method', 'credit_card')->count();
        // Or if it's in the payments table, you may need to adjust this:
        // $cardCount = $query->whereHas('payments', function($q) {
        //     $q->where('payment_method', 'credit_card');
        // })->count();
            
        return round(($cardCount / $total) * 100) . '%';
    }
    
    /**
     * Get percentage of cash transactions.
     */
    public function getCashTransactionsPercentAttribute()
    {
        $total = $this->todays_transaction_count;
        if ($total <= 0) return '0%';
        
        // Same adjustment as above
        $query = $this->transactions()
            ->whereDate('created_at', today())
            ->where('status', 'completed');
            
        // If payment_method is on the transaction table:
        $cashCount = $query->where('payment_method', 'cash')->count();
        // Or with payments relationship:
        // $cashCount = $query->whereHas('payments', function($q) {
        //     $q->where('payment_method', 'cash');
        // })->count();
            
        return round(($cashCount / $total) * 100) . '%';
    }
    
    /**
     * Start a new register session.
     */
    public function startSession(User $user)
    {
        $this->current_user_id = $user->id;
        $this->session_started_at = now();
        $this->session_transaction_count = 0;
        $this->session_revenue = 0;
        $this->status = 'online';
        $this->last_activity = now();
        $this->save();
        
        // You could log this event
        return true;
    }
    
    /**
     * End the current register session.
     */
    public function endSession()
    {
        // Log the session details before resetting
        
        $this->current_user_id = null;
        $this->session_started_at = null;
        $this->status = 'offline';
        $this->save();
        
        return true;
    }
}
