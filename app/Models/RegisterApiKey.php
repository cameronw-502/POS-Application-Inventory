<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisterApiKey extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'register_id',
        'name',
        'key',
        'token',
        'expires_at',
        'is_active',
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];
    
    /**
     * Get the register that owns the API key.
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }
    
    /**
     * Determine if the API key is valid.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate a new API key.
     */
    public static function generateForRegister(Register $register, string $name = null): self
    {
        return self::create([
            'register_id' => $register->id,
            'name' => $name ?? 'API Key for ' . $register->name,
            'key' => 'reg_' . \Illuminate\Support\Str::random(24),
            'token' => hash('sha256', \Illuminate\Support\Str::random(40)),
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
    }
}
