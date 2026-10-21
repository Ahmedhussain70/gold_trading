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
    use HasFactory , Translatable;
    public $translatedAttributes  = ['title'];
    protected $fillable = [
        'product_category_id',
        'lowest_price',
        'highest_price',
        'metal',         // NEW
        'karat',         // NEW
        'gram_weight',   // NEW
    ];

    protected $casts = [
        'lowest_price' => 'decimal:2',
        'highest_price' => 'decimal:2',
        'karat' => 'integer',
        'gram_weight' => 'decimal:3',
    ];

    protected $table = 'products';

    /**
     * Get the category that owns the Product
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id', 'id');
    }
}
