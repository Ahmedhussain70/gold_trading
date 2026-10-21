<?php

namespace App\Traits;
use Illuminate\Support\Facades\Cache; // ✅ أضف دي
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait TwelveDataApi
{
    // ✅ ثوابت
    private $gramsPerOunce = 31.1035;
    private $defaultSpread = 0.10; // سبريد الجرام

    protected function tdKey(): string
    {
        return config('services.twelvedata.key', '');
    }

    /**
     * ✅ الدالة الأساسية: الحصول على سعر المعدن مع التحويل للعملة المطلوبة
     */
    public function getMetalWithConversion(string $metal, string $currency)
    {
        // الحصول على السعر بالدولار (من الـ API)
        $usdPrice = $this->getMetalPriceUSD($metal);
        
        if (!$usdPrice || empty($usdPrice['market'])) {
            return [
                'market' => null,
                'buy'    => null,
                'sell'   => null,
            ];
        }

        // إذا كانت العملة دولار، إرجاع السعر مباشرة
        if (strtoupper($currency) === 'USD') {
            return $usdPrice;
        }

        // ✅ التحويل للجنيه المصري أو عملة أخرى
        $exchangeRate = $this->getExchangeRate($currency);
        
        return [
            'market' => round($usdPrice['market'] * $exchangeRate, 4),
            'buy'    => round($usdPrice['buy'] * $exchangeRate, 4),
            'sell'   => round($usdPrice['sell'] * $exchangeRate, 4),
        ];
    }

    /**
     * ✅ الحصول على سعر المعدن بالدولار فقط (من الـ API)
     * - يحول من أونصة لجرام
     * - يضيف السبريد
     */
    private function getMetalPriceUSD(string $metal)
    {
        $cacheKey = "metal_price_{$metal}_usd";
        $ttl = 60; // cache for 1 minute

        return Cache::remember($cacheKey, $ttl, function () use ($metal) {
            $symbol = "{$metal}/USD";
            $data = $this->getGoldPrice($symbol);

            if (isset($data['error']) || !isset($data['price'])) {
                Log::warning("Failed to get price for {$symbol}");
                return null;
            }

            $pricePerOunce = (float) $data['price'];

            if ($pricePerOunce <= 0) {
                Log::warning("Invalid price from TwelveData [{$symbol}]: {$pricePerOunce}");
                return null;
            }

            // ✅ تحويل من أونصة لجرام
            $pricePerGram = $pricePerOunce / $this->gramsPerOunce;

            // ✅ حساب أسعار الشراء والبيع (سبريد بالجرام)
            $buy  = round($pricePerGram + $this->defaultSpread, 4);
            $sell = round($pricePerGram - $this->defaultSpread, 4);

            return [
                'market' => round($pricePerGram, 4),
                'buy'    => $buy,
                'sell'   => $sell,
            ];
        });
    }

    /**
     * ✅ الحصول على سعر الصرف من الإعدادات
     */
    private function getExchangeRate(string $currency): float
    {
        $currency = strtoupper($currency);

        if ($currency === 'USD') {
            return 1.0;
        }

        if ($currency === 'EGP') {
            $rate = DB::table('app_settings')
                ->where('key', 'exchange_rate_usd_to_egp')
                ->value('value');

            return $rate ? (float) $rate : 51.0; // fallback to 51
        }

        // للعملات الأخرى في المستقبل
        Log::warning("Unsupported currency: {$currency}");
        return 1.0;
    }


    public function getGoldPrice($symbol = 'XAU/USD')
    {
        $apiKey = $this->tdKey();
        $url = "https://api.twelvedata.com/price?symbol=$symbol&apikey=$apiKey";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            \Log::error("TwelveData API Curl Error: $error");
            return ['error' => $error];
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['code']) && (int)$decoded['code'] !== 200) {
            \Log::warning("TwelveData API Response Error for $symbol: " . json_encode($decoded));
        }

        return $decoded;
    }

    public function getGoldQuote($symbol = 'XAU/USD')
    {
        $apiKey = $this->tdKey();
        $url = "https://api.twelvedata.com/quote?symbol=$symbol&apikey=$apiKey";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            \Log::error("TwelveData Quote API Curl Error: $error");
            return ['error' => $error];
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['code']) && (int)$decoded['code'] !== 200) {
            \Log::warning("TwelveData Quote API Response Error for $symbol: " . json_encode($decoded));
        }

        return $decoded;
    }

    public function calculateBuySell($market_price, $spread = 2)
    {
        $buy  = $market_price + $spread;
        $sell = $market_price - $spread;

        return [
            'buy'  => round($buy, 2),
            'sell' => round($sell, 2),
        ];
    }
}
