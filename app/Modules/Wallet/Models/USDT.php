<?php

namespace Wallet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class USDT extends Model
{
    protected $guarded = [];
    protected $table = 'usdts';


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
