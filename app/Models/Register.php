<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];
    
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
    ];
    
    /**
     * Get the API keys associated with the register.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(RegisterApiKey::class);
    }
    
    /**
     * Get the active API key for this register.
     */
    public function getActiveApiKey()
    {
        return $this->apiKeys()
            ->where('is_active', true)
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now())
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
    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'disabled';
        }
        
        return $this->isOnline() ? 'online' : 'offline';
    }
}
