<?php

namespace SliderBanner\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Support\Facades\Storage;

class SliderBanner extends Model implements TranslatableContract
{
    use Translatable;
    public $translatedAttributes  = ['title','sub_title','link'];
    protected $fillable = ['id','image','created_at', 'updated_at'];
    protected $table = 'slider_banner';

    public function getImageAttribute($value)
    {
        return ($value != null) ? asset($value) : '';
    }
}
