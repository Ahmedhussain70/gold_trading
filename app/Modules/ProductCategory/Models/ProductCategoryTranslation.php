<?php

namespace ProductCategory\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategoryTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['id', 'title'];

    protected $table = 'product_category_translations';
}
