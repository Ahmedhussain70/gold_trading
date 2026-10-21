<?php

namespace App\Modules\APIS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Traits\TwelveDataApi;

class PriceController extends Controller
{
    use TwelveDataApi;

    // GET /api/price?metal=XAU&currency=USD
    public function index(Request $request)
    {
        $metal    = strtoupper($request->query('metal', 'XAU')); // XAU | XAG
        $currency = strtoupper($request->query('currency', 'USD')); // USD | EGP ...

        if (!in_array($metal, ['XAU','XAG'])) {
            return response()->json(['success'=>false,'message'=>'Invalid metal','result'=>null], 422);
        }

        // كاش قصير جدًا (1 ثانية) عشان تحمي الـ API
        $cacheKey = "q:price:{$metal}:{$currency}";
        $data = Cache::remember($cacheKey, now()->addSeconds(1), function () use ($metal, $currency) {
            $src = 'twelvedata';
            $quote = $this->getMetalWithConversion($metal, $currency); // ['market','buy','sell']

            // fallback لو السوق وقع
            if (!is_array($quote) || empty($quote['market'])) {
                $raw = $this->getGoldPrice("{$metal}/{$currency}");
                if (is_array($raw) && isset($raw['price'])) {
                    $market = (float)$raw['price'];
                    $quote = [
                        'market' => round($market, 2),
                        // لو عايز سبريد ثابت 0 خليه مباشرة
                        'buy'    => round($market, 2),
                        'sell'   => round($market, 2),
                    ];
                    $src = 'twelvedata:price';
                } else {
                    return null;
                }
            }

            return [
                'metal'    => $metal,
                'currency' => $currency,
                'market'   => (float)$quote['market'],
                'buy'      => (float)$quote['buy'],
                'sell'     => (float)$quote['sell'],
                'source'   => $src,
                'ts'       => now()->toIso8601String(),
                'ttl'      => 1000, // ms تقريبًا 1 ثانية
            ];
        });

        if (!$data) {
            return response()->json(['success'=>false,'message'=>'Live price unavailable','result'=>null], 503);
        }

        return response()->json(['success'=>true,'message'=>'OK','result'=>$data], 200);
    }

    // SSE: تحديث لحظي بدون polling كثيف (اختياري)
    // افتح: GET /api/price/stream?metal=XAU&currency=USD
    public function stream(Request $request)
    {
        $metal    = strtoupper($request->query('metal', 'XAU'));
        $currency = strtoupper($request->query('currency', 'USD'));

        if (!in_array($metal, ['XAU','XAG'])) {
            return response('Invalid metal', 422);
        }

        @ini_set('zlib.output_compression', 0);
        @ini_set('output_buffering', 'off');
        @ini_set('implicit_flush', 1);
        @set_time_limit(0);

        return response()->stream(function () use ($metal, $currency) {
            while (true) {
                // أعد استخدام نفس منطق index (مع الكاش 1 ثانية)
                $cacheKey = "q:price:{$metal}:{$currency}";
                $data = Cache::remember($cacheKey, now()->addSeconds(1), function () use ($metal, $currency) {
                    $src   = 'twelvedata';
                    $quote = $this->getMetalWithConversion($metal, $currency);
                    if (!is_array($quote) || empty($quote['market'])) {
                        $raw = $this->getGoldPrice("{$metal}/{$currency}");
                        if (is_array($raw) && isset($raw['price'])) {
                            $market = (float)$raw['price'];
                            $quote = [
                                'market' => round($market, 2),
                                'buy'    => round($market, 2),
                                'sell'   => round($market, 2),
                            ];
                            $src = 'twelvedata:price';
                        } else {
                            return null;
                        }
                    }
                    return [
                        'metal'    => $metal,
                        'currency' => $currency,
                        'market'   => (float)$quote['market'],
                        'buy'      => (float)$quote['buy'],
                        'sell'     => (float)$quote['sell'],
                        'source'   => $src,
                        'ts'       => now()->toIso8601String(),
                    ];
                });

                if ($data) {
                    echo "event: price\n";
                    echo 'data: ' . json_encode(['success'=>true,'result'=>$data]) . "\n\n";
                } else {
                    echo "event: price\n";
                    echo 'data: ' . json_encode(['success'=>false,'message'=>'unavailable']) . "\n\n";
                }

                @ob_flush(); @flush();
                sleep(1); // إرسال كل ثانية
            }
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // لو Nginx
        ]);
    }
}
