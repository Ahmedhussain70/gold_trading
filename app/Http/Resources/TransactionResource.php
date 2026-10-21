<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Wallet\Models\Transaction;

class TransactionResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'credit'      => $this->credit,
            'debit'       => $this->debit,
            'balance'     => $this->balance,
            'currency'    => strtoupper($this->currency ?? 'USD'),
            'type'        => $this->type,
            'type_label'  => match($this->type) {
                'Deposit'                => 'إيداع رصيد',
                'Withdraw'               => 'سحب رصيد',
                'trade_open'             => 'شراء فوري',
                'order_reserve'          => 'حجز أوردر',
                'order_cancel_refund'    => 'استرداد إلغاء',
                'order_execution_refund' => 'استرداد فرق سعر',
                'sell'                   => 'عائد بيع',
                'delivery'               => 'رسوم توصيل',
                'delivery_refund'        => 'استرداد توصيل',
                'currency_convert'       => 'تحويل عملة',
                default                  => $this->type,
            },
            'amount_type' => ucfirst($this->amount_type),
            'created_by'  => ucfirst($this->created_by),
            'note'        => $this->note ?? '',
            'is_approval' => $this->is_approval == 1 ? 'Approved' : 'Not Approved',
            'request_at'  => optional($this->request_at)->format('Y-m-d H:i:s'),
            'approve_at'  => optional($this->approve_at)->format('Y-m-d H:i:s'),
            'created_at'  => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at'  => optional($this->updated_at)->format('Y-m-d H:i:s'),
        ];
    }
}
