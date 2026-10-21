<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Ratchet\Client\connect;

class TwelveDataStreamCommand extends Command
{
    protected $signature = 'trading:stream';
    protected $description = 'Real-time price stream from TwelveData and execute trading engine on each tick';

    public function handle()
    {
        @set_time_limit(0);

        // منع تشغيل أكثر من نسخة
        $lock = Cache::lock('trading:stream:lock', 3600);
        if (!$lock->get()) {
            $this->warn('Stream already running.');
            return Command::SUCCESS;
        }

        $apiKey = config('services.twelvedata.key');
        $endpoint = "wss://ws.twelvedata.com/v1/quotes/price?apikey={$apiKey}";

        $subscribe = json_encode([
            'action' => 'subscribe',
            'params' => [
                'symbols' => 'XAU/USD,XAG/USD'
            ]
        ]);

        $this->info('Connecting to TwelveData stream...');

        // connect($endpoint)->then(function($conn) use ($subscribe, $lock) {
        //     $conn->send($subscribe);

        //     $conn->on('message', function($msg) use ($conn) {
        //         $payload = json_decode((string)$msg, true);
        //         if (!$payload) {
        //             return;
        //         }

        //         // TwelveData غالباً بترجع: symbol / price / timestamp ... إلخ
        //         $symbol = $payload['symbol'] ?? null;
        //         $price  = isset($payload['price']) ? (float)$payload['price'] : null;
        //         $ts     = $payload['timestamp'] ?? time();

        //         if (!$symbol || !$price) {
        //             return;
        //         }

        //         // خزّن آخر سعر (للواجهة أو لأي مكان محتاجه)
        //         Cache::put("td:last:{$symbol}", [
        //             'symbol' => $symbol,
        //             'price'  => $price,
        //             'ts'     => $ts,
        //         ], 10);

        //         // شغّل محرك التنفيذ فوراً
        //         // الأفضل: Service مستقل (TradingEngineService) بدل ما تحشر منطق في الكوماند
        //         // app(\App\Services\TradingEngineService::class)->onTick($symbol, $price, $ts);
        //         try {
        //             app(\App\Services\TradingEngineService::class)->onTick($symbol, $price, $ts);
        //         } catch (\Throwable $e) {
        //             Log::error("TradingEngineService onTick error: " . $e->getMessage(), [
        //                 'symbol' => $symbol,
        //                 'price'  => $price,
        //                 'ts'     => $ts,
        //             ]);
        //         }
        //     });

        //     $conn->on('close', function() use ($lock) {
        //         $lock->release();
        //         Log::warning('TwelveData stream closed.');
        //     });

        // }
        connect($endpoint)->then(function($conn) use ($subscribe, $lock) {
            $conn->send($subscribe);
        
            // تخزين آخر سعر في ميموري الكوماند نفسه
            $lastPrices = [];
        
            $conn->on('message', function($msg) use ($conn, &$lastPrices) {
                $payload = json_decode((string)$msg, true);
                if (!$payload) {
                    return;
                }
        
                $symbol = $payload['symbol'] ?? null;
                $price  = isset($payload['price']) ? (float)$payload['price'] : null;
                $ts     = $payload['timestamp'] ?? time();
        
                if (!$symbol || !$price) {
                    return;
                }
        
                // Throttle مقارنة بالتغير السابق
                $last = $lastPrices[$symbol]['price'] ?? null;
                if ($last !== null && abs($price - $last) < 0.01) {
                    // لو التغير أقل من 0.01 ما تعملش حاجة
                    return;
                }
                $lastPrices[$symbol] = ['price' => $price, 'ts' => $ts];
        
                Cache::put("td:last:{$symbol}", [
                    'symbol' => $symbol,
                    'price'  => $price,
                    'ts'     => $ts,
                ], 10);
        
                try {
                    app(\App\Services\TradingEngineService::class)->onTick($symbol, $price, $ts);
                } catch (\Throwable $e) {
                    Log::error("TradingEngineService onTick error: " . $e->getMessage());
                }
            });
        
        });
        
        
        // , function($e) use ($lock) {
        //     $lock->release();
        //     Log::error('TwelveData stream connect error: ' . $e->getMessage());
        // });

        return Command::SUCCESS;
    }
}
