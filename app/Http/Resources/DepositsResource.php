<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DepositsResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => 'USD', // ✅ NEW: الإيداع دائماً بالدولار
            'request_at' => $this->created_at,
            'is_approval' => ( $this->is_approval == 1) ? 'Approved' : 'Not Approved',
            'approve_at' => $this->approve_at,
            'payment_method' => $this->payment_method, // ✅ NEW
            'receipt_url' => $this->receipt_url, // ✅ NEW
        ];
    }
}