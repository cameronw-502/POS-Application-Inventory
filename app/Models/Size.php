<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use HasFactory;
    
    protected $fillable = ['name', 'display_order'];
    
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    
    public function productVariants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}