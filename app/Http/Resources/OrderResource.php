<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Trading\OpenPnlService;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $pnl = app(OpenPnlService::class)->calculateForOrder($this->resource);

        return [
            'id'     => $this->id,
            'type'   => $this->type,
            'status' => $this->status,
            'mode'   => $this->mode,

            // ✅ NEW: Product Information
            'product_id'       => $this->product_id,
            'quantity'         => (int) ($this->quantity ?? 1),
            'unit_gram_weight' => $this->unit_gram_weight ? (float) $this->unit_gram_weight : null,
            'unit_karat'       => $this->unit_karat ? (int) $this->unit_karat : null,

            // Product details (when loaded)
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id'          => $this->product->id,
                    'name'        => $this->product->title,
                    'metal'       => $this->product->metal,
                    'karat'       => (int) $this->product->karat,
                    'gram_weight' => (float) $this->product->gram_weight,
                    'category'    => $this->product->category->title ?? null,
                ];
            }),

            // Metal & Currency
            'metal'    => $this->metal,
            'currency' => $this->currency,

            // Quantities (qty = total grams)
            'qty'         => $this->qty ? (float) $this->qty : null,
            'total_grams' => $this->getTotalGramsComputed(),

            // Prices
            'open_price'     => $this->open_price ? (float) $this->open_price : null,
            'price'          => $this->price ? (float) $this->price : null,
            'entry_price'    => $this->entry_price ? (float) $this->entry_price : null,
            'executed_price' => $this->executed_price ? (float) $this->executed_price : null,
            'close_price'    => $this->close_price ? (float) $this->close_price : null,

            // Trade controls
            'stop_loss'       => $this->stop_loss ? (float) $this->stop_loss : null,
            'take_profit'     => $this->take_profit ? (float) $this->take_profit : null,
            'sell_when_price' => $this->sell_when_price ? (float) $this->sell_when_price : null,

            // Existing PnL keys - كما هي بدون تغيير
            'realized_pnl'   => $this->realized_pnl ? (float) $this->realized_pnl : null,
            'unrealized_pnl' => $this->unrealized_pnl ? (float) $this->unrealized_pnl : null,

            // ✅ NEW: Live Floating P/L keys
            'entry_price_egp'         => $pnl['entry_price_egp'],
            'current_price_egp'       => $pnl['current_price_egp'],

            'floating_pnl_usd'        => $pnl['floating_pnl_usd'],
            'floating_pnl_egp'        => $pnl['floating_pnl_egp'],
            'floating_pnl_percent'    => $pnl['floating_pnl_percent'],

            'exchange_rate_usd_to_egp' => $pnl['exchange_rate_usd_to_egp'],

            // Closing info
            'close_kind' => $this->close_kind,
            'closed_at'  => $this->closed_at?->toIso8601String(),

            // Market snapshot (at order time)
            'prev_close_price' => $this->prev_close_price ? (float) $this->prev_close_price : null,
            'low_price'        => $this->low_price ? (float) $this->low_price : null,
            'high_price'       => $this->high_price ? (float) $this->high_price : null,
            'open_time'        => $this->open_time,
            'ch'               => $this->ch,
            'chp'              => $this->chp,
            'ask'              => $this->ask ? (float) $this->ask : null,
            'bid'              => $this->bid ? (float) $this->bid : null,

            // Gram prices snapshot
            'price_gram_24k' => $this->price_gram_24k ? (float) $this->price_gram_24k : null,
            'price_gram_22k' => $this->price_gram_22k ? (float) $this->price_gram_22k : null,
            'price_gram_21k' => $this->price_gram_21k ? (float) $this->price_gram_21k : null,
            'price_gram_20k' => $this->price_gram_20k ? (float) $this->price_gram_20k : null,
            'price_gram_18k' => $this->price_gram_18k ? (float) $this->price_gram_18k : null,
            'price_gram_16k' => $this->price_gram_16k ? (float) $this->price_gram_16k : null,
            'price_gram_14k' => $this->price_gram_14k ? (float) $this->price_gram_14k : null,
            'price_gram_10k' => $this->price_gram_10k ? (float) $this->price_gram_10k : null,

            // Delivery info
            'delivery_status'       => $this->delivery_status,
            'delivery_requested_at' => $this->delivery_requested_at?->toIso8601String(),
            'delivery_method'       => $this->delivery_method,
            'delivery_city'         => $this->delivery_city,
            'delivery_phone'        => $this->delivery_phone,
            'delivery_fee'          => $this->delivery_fee ? (float) $this->delivery_fee : null,
            'delivery_tracking_no'  => $this->delivery_tracking_no,
            'address'               => $this->address,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // ✅ Computed: Total cost at order time
            'total_cost' => $this->getTotalCost(),
        ];
    }

    /**
     * Get total grams (either from qty or calculated)
     */
    private function getTotalGramsComputed(): ?float
    {
        if ($this->qty) {
            return (float) $this->qty;
        }

        if ($this->quantity && $this->unit_gram_weight) {
            return (float) $this->quantity * (float) $this->unit_gram_weight;
        }

        return null;
    }

    /**
     * Calculate total cost at order time
     * ✅ يقرأ من metal_cost_snapshot (القيمة الفعلية المخصومة) أولاً
     */
    private function getTotalCost(): ?float
    {
        // ✅ القيمة الفعلية المخصومة وقت الإنشاء
        if (!is_null($this->metal_cost_snapshot) && (float) $this->metal_cost_snapshot > 0) {
            return round((float) $this->metal_cost_snapshot, 2);
        }

        // fallback — لو الـ snapshot مش موجود (أوردرات قديمة)
        $qty   = $this->getTotalGramsComputed();
        $price = (float) ($this->open_price ?? $this->price ?? 0);

        if ($qty && $price > 0) {
            return round($qty * $price, 2);
        }

        return null;
    }
}