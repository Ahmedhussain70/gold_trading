<?php

namespace Blogs\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Blog extends Model implements TranslatableContract
{
    use HasFactory, Translatable;
    
    public $translatedAttributes = ['title', 'content'];
    
    protected $fillable = [
        'id',
        'image',
        'publish',
        'views',
        'twitter_image',
        'opengraph_image',
        'image_data',
        'created_at',
        'updated_at'
    ];

    public function getImageAttribute($value)
    {
        return ($value != null) ? asset($value) : '';
    }
}