<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{

    // public function toArray($request)
    // {

    //     return [
    //         'id' => $this->id,
    //         'metal' => $this->metal,
    //         'user' => $this->user->name ?? '',
    //         'currency' => $this->currency,
    //         'prev_close_price' => $this->prev_close_price,
    //         'open_price' => $this->open_price,
    //         'low_price' => $this->low_price,
    //         'high_price' => $this->high_price,
    //         'price' => $this->price,
    //         'ch' => $this->ch,
    //         'chp' => $this->chp,
    //         'ask' => $this->ask,
    //         'bid' => $this->bid,
    //         'price_gram_24k' => $this->price_gram_24k,
    //         'price_gram_22k' => $this->price_gram_22k,
    //         'price_gram_21k' => $this->price_gram_21k,
    //         'price_gram_20k' => $this->price_gram_20k,
    //         'price_gram_18k' => $this->price_gram_18k,
    //         'price_gram_16k' => $this->price_gram_16k,
    //         'price_gram_14k' => $this->price_gram_14k,
    //         'price_gram_10k' => $this->price_gram_10k,
    //         'qty' => $this->qty,
    //         'stop_loss' => $this->stop_loss,
    //         'take_profit' => $this->take_profit,
    //         "sell_when_price" => $this->sell_when_price
    //     ];
    // }

    public function toArray($request)
    {
        // Backward-compatible: لا نغير المفتاح القديم "price"
        // لكن نضيف entry_price كسعر الدخول الحقيقي إن كان موجوداً.
        $entryPrice = $this->entry_price ?? $this->executed_price ?? $this->price;

        // Title without match() (PHP 7/8 safe)
        $title = $this->metal;
        if ($this->metal === 'XAU') {
            $title = transWord('Gold');
        } elseif ($this->metal === 'XAG') {
            $title = transWord('Silver');
        }

        return [
            // ====== OLD FIELDS (لازم تفضل كما هي للموبايل) ======
            'id'               => $this->id,
            'metal'            => $this->metal,

            // Avoid N+1 if relation not loaded
            'user'             => $this->whenLoaded('user', fn () => $this->user?->name ?? '', ''),

            'currency'         => $this->currency,

            'prev_close_price' => $this->prev_close_price,
            'open_price'       => $this->open_price,
            'low_price'        => $this->low_price,
            'high_price'       => $this->high_price,

            // لا نغيره حفاظاً على التوافق
            'price'            => $this->price,

            'ch'               => $this->ch,
            'chp'              => $this->chp,
            'ask'              => $this->ask,
            'bid'              => $this->bid,

            'price_gram_24k'   => $this->price_gram_24k,
            'price_gram_22k'   => $this->price_gram_22k,
            'price_gram_21k'   => $this->price_gram_21k,
            'price_gram_20k'   => $this->price_gram_20k,
            'price_gram_18k'   => $this->price_gram_18k,
            'price_gram_16k'   => $this->price_gram_16k,
            'price_gram_14k'   => $this->price_gram_14k,
            'price_gram_10k'   => $this->price_gram_10k,

            'qty'              => (float) $this->qty,
            'stop_loss'        => is_null($this->stop_loss) ? null : (float) $this->stop_loss,
            'take_profit'      => is_null($this->take_profit) ? null : (float) $this->take_profit,
            'sell_when_price'  => is_null($this->sell_when_price) ? null : (float) $this->sell_when_price,

            // ====== NEW FIELDS (إضافات بدون كسر القديم) ======
            'title'            => $title,

            'type'             => $this->type ?? null,     // trade / order
            'status'           => $this->status ?? null,   // open / executed / pending_delivery ...
            'mode'             => $this->mode ?? null,     // live / demo

            'entry_price'      => is_null($entryPrice) ? null : (float) $entryPrice,
            'close_price'      => is_null($this->close_price) ? null : (float) $this->close_price,

            'realized_pnl'     => is_null($this->realized_pnl) ? null : (float) $this->realized_pnl,
            'unrealized_pnl'   => is_null($this->unrealized_pnl) ? null : (float) $this->unrealized_pnl,

            'created_at'       => $this->created_at,
            'closed_at'        => $this->closed_at,
            'close_kind'       => $this->close_kind ?? null, // sell / delivery
        ];
    }


}
