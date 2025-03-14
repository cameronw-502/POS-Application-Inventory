<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'user_id',
        'device_info',
        'device_identifier', // Add this field
        'expires_at',
        'last_used_at',
        'is_active'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Generate new API key
    public static function generateKey()
    {
        return Str::random(32);
    }
}