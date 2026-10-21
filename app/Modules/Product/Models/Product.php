<?php

namespace Product\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use ProductCategory\Models\ProductCategory;

class Product extends Model implements TranslatableContract
{
    use HasFactory, Translatable;

    public $translatedAttributes = ['title'];

    protected $fillable = [
        'product_category_id',
        'metal',
        'karat',
        'gram_weight',
        'manufacturing_fee',
        'has_delivery',
        'shipping_cost',
    ];

    protected $casts = [
        'karat' => 'integer',
        'gram_weight' => 'decimal:3',
        'manufacturing_fee'=> 'decimal:2',
        'has_delivery'     => 'boolean',
        'shipping_cost'    => 'decimal:2',
    ];

    protected $table = 'products';

    /**
     * Get the category that owns the Product
     */
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id', 'id');
    }

    /**
     * Get orders for this product
     */
    public function orders()
    {
        return $this->hasMany(\App\Modules\Orders\Models\Order::class, 'product_id', 'id');
    }

    /**
     * Get karat purity factor (for price calculation)
     * 24k = 1.0, 22k = 0.9167, 21k = 0.875, etc.
     */
    public function getKaratFactorAttribute(): float
    {
        return (int)$this->karat / 24;
    }

    /**
     * Helper: Get price field name based on karat
     * Returns the corresponding price_gram_XXk field name
     */
    public function getPriceFieldName(): string
    {
        return 'price_gram_' . (int)$this->karat . 'k';
    }
}
