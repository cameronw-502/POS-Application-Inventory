<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'notes',
        'loyalty_points',
        'status',
    ];

    protected $casts = [
        'loyalty_points' => 'integer',
    ];

    /**
     * Get the sales for the customer.
     */
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Scope a query to only include active customers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the customer's full address.
     *
     * @return string
     */
    public function getFullAddressAttribute()
    {
        $parts = [
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ];

        return implode(', ', array_filter($parts));
    }

    /**
     * Calculate the customer's lifetime value.
     *
     * @return float
     */
    public function getLifetimeValueAttribute()
    {
        return $this->sales()->sum('total');
    }
}
