<?php

namespace App\Jobs;

use App\Modules\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Wallet\Models\Wallet;
use Wallet\Models\Transaction;

class CheckOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    private float $spread = 0.10;
    private float $gramsPerOunce = 31.1035;

    public function handle()
    {
        $lockSeconds = (int) config('trading.lock_seconds', 50);
        $lock = Cache::lock('job:check_orders', $lockSeconds);

        if (!$lock->get()) {
            return;
        }

        try {
            $quotes = $this->getMetalsQuotesCached();

            if (
                empty($quotes['XAU']['market']) &&
                empty($quotes['XAG']['market'])
            ) {
                return;
            }

            // تنفيذ الأوامر المعلقة
            $this->processPendingOrders($quotes);

            // إغلاق الصفقات المفتوحة حسب TP/SL
            $this->processOpenTrades($quotes);

        } catch (\Throwable $e) {
            Log::error('CheckOrdersJob handle error: ' . $e->getMessage());
        } finally {
            optional($lock)->release();
        }
    }

    private function getMetalsQuotesCached(): array
    {
        $ttl = (int) config('trading.quotes_ttl_seconds', 300);
        $cacheKey = 'trading:quotes:xau_xag';

        $quotes = Cache::remember($cacheKey, $ttl, function () {
            return $this->fetchMetalsQuotesFromTwelveData();
        });

        $this->broadcastPrices($quotes);

        return $quotes;
    }

    private function fetchMetalsQuotesFromTwelveData(): array
    {
        $key = config('services.twelvedata.key', '');
        if (!$key) {
            Log::warning('CheckOrdersJob: TwelveData key missing');
            return $this->emptyQuotes();
        }

        $result = $this->emptyQuotes();

        foreach (['XAU' => 'XAU/USD', 'XAG' => 'XAG/USD'] as $metal => $symbol) {
            try {
                $resp = Http::retry(2, 250)->timeout(10)->get('https://api.twelvedata.com/price', [
                    'symbol' => $symbol,
                    'apikey' => $key,
                ]);

                if (!$resp->successful()) {
                    Log::warning("TwelveData price failed [$symbol]: ".$resp->status().' '.$resp->body());
                    continue;
                }

                $json  = $resp->json();
                $price = isset($json['price']) ? (float) $json['price'] : null;

                if (!$price || $price <= 0) {
                    Log::warning("TwelveData invalid price [$symbol]: ".json_encode($json));
                    continue;
                }

                // تحويل من أونصة إلى جرام
                $pricePerGram = $price / $this->gramsPerOunce;

                $ttlPnl = (int) config('trading.quotes_ttl_seconds', 300);
                Cache::put("td:last:{$symbol}", ['price' => $price], $ttlPnl);
                
                $buy  = round($pricePerGram + $this->spread, 4);
                $sell = round($pricePerGram - $this->spread, 4);

                $result[$metal] = [
                    'market' => round($pricePerGram, 4),
                    'buy'    => $buy,
                    'sell'   => $sell,
                ];
            } catch (\Throwable $e) {
                Log::error("TwelveData exception [$symbol]: ".$e->getMessage());
            }
        }

        return $result;
    }

    private function emptyQuotes(): array
    {
        return [
            'XAU' => ['market' => null, 'buy' => null, 'sell' => null],
            'XAG' => ['market' => null, 'buy' => null, 'sell' => null],
        ];
    }

    /**
     * تنفيذ الأوامر المعلقة:
     * - type=order, status=pending
     * - شرط التنفيذ (Limit Buy): buy_price <= open_price
     * 
     * ✅ UPDATED: يستخدم qty (إجمالي الجرامات) مباشرة
     */
    private function processPendingOrders(array $quotes): void
    {
        Order::where('type', 'order')
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById(300, function ($orders) use ($quotes) {
                foreach ($orders as $order) {
                    $metal    = (string) $order->metal;
                    $currency = strtoupper($order->currency ?? 'USD');

                    $baseMarketPrice = (float) ($quotes[$metal]['market'] ?? 0);
                    if ($baseMarketPrice <= 0) continue;

                    // ✅ تحويل للعملة الصح قبل المقارنة
                    if ($currency === 'EGP') {
                        $egpRate     = $this->getExchangeRate('EGP');
                        $marketPrice = $baseMarketPrice * $egpRate;
                    } else {
                        $marketPrice = $baseMarketPrice;
                    }

                    $trigger = null;
                    if (!empty($order->sell_when_price) && (float) $order->sell_when_price > 0) {
                        $trigger = (float) $order->sell_when_price;
                    } else {
                        $trigger = (float) ($order->open_price ?? 0);
                    }

                    if ($trigger <= 0) continue;

                    $openPrice = (float) ($order->open_price ?? 0);

                    if ($trigger > $openPrice) {
                        // Stop Buy — ينفذ لما السعر يصعد للـ trigger
                        if ($marketPrice >= $trigger) {
                            $this->executeEntry($order->id, $trigger); // ✅ trigger
                        }
                    } else {
                        // Limit Buy — ينفذ لما السعر ينزل للـ trigger
                        if ($marketPrice <= $trigger) {
                            $this->executeEntry($order->id, $trigger); // ✅ trigger
                        }
                    }
                }
            });
    }

    /**
     * ✅ تنفيذ دخول الأوردر
     * - يحول من order -> trade
     * - يحفظ سعر الدخول الفعلي
     */
    // private function executeEntry(int $orderId, float $entryPrice): void
    // {
    //     DB::beginTransaction();
    //     try {
    //         $order = Order::where('id', $orderId)->lockForUpdate()->first();
    //         if (!$order) { DB::rollBack(); return; }

    //         if ($order->type !== 'order' || $order->status !== 'pending') {
    //             DB::rollBack();
    //             return;
    //         }

    //         $update = [
    //             'type'           => 'trade',
    //             'status'         => 'open',
    //             'executed_price' => $entryPrice,
    //             'executed_at'    => now(),
    //         ];

    //         if (Schema::hasColumn('orders', 'entry_price')) {
    //             $update['entry_price'] = $entryPrice;
    //         }

    //         $order->update($update);

    //         // Log the execution
    //         Log::info("Order #{$orderId} executed as trade at price {$entryPrice}", [
    //             'product_id' => $order->product_id,
    //             'quantity'   => $order->quantity,
    //             'qty_grams'  => $order->qty,
    //         ]);

    //         DB::commit();
    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         Log::error('CheckOrdersJob executeEntry error: '.$e->getMessage());
    //     }
    // }
    // private function executeEntry(int $orderId, float $entryPrice): void
    // {
    //     DB::beginTransaction();
    //     try {
    //         $order = Order::where('id', $orderId)->lockForUpdate()->first();
    //         if (!$order) { DB::rollBack(); return; }

    //         if ($order->type !== 'order' || $order->status !== 'pending') {
    //             DB::rollBack();
    //             return;
    //         }

    //         // ✅ حساب الفرق وإرجاعه للمحفظة
    //         $openPrice  = (float) $order->open_price;
    //         $qty        = (float) $order->qty;
    //         $reserved   = round($openPrice * $qty, 2);
    //         $actualCost = round($entryPrice * $qty, 2);
    //         $refund     = round($reserved - $actualCost, 2);

    //         $update = [
    //             'type'           => 'trade',
    //             'status'         => 'open',
    //             'executed_price' => $entryPrice,
    //             'executed_at'    => now(),
    //         ];

    //         if (Schema::hasColumn('orders', 'entry_price')) {
    //             $update['entry_price'] = $entryPrice;
    //         }

    //         $order->update($update);

    //         // ✅ رد الفرق للمحفظة لو السعر اتنفذ بسعر أقل من المحجوز
    //         if ($refund > 0) {
    //             $currency     = strtoupper($order->currency ?? 'USD');
    //             $walletColumn = match(true) {
    //                 $order->mode === 'demo' => 'amount_demo',
    //                 $currency === 'EGP'     => 'amount_egp',
    //                 default                 => 'amount',
    //             };

    //             $wallet = Wallet::where('user_id', $order->user_id)
    //                 ->lockForUpdate()->first();

    //             if ($wallet) {
    //                 $wallet->increment($walletColumn, $refund);

    //                 $gramWeight      = (float) ($order->unit_gram_weight ?? 1);
    //                 $quantity        = (int)   ($order->quantity ?? 1);
    //                 $entryPerBar     = round($entryPrice * $gramWeight, 2); // ✅

                    

    //                 Transaction::create([
    //                     'user_id'     => $order->user_id,
    //                     'credit'      => $refund,
    //                     'debit'       => 0,
    //                     'balance'     => (float) $wallet->fresh()->{$walletColumn},
    //                     'type'        => 'order_execution_refund',
    //                     'amount_type' => $order->mode,
    //                     'currency'    => $currency,
    //                     'created_by'  => 'system',
    //                     'is_approval' => 1,
    //                     'approve_at'  => now(),
    //                     'request_at'  => now(),
    //                     // 'note'        => "Order #{$orderId} executed. Reserved={$reserved}, Actual={$actualCost}, Refund={$refund} [{$currency}]",
    //                     'note' => "تنفيذ أوردر — {$quantity} قطعة | سعر التنفيذ: {$entryPerBar} {$currency}/سبيكة | مُسترد: " . number_format($refund, 2) . " {$currency}",
    //                 ]);
    //             }
    //         }

    //         Log::info("Order #{$orderId} executed as trade at price {$entryPrice}", [
    //             'product_id' => $order->product_id,
    //             'quantity'   => $order->quantity,
    //             'qty_grams'  => $order->qty,
    //             'reserved'   => $reserved,
    //             'actual'     => $actualCost,
    //             'refund'     => $refund,
    //         ]);

    //         DB::commit();
    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         Log::error('CheckOrdersJob executeEntry error: '.$e->getMessage());
    //     }
    // }
    private function executeEntry(int $orderId, float $entryPrice): void
    {
        DB::beginTransaction();
        try {
            $order = Order::where('id', $orderId)->lockForUpdate()->first();
            if (!$order) { DB::rollBack(); return; }

            if ($order->type !== 'order' || $order->status !== 'pending') {
                DB::rollBack();
                return;
            }

            $qty = (float) $order->qty;

            // ✅ المحجوز كان على sell_when_price (السعر المستهدف) — مش open_price
            $reservePrice = (!empty($order->sell_when_price) && (float)$order->sell_when_price > 0)
                ? (float) $order->sell_when_price
                : (float) $order->open_price;

            $reserved   = round($reservePrice * $qty, 2);
            $actualCost = round($entryPrice * $qty, 2);
            $refund     = round($reserved - $actualCost, 2);

            $currency     = strtoupper($order->currency ?? 'USD');
            $walletColumn = match(true) {
                $order->mode === 'demo' && $currency === 'EGP' => 'amount_demo_egp',
                $order->mode === 'demo'                        => 'amount_demo',
                $currency === 'EGP'                            => 'amount_egp',
                default                                        => 'amount',
            };

            $gramWeight  = (float) ($order->unit_gram_weight ?? 1);
            $quantity    = (int)   ($order->quantity ?? 1);
            $entryPerBar = round($entryPrice * $gramWeight, 2);

            $update = [
                'type'           => 'trade',
                'status'         => 'open',
                'executed_price' => $entryPrice,
                'executed_at'    => now(),
            ];

            if (Schema::hasColumn('orders', 'entry_price')) {
                $update['entry_price'] = $entryPrice;
            }

            $order->update($update);

            $wallet = Wallet::where('user_id', $order->user_id)->lockForUpdate()->first();

            if ($wallet) {
                if ($refund > 0) {
                    // ✅ Limit Buy — السعر نزل → رد الفرق
                    $wallet->increment($walletColumn, $refund);
                    $balance = (float) $wallet->fresh()->{$walletColumn};

                    Transaction::create([
                        'user_id'     => $order->user_id,
                        'credit'      => $refund,
                        'debit'       => 0,
                        'balance'     => $balance,
                        'type'        => 'order_execution_refund',
                        'amount_type' => $order->mode,
                        'currency'    => $currency,
                        'created_by'  => 'system',
                        'is_approval' => 1,
                        'approve_at'  => now(),
                        'request_at'  => now(),
                        'note'        => "تنفيذ Limit Buy — {$quantity} قطعة | سعر التنفيذ: {$entryPerBar} {$currency}/سبيكة | مُسترد: " . number_format($refund, 2) . " {$currency}",
                    ]);

                } elseif ($refund < 0) {
                    // ✅ Stop Buy — السعر صعد → اخصم الفرق الزيادة
                    $extra     = abs($refund);
                    $available = (float) $wallet->{$walletColumn};

                    if ($available >= $extra) {
                        $wallet->decrement($walletColumn, $extra);
                        $balance = (float) $wallet->fresh()->{$walletColumn};

                        Transaction::create([
                            'user_id'     => $order->user_id,
                            'credit'      => 0,
                            'debit'       => $extra,
                            'balance'     => $balance,
                            'type'        => 'order_execution_extra',
                            'amount_type' => $order->mode,
                            'currency'    => $currency,
                            'created_by'  => 'system',
                            'is_approval' => 1,
                            'approve_at'  => now(),
                            'request_at'  => now(),
                            'note'        => "تنفيذ Stop Buy — {$quantity} قطعة | سعر التنفيذ: {$entryPerBar} {$currency}/سبيكة | فرق إضافي: " . number_format($extra, 2) . " {$currency}",
                        ]);
                    } else {
                        Log::warning("executeEntry: insufficient balance for Stop Buy extra charge", [
                            'order_id'  => $orderId,
                            'extra'     => $extra,
                            'available' => $available,
                            'currency'  => $currency,
                        ]);
                    }
                }
                // refund == 0 → السعر نفسه، مفيش تعديل
            }

            Log::info("Order #{$orderId} executed as trade at price {$entryPrice}", [
                'product_id'    => $order->product_id,
                'quantity'      => $quantity,
                'qty_grams'     => $qty,
                'reserve_price' => $reservePrice,
                'reserved'      => $reserved,
                'actual'        => $actualCost,
                'refund'        => $refund,
                'type'          => $refund > 0 ? 'limit_buy_refund' : ($refund < 0 ? 'stop_buy_extra' : 'exact'),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CheckOrdersJob executeEntry error: '.$e->getMessage());
        }
    }

    /**
     * إغلاق الصفقات المفتوحة:
     * - type=trade, status=open
     * - الإغلاق على SELL مع TP/SL
     * 
     * ✅ UPDATED: يستخدم qty (إجمالي الجرامات)
     */
    private function processOpenTrades(array $quotes): void
    {
        Order::where('type', 'trade')
            ->where('status', 'open')
            ->orderBy('id')
            ->chunkById(300, function ($trades) use ($quotes) {
                foreach ($trades as $trade) {
                    if ($trade->status === 'pending_delivery' || !empty($trade->delivery_status)) {
                        continue;
                    }

                    $metal    = (string) $trade->metal;
                    $currency = strtoupper($trade->currency ?? 'USD');

                    $baseSellPrice = (float) ($quotes[$metal]['sell'] ?? 0);
                    if ($baseSellPrice <= 0) continue;

                    // ✅ تحويل للعملة الصح قبل المقارنة
                    if ($currency === 'EGP') {
                        $egpRate   = $this->getExchangeRate('EGP');
                        $sellPrice = $baseSellPrice * $egpRate;
                    } else {
                        $sellPrice = $baseSellPrice;
                    }

                    $tp = (float) ($trade->take_profit ?? 0);
                    $sl = (float) ($trade->stop_loss  ?? 0);

                    if ($tp <= 0 && $sl <= 0) continue;

                    if ($tp > 0 && $sellPrice >= $tp) {
                        $this->closeTradeSystem($trade->id, $tp, 'tp');   // ✅ tp بدل sellPrice
                        continue;
                    }

                    if ($sl > 0 && $sellPrice <= $sl) {
                        $this->closeTradeSystem($trade->id, $sl, 'sl');   // ✅ sl بدل sellPrice
                        continue;
                    }
                }
            });
    }

    /**
     * ✅ إغلاق الصفقة مع حساب العمولة
     * - يستخدم qty (إجمالي الجرامات) للحسابات
     * - نموذج (A): خصم كامل عند الفتح، إرجاع principal + pnl - fee عند الإغلاق
     */
    private function closeTradeSystem(int $tradeId, float $closePrice, string $reason): void
    {
        DB::beginTransaction();
        try {
            $order = Order::where('id', $tradeId)->lockForUpdate()->first();
            if (!$order) { DB::rollBack(); return; }

            if ($order->type !== 'trade' || $order->status !== 'open') {
                DB::rollBack();
                return;
            }

            if ($order->status === 'pending_delivery' || !empty($order->delivery_status)) {
                DB::rollBack();
                return;
            }

            $qty = (float) ($order->qty ?? 0);

            $entry = 0.0;
            if (Schema::hasColumn('orders', 'entry_price') && !empty($order->entry_price)) {
                $entry = (float) $order->entry_price;
            } else {
                $entry = (float) ($order->executed_price ?? $order->price ?? 0);
            }

            if ($qty <= 0 || $entry <= 0) {
                DB::rollBack();
                return;
            }

            $feeRatePercent = 0.0;
            if ($order->mode === 'live') {
                $feeRatePercent = (float) (
                    \AppSettings\Models\AppSettings::where('key', 'sell_commission_rate')
                        ->value('value') ?? 0
                );
            }

            $principal  = round($entry * $qty, 8);
            $pnl        = round(($closePrice - $entry) * $qty, 8);
            $gross      = round($closePrice * $qty, 8);
            $fee        = round($gross * ($feeRatePercent / 100), 2);
            $net        = max(0, round($gross - $fee, 2));
            $settlement = $net;

            // ✅ تحديد عمود المحفظة حسب العملة والمود
            $currency     = strtoupper($order->currency ?? 'USD');
            $walletColumn = match(true) {
                $order->mode === 'demo' && $currency === 'EGP' => 'amount_demo_egp',
                $order->mode === 'demo'                        => 'amount_demo',
                $currency === 'EGP'                            => 'amount_egp',
                default                                        => 'amount',
            };

            $wallet = Wallet::where('user_id', $order->user_id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id'            => $order->user_id,
                    'amount'             => 0,
                    'amount_egp'         => 0,
                    'amount_demo'        => 0,
                    'amount_demo_egp'    => 0,
                    'wait_withdraw_live' => 0,
                    'wait_withdraw_demo' => 0,
                    'wait_withdraw_egp'  => 0,
                ]);
            }

            $wallet->increment($walletColumn, $settlement);
            $balance = (float) $wallet->fresh()->{$walletColumn};

            $update = [
                'status'         => 'executed',
                'close_price'    => $closePrice,
                'realized_pnl'   => $pnl,
                'unrealized_pnl' => 0,
                // ✅ close_kind صح حسب السبب
                'close_kind'     => match($reason) {
                    'tp'    => 'take_profit',
                    'sl'    => 'stop_loss',
                    default => 'sell',
                },
            ];

            if (Schema::hasColumn('orders', 'closed_at')) {
                $update['closed_at'] = now();
            }
            if (Schema::hasColumn('orders', 'fee_rate_percent')) {
                $update['fee_rate_percent'] = $feeRatePercent;
            }
            if (Schema::hasColumn('orders', 'fee_amount')) {
                $update['fee_amount'] = $fee;
            }

            $order->update($update);
            $gramWeight = (float) ($order->unit_gram_weight ?? 1); // ✅
            $quantity   = (int)   ($order->quantity ?? 1);          // ✅

            $closePricePerBar = round($closePrice * $gramWeight, 2); // ✅
            $entryPerBar      = round($entry * $gramWeight, 2);
            $pnlPerBar        = $quantity > 0 ? round($pnl / $quantity, 2) : 0; // ✅

            // نوع الإغلاق بالعربي
            $reasonAr = match($reason) {
                'tp'    => 'جني أرباح',
                'sl'    => 'وقف خسارة',
                default => 'إغلاق آلي',
            };

            // ✅ note مفصّل بالعربي
            $note =
                "{$reasonAr} ({$reason}) — المنتج #{$order->product_id} | "
                . "الكمية: {$quantity} قطعة ({$qty} جم) | "
                . "سعر الدخول: {$entryPerBar} | "
                . "سعر الإغلاق: {$closePricePerBar} {$currency}/سبيكة | "
                . "الإجمالي: " . number_format($gross, 2) . " | "
                . "العمولة ({$feeRatePercent}%): " . number_format($fee, 2) . " | "
                . "الصافي: " . number_format($net, 2) . " | "
                . "ربح/خسارة: " . number_format($pnl, 2) . " ({$pnlPerBar}/سبيكة) [{$currency}]";

           
            Transaction::create([
                'user_id'     => $order->user_id,
                'credit'      => $settlement,
                'debit'       => 0,
                'balance'     => $balance,
                'type'        => 'sell',
                'amount_type' => $order->mode,
                'currency'    => $currency, 
                'created_by'  => 'system',
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
                'note'        => $note,
            ]);
            
            Log::info("Trade #{$tradeId} auto-closed ($reason)", [
                'product_id'  => $order->product_id,
                'quantity'    => $order->quantity,
                'qty_grams'   => $qty,
                'currency'    => $currency,
                'wallet_col'  => $walletColumn,
                'entry'       => $entry,
                'close'       => $closePrice,
                'gross'       => $gross,
                'fee_rate'    => $feeRatePercent,
                'fee'         => $fee,
                'net'         => $net,
                'pnl'         => $pnl,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CheckOrdersJob closeTradeSystem error: '.$e->getMessage());
        }
    }

    private function broadcastPrices(array $quotes): void
    {
        foreach (['XAU', 'XAG'] as $metal) {
            if (!empty($quotes[$metal]['market'])) {
                
                // Broadcast بالدولار
                $this->broadcastPriceInCurrency($metal, 'USD', $quotes[$metal], 1.0);
                
                // Broadcast بالجنيه المصري
                $egpRate = $this->getExchangeRate('EGP');
                if ($egpRate > 0) {
                    $this->broadcastPriceInCurrency($metal, 'EGP', $quotes[$metal], $egpRate);
                }
            }
        }
    }

    private function getExchangeRate(string $currency): float
    {
        if ($currency === 'USD') {
            return 1.0;
        }
        
        $rate = DB::table('app_settings')
            ->where('key', 'exchange_rate_usd_to_egp')
            ->value('value');
        
        return $rate ? (float) $rate : 50.0;
    }

    private function broadcastPriceInCurrency(string $metal, string $currency, array $quote, float $rate): void
    {
        try {
            broadcast(new \App\Events\PriceUpdated([
                'metal'     => $metal,
                'currency'  => $currency,
                'market'    => round((float) $quote['market'] * $rate, 4),
                'buy'       => round((float) $quote['buy'] * $rate, 4),
                'sell'      => round((float) $quote['sell'] * $rate, 4),
                'timestamp' => now()->toIso8601String(),
            ]));
        } catch (\Throwable $e) {
            Log::warning("Price broadcast failed [$currency]: " . $e->getMessage());
        }
    }
}
