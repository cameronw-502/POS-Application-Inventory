<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'process_sales',
        'apply_discounts',
        'void_transactions',
        'access_reports',
        'manage_inventory',
        'manage_users',
        'manage_settings',
    ];

    protected $casts = [
        'process_sales' => 'boolean',
        'apply_discounts' => 'boolean',
        'void_transactions' => 'boolean',
        'access_reports' => 'boolean',
        'manage_inventory' => 'boolean',
        'manage_users' => 'boolean',
        'manage_settings' => 'boolean',
    ];

    /**
     * Get the users with this role.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if the role has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return $this->$permission === true;
    }
}
