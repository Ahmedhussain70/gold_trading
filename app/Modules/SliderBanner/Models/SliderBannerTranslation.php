<?php

namespace SliderBanner\Models;

use Illuminate\Database\Eloquent\Model;

class SliderBannerTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['id', 'title','sub_title','link'];
    protected $table = 'slider_banner_translations';
}
