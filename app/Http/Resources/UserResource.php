<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Wallet\Models\Transaction;
use Wallet\Models\Wallet;

class UserResource extends JsonResource
{

    public function toArray($request)
    {
        $wallet = Wallet::where('user_id', $this->id)->first();
        $balance = 0;
        $balance_egp = 0; // ✅ NEW

        if ($wallet) {
            if ($this->mode === 'live') {
                $balance     = $wallet->amount;
                $balance_egp = $wallet->amount_egp ?? 0; // ✅ NEW
            } elseif ($this->mode === 'demo') {
                $balance = $wallet->amount_demo;
                // Demo بدون تحويل عملات
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile_number,
            'is_verified' => $this->is_verified,
            'publish' => $this->publish,
            'type' => $this->type,
            'mode' => $this->mode,
            'balance' => $balance,
            'balance_egp' => (float) $balance_egp, // ✅ NEW: رصيد الجنيه المصري

            'national_id_front' => $this->national_id_front ? asset('storage/' . $this->national_id_front) : null,
            'national_id_back' => $this->national_id_back ? asset('storage/' . $this->national_id_back) : null,

            'national_id_front_approved' => (bool) $this->national_id_front_approved,
            'national_id_back_approved' => (bool) $this->national_id_back_approved,
        ];
    }
}