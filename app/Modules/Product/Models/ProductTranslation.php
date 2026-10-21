<?php

namespace Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['id', 'title'];

    protected $table = 'product_translations';
}
