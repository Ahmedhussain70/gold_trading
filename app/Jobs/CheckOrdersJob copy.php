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

    /** يمنع قتل الجوب لو طول شوية */
    public $timeout = 60;

    /** سبريد ثابت (نفس منطق calculateBuySell عندك) */
    // private float $spread = 2.0;
    /** سبريد ثابت (نفس منطق calculateBuySell عندك) */
    private float $spread = 0.10;  // ← غيّر من 2.0 إلى 0.10
    
    /** أونصة = 31.1035 جرام */
    private float $gramsPerOunce = 31.1035;  // ← أضف السطر ده

    public function handle()
    {
        // 1) منع التداخل (Overlap)
        $lockSeconds = (int) config('trading.lock_seconds', 50);
        $lock = Cache::lock('job:check_orders', $lockSeconds);

        if (!$lock->get()) {
            return;
        }

        try {
            // 2) جلب الأسعار (من Cache) - وبيعمل API call فقط عند انتهاء TTL
            $quotes = $this->getMetalsQuotesCached();

            if (
                empty($quotes['XAU']['market']) &&
                empty($quotes['XAG']['market'])
            ) {
                return;
            }

            // 3) تنفيذ الأوامر المعلقة (Pending Orders -> تدخل Trade Open)
            $this->processPendingOrders($quotes);

            // 4) إغلاق الصفقات المفتوحة حسب TP/SL
            $this->processOpenTrades($quotes);

        } catch (\Throwable $e) {
            Log::error('CheckOrdersJob handle error: ' . $e->getMessage());
        } finally {
            optional($lock)->release();

            // 5) Self-dispatch: تشغيل مستمر بدون Cron
            // $pollSeconds = (int) config('trading.poll_seconds', 2);
            // self::dispatch()->delay(now()->addSeconds(max(1, $pollSeconds)));
        }
    }

    /**
     * IMPORTANT:
     * - الجوب يشتغل كثير (poll_seconds)
     * - لكنه لا يضرب TwelveData إلا عند انتهاء quotes_ttl_seconds
     */
    // private function getMetalsQuotesCached(): array
    // {
    //     $ttl = (int) config('trading.quotes_ttl_seconds', 300); // الافتراضي 5 دقائق
    //     $cacheKey = 'trading:quotes:xau_xag';

    //     return Cache::remember($cacheKey, $ttl, function () {
    //         return $this->fetchMetalsQuotesFromTwelveData();
    //     });
    // }

    private function getMetalsQuotesCached(): array
    {
        $ttl = (int) config('trading.quotes_ttl_seconds', 300);
        $cacheKey = 'trading:quotes:xau_xag';

        $quotes = Cache::remember($cacheKey, $ttl, function () {
            return $this->fetchMetalsQuotesFromTwelveData();
        });

        // Broadcast prices via WebSocket
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

        // foreach (['XAU' => 'XAU/USD', 'XAG' => 'XAG/USD'] as $metal => $symbol) {
        foreach (['XAU' => 'XAU/USD'] as $metal => $symbol) {
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
                
                // حساب السعر مع spread (بالجرام)
                $buy  = round($pricePerGram + $this->spread, 4);
                $sell = round($pricePerGram - $this->spread, 4);

                $result[$metal] = [
                    'market' => round($pricePerGram, 4),  // 4 decimal places
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
     * - type=order
     * - status=pending
     * شرط التنفيذ (Limit Buy): buy_price <= open_price
     */
    private function processPendingOrders(array $quotes): void
    {
        Order::where('type', 'order')
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById(300, function ($orders) use ($quotes) {
                foreach ($orders as $order) {
                    $metal = (string) $order->metal;
                    $buyPrice = (float) ($quotes[$metal]['buy'] ?? 0);
                    if ($buyPrice <= 0) continue;

                    $trigger = (float) ($order->open_price ?? 0);
                    if ($trigger <= 0) continue;

                    if ($buyPrice <= $trigger) {
                        $this->executeEntry($order->id, $buyPrice);
                    }
                }
            });
    }

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

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CheckOrdersJob executeEntry error: '.$e->getMessage());
        }
    }

    /**
     * إغلاق الصفقات المفتوحة فقط:
     * - type=trade
     * - status=open
     * الإغلاق على SELL مع TP/SL
     */
    private function processOpenTrades(array $quotes): void
    {
        Order::where('type', 'trade')
            ->where('status', 'open')
            ->orderBy('id')
            ->chunkById(300, function ($trades) use ($quotes) {
                foreach ($trades as $trade) {

                    // لا تلمس صفقات داخل الشحن
                    if ($trade->status === 'pending_delivery' || !empty($trade->delivery_status)) {
                        continue;
                    }

                    $metal = (string) $trade->metal;
                    $sellPrice = (float) ($quotes[$metal]['sell'] ?? 0);
                    if ($sellPrice <= 0) continue;

                    $tp = (float) ($trade->take_profit ?? 0);
                    $sl = (float) ($trade->stop_loss ?? 0);

                    // بدون TP/SL => تظل مفتوحة (Manual close / Delivery)
                    if ($tp <= 0 && $sl <= 0) continue;

                    if ($tp > 0 && $sellPrice >= $tp) {
                        $this->closeTradeSystem($trade->id, $sellPrice, 'tp');
                        continue;
                    }

                    if ($sl > 0 && $sellPrice <= $sl) {
                        $this->closeTradeSystem($trade->id, $sellPrice, 'sl');
                        continue;
                    }
                }
            });
    }

    /**
     * نموذج (A): خصم كامل قيمة الصفقة عند فتحها
     * عند الإغلاق نعيد principal + pnl للمحفظة
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

            // entry_price إن وجد، وإلا executed_price
            $entry = 0.0;
            if (Schema::hasColumn('orders', 'entry_price') && !empty($order->entry_price)) {
                $entry = (float) $order->entry_price;
            } else {
                $entry = (float) ($order->executed_price ?? 0);
            }

            if ($qty <= 0 || $entry <= 0) {
                DB::rollBack();
                return;
            }

            $principal  = $entry * $qty;
            $pnl        = ($closePrice - $entry) * $qty;    // Long-only
            $settlement = $principal + $pnl;

            $wallet = Wallet::where('user_id', $order->user_id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id'             => $order->user_id,
                    'amount'              => 0,
                    'amount_demo'         => 0,
                    'wait_withdraw_live'  => 0,
                    'wait_withdraw_demo'  => 0,
                ]);
            }

            if ($order->mode === 'live') {
                $wallet->increment('amount', $settlement);
                $balance = (float) $wallet->fresh()->amount;
            } else {
                $wallet->increment('amount_demo', $settlement);
                $balance = (float) $wallet->fresh()->amount_demo;
            }

            // close_kind عندك غالباً ENUM('sell','delivery') => لازم 'sell'
            $update = [
                'status'         => 'executed',
                'close_price'    => $closePrice,
                'realized_pnl'   => $pnl,
                'unrealized_pnl' => 0,
                'close_kind'     => 'sell',
            ];

            if (Schema::hasColumn('orders', 'closed_at')) {
                $update['closed_at'] = now();
            }

            $order->update($update);

            Transaction::create([
                'user_id'     => $order->user_id,
                'credit'      => $settlement,
                'debit'       => 0,
                'balance'     => $balance,
                'type'        => 'sell',           // حركة إغلاق
                'amount_type' => $order->mode,
                'created_by'  => 'system',
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
                'note'        => "Auto close ($reason). entry=$entry close=$closePrice qty=$qty pnl=$pnl",
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CheckOrdersJob closeTradeSystem error: '.$e->getMessage());
        }
    }

    // private function broadcastPrices(array $quotes): void
    // {
    //     foreach (['XAU'] as $metal) {
    //         if (!empty($quotes[$metal]['market'])) {
    //             try {
    //                 broadcast(new \App\Events\PriceUpdated([
    //                     'metal'     => $metal,
    //                     'currency'  => 'USD',
    //                     'market'    => (float) $quotes[$metal]['market'],
    //                     'buy'       => (float) $quotes[$metal]['buy'],
    //                     'sell'      => (float) $quotes[$metal]['sell'],
    //                     'timestamp' => now()->toIso8601String(),
    //                 ]));
    //             } catch (\Throwable $e) {
    //                 Log::warning('Price broadcast failed: ' . $e->getMessage());
    //             }
    //         }
    //     }
    // }

    private function broadcastPrices(array $quotes): void
    {
        foreach (['XAU'] as $metal) {
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
        
        // قراءة من database
        $rate = DB::table('app_settings')
            ->where('key', 'exchange_rate_usd_to_egp')
            ->value('value');
        
        return $rate ? (float) $rate : 50.0; // Default 50
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
