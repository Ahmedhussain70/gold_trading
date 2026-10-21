<?php

namespace Blogs\Models;

use Illuminate\Database\Eloquent\Model;

class BlogTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['id', 'title','content'];
}
