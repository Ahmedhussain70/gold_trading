<?php

namespace App\Traits;

trait TwelveDataApi
{
    protected function tdKey(): string
    {
        return config('services.twelvedata.key', '');
    }
    public function __construct()
    {
        $this->apiKey = $this->tdKey();
    }
    public function getGoldPrice($symbol = 'XAU/USD')
    {
        $apiKey = $this->apiKey;
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
        } else {
            $decoded = json_decode($response, true);

            if (isset($decoded['code']) && $decoded['code'] != 200) {
                \Log::warning("TwelveData API Response Error for $symbol: " . json_encode($decoded));
            } else {
                \Log::info("TwelveData API Success [$symbol]: " . json_encode($decoded));
            }

            return $decoded;
        }
    }


    public function getGoldQuote($symbol = 'XAU/USD')
    {
        $apiKey = $this->apiKey;
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
        } else {
            $decoded = json_decode($response, true);

            if (isset($decoded['code']) && $decoded['code'] != 200) {
                \Log::warning("TwelveData Quote API Response Error for $symbol: " . json_encode($decoded));
            } else {
                \Log::info("TwelveData Quote API Success [$symbol]: " . json_encode($decoded));
            }

            return $decoded;
        }
    }

    // دالة لحساب سعر البيع والشراء مع سبريد
    // spread: نسبة أو رقم ثابت (مثال: 1 أو 2 أو 0.001 = 0.1%)
    public function calculateBuySell($market_price, $spread = 2)
    {
        // spread هنا رقم ثابت. لو عايزها نسبة، عدل السطر اللي تحت
        $buy  = $market_price + $spread;
        $sell = $market_price - $spread;
        return [
            'buy'  => round($buy, 2),
            'sell' => round($sell, 2),
        ];
    }

    public function getMetalWithConversion($metal = 'XAU', $currency = 'USD')
    {
        $symbol = "{$metal}/{$currency}";
        $price_data = $this->getGoldPrice($symbol);

        if (isset($price_data['price'])) {
            $market_price = floatval($price_data['price']);
        } else {
            // تحويل يدوي (لو مثلاً EGP مش مدعوم)
            if ($currency == 'EGP') {
                $usd_data = $this->getGoldPrice("{$metal}/USD");
                $usd_egp = $this->getGoldPrice("USD/EGP");
                if (isset($usd_data['price'], $usd_egp['price'])) {
                    $market_price = floatval($usd_data['price']) * floatval($usd_egp['price']);
                } else {
                    $market_price = null;
                }
            } else {
                $market_price = null;
            }
        }

        if ($market_price) {
            $prices = $this->calculateBuySell($market_price, 2); // عدل الـspread حسب سياستك
            return [
                'market' => round($market_price, 2),
                'buy'    => $prices['buy'],
                'sell'   => $prices['sell'],
            ];
        } else {
            return [
                'market' => null,
                'buy'    => null,
                'sell'   => null,
            ];
        }
    }


    
}
