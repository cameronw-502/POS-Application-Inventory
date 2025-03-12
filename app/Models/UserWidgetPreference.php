<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWidgetPreference extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'widget_class',
        'is_visible',
        'sort_order',
        'column_span'
    ];
    
    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
        'column_span' => 'integer',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
