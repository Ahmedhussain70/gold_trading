<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawsResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency ?? 'USD', // ✅ NEW
            'request_at' => $this->created_at,
            'is_approval' => ( $this->is_approval == 1) ? 'Approved' : 'Not Approved',
            'approve_at' => $this->approve_at,
            'payment_method' => $this->payment_method, // ✅ NEW
        ];
    }
}