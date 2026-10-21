<?php

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class Deposits extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['receipt_url'];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class,'approved_by');
    }

    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_path) {
            return null;
        }

        return rtrim(Storage::disk('public')->url(''), '/') . '/' . ltrim($this->receipt_path, '/');
    }
}