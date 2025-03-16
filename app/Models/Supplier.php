<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'website',
        'tax_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'notes',
        'status',
        'default_payment_terms',
        'default_shipping_method',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::deleting(function($supplier) {
            // Prevent deletion if the supplier has associated products
            if ($supplier->products()->count() > 0) {
                throw new \Exception('Cannot delete supplier with associated products.');
            }
        });
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_supplier');
    }
}