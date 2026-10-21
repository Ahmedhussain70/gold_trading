<?php

namespace Ticket\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;


class Ticket extends Model
{
    protected $guarded = [];

    protected $table = 'tickets';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
