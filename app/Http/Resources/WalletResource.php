<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Wallet\Models\Transaction;

class WalletResource extends JsonResource
{

    public function toArray($request)
    {
        // $transactions = Transaction::where('user_id',auth()->user()->id)->where('is_approval',1)->get();
        // $amount = 0;
        // if (count($transactions) > 0) {
        //     foreach ($transactions as $transaction) {
        //         if ($transaction->type == 'Deposit') {
        //             $amount += $transaction->amount;
        //         }else{
        //             $amount -= $transaction->amount;
        //         }
        //     }
        // }
        // return [
        //     'amount' => $amount,
        // ];
        $user = Auth::user();
        $amount = 0;

        if ($user && $user->mode === 'live') {
            $amount = $this->amount; // القيمة الفعلية من جدول wallets
        } elseif ($user && $user->mode === 'demo') {
            $amount = $this->amount_demo; // القيمة الفعلية من جدول wallets
        }

        return [
            'amount' => $amount,
        ];
    }
}
