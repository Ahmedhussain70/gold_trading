<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\TwelveDataApi;

class ProductResource extends JsonResource
{
    use TwelveDataApi;

    public function toArray($request)
    {
        // ❌ لا تحط أي check على $this->resource هنا!
        
        // حماية الـ category
        $category = null;
        if (method_exists($this, 'relationLoaded') && $this->relationLoaded('category')) {
            $category = $this->category;
        }
        
        $metal    = $this->metal ?? ($category?->api_symbol ?? 'XAU');
        $currency = strtoupper($request->route('currency') ?? $request->query('currency', 'USD'));
        $symbol   = "{$metal}/{$currency}";

        $marketPrice = null; 
        $quoteData = [];
        
        try {
            $priceData   = $this->getMetalWithConversion($metal, $currency);
            $marketPrice = $priceData['market'] ?? null;
        } catch (\Throwable $e) {}
        
        try {
            $quoteData = $this->getGoldQuote($symbol) ?? [];
        } catch (\Throwable $e) {}

        $gramPrice24 = $marketPrice ? round($marketPrice / 31.1035, 2) : 0;
        $karat       = (int) ($this->karat ?? 24);
        $gramWeight  = (float) ($this->gram_weight ?? 0);
        $karatFactor = max(0, min(24, $karat)) / 24;
        $approxPiece = $gramWeight > 0 && $gramPrice24 > 0
            ? round($gramPrice24 * $karatFactor * $gramWeight, 2)
            : null;

        $multipliers = [
            'priceGram24k' => 1,
            'priceGram22k' => 22/24,
            'priceGram21k' => 21/24,
            'priceGram20k' => 20/24,
            'priceGram18k' => 18/24,
            'priceGram16k' => 16/24,
            'priceGram14k' => 14/24,
            'priceGram10k' => 10/24,
        ];
        
        $grams = [];
        foreach ($multipliers as $k => $m) {
            $grams[$k] = $marketPrice ? round($gramPrice24 * $m, 2) : 0;
        }

        // جيب اسم الـ category
        $categoryName = null;
        if ($category) {
            $categoryTrans = $category->translate(app()->getLocale()) ?? $category->translate('en');
            $categoryName = $categoryTrans?->title;
        }

        // جيب اسم المنتج
        $productTrans = $this->translate(app()->getLocale()) ?? $this->translate('en');
        $productName = $productTrans?->title ?? 'Product #' . $this->id;

        return [
            'id'              => $this->id,
            'name'            => $productName,
            'category'        => $categoryName,
            'lowestPrice'     => (float) ($this->lowest_price ?? 0),
            'highestPrice'    => (float) ($this->highest_price ?? 0),
            'timestamp'       => $this->created_at?->timestamp,
            'metal'           => $metal === 'XAU' ? 'gold' : ($metal === 'XAG' ? 'silver' : $metal),
            'currency'        => $currency,
            'symbol'          => $symbol,
            'price'           => $marketPrice !== null ? number_format($marketPrice, 2) : null,
            'karat'           => $karat,
            'gramWeight'      => $gramWeight,
            'approxPiecePrice'=> $approxPiece,
            'prevClosePrice'  => $quoteData['previous_close'] ?? null,
            'openPrice'       => $quoteData['open'] ?? null,
            'lowPrice'        => $quoteData['low'] ?? null,
            'highPrice'       => $quoteData['high'] ?? null,
            'openTime'        => isset($quoteData['datetime']) ? strtotime($quoteData['datetime']) : null,
            'ch'              => $quoteData['change'] ?? null,
            'chp'             => $quoteData['percent_change'] ?? null,
            'ask'             => $quoteData['ask'] ?? null,
            'bid'             => $quoteData['bid'] ?? null,
            'priceGram24k'    => $grams['priceGram24k'],
            'priceGram22k'    => $grams['priceGram22k'],
            'priceGram21k'    => $grams['priceGram21k'],
            'priceGram20k'    => $grams['priceGram20k'],
            'priceGram18k'    => $grams['priceGram18k'],
            'priceGram16k'    => $grams['priceGram16k'],
            'priceGram14k'    => $grams['priceGram14k'],
            'priceGram10k'    => $grams['priceGram10k'],
        ];
    }
}