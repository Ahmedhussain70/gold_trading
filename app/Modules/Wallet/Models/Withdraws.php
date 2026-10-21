<?php

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Withdraws extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['account_details'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getAccountDetailsAttribute(): array
    {
        return [
            'payment_method' => $this->payment_method,
            'account_name'   => $this->account_name,
            'account_phone'  => $this->account_phone,
            'bank_name'      => $this->bank_name,
            'bank_account'   => $this->bank_account,
            'address'        => $this->address,
            'binanceId'      => $this->binanceId,
            'transaction_id' => $this->transaction_id,
            'currency'       => $this->currency,
            'note'           => $this->note,
            'status'         => $this->status,
        ];
    }
}