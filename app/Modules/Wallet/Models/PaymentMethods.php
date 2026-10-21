<?php

namespace Wallet\Models; // <<< مهم جداً

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethods extends Model // <<< مفرد
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'value', 'is_active', 'sort_order', 'created_by', 'updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
                 ->orderBy('sort_order')
                 ->orderBy('id', 'desc');
    }
}
