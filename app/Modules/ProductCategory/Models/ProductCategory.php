<?php

namespace ProductCategory\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class ProductCategory extends Model implements TranslatableContract
{
    use HasFactory , Translatable;
    public $translatedAttributes  = ['title'];
    protected $fillable = ['id', 'api_symbol', 'currency', 'created_at', 'updated_at'];

    protected $table = 'product_category';
}
