<?php

namespace Wallet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $guarded = [];
    protected $table = 'wallets';


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
