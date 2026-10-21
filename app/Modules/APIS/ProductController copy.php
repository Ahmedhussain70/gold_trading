<?php

namespace App\Modules\APIS;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Traits\TwelveDataApi;
use Product\Models\Currency;
use Product\Models\Product;
use ProductCategory\Models\ProductCategory;

class ProductController extends Controller
{
    use TwelveDataApi;

    public function currencies()
    {
        $currencies = Currency::query()
            ->get(['code','name'])
            ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name])
            ->values();

        return response()->json([
            'success' => true,
            'message' => transWord("Currencies"),
            'result'  => $currencies,
        ], 200);
    }

public function index($cat = null, $currency = null)
{
    $currency = strtoupper($currency ?? 'USD');
    
    // ✅ اجلب الأسعار مرة واحدة
    $prices = [];
    try {
        $prices['gold_usd']   = $this->getMetalWithConversion('XAU', 'USD');
        $prices['gold_egp']   = $this->getMetalWithConversion('XAU', 'EGP');
        $prices['silver_usd'] = $this->getMetalWithConversion('XAG', 'USD');
        $prices['silver_egp'] = $this->getMetalWithConversion('XAG', 'EGP');
    } catch (\Throwable $e) {
        $prices = [
            'gold_usd' => ['market' => null],
            'gold_egp' => ['market' => null],
            'silver_usd' => ['market' => null],
            'silver_egp' => ['market' => null],
        ];
    }

    $query = Product::query()
        ->with(['category.translations', 'translations'])
        ->latest('id');
    
    if (!empty($cat)) {
        $query->where('product_category_id', $cat);
    }

    $products = $query->get();

    // ✅ مرر الأسعار للـ transform
    $productsArray = $products->map(function($product) use ($currency, $prices) {
        return $this->transformProduct($product, $currency, $prices);
    })->toArray();

    return response()->json([
        'success'  => true,
        'message'  => transWord("Products & Prices"),
        'prices'   => [
            'gold_usd' => $prices['gold_usd'],
            'gold_egp' => $prices['gold_egp'],
            'silver_usd' => $prices['silver_usd'],
            'silver_egp' => $prices['silver_egp'],
        ],
        'products' => $productsArray,
    ], 200);
}

// ✅ عدّل الـ method علشان ياخذ الأسعار الجاهزة
private function transformProduct($product, $currency, $prices)
{
    $category = $product->category;
    $metal = $product->metal ?? ($category?->api_symbol ?? 'XAU');
    $symbol = "{$metal}/{$currency}";

    // ✅ استخدم الأسعار الجاهزة بدلاً من API call جديد
    $priceKey = strtolower($metal) . '_' . strtolower($currency);
    $marketPrice = $prices[$priceKey]['market'] ?? null;
    
    // لو مفيش سعر للعملة المطلوبة، حاول تحسبه
    if (!$marketPrice) {
        if ($metal === 'XAU' && $currency === 'USD') {
            $marketPrice = $prices['gold_usd']['market'] ?? null;
        } elseif ($metal === 'XAU' && $currency === 'EGP') {
            $marketPrice = $prices['gold_egp']['market'] ?? null;
        } elseif ($metal === 'XAG' && $currency === 'USD') {
            $marketPrice = $prices['silver_usd']['market'] ?? null;
        } elseif ($metal === 'XAG' && $currency === 'EGP') {
            $marketPrice = $prices['silver_egp']['market'] ?? null;
        }
    }

    $quoteData = [];
    try {
        $quoteData = $this->getGoldQuote($symbol) ?? [];
    } catch (\Throwable $e) {}

    $gramPrice24 = $marketPrice ? round($marketPrice / 31.1035, 2) : 0;
    $karat = (int) ($product->karat ?? 24);
    $gramWeight = (float) ($product->gram_weight ?? 0);
    $karatFactor = max(0, min(24, $karat)) / 24;
    $approxPiece = $gramWeight > 0 && $gramPrice24 > 0
        ? round($gramPrice24 * $karatFactor * $gramWeight, 2)
        : null;

    $grams = [
        'priceGram24k' => $marketPrice ? round($gramPrice24, 2) : 0,
        'priceGram22k' => $marketPrice ? round($gramPrice24 * 22/24, 2) : 0,
        'priceGram21k' => $marketPrice ? round($gramPrice24 * 21/24, 2) : 0,
        'priceGram20k' => $marketPrice ? round($gramPrice24 * 20/24, 2) : 0,
        'priceGram18k' => $marketPrice ? round($gramPrice24 * 18/24, 2) : 0,
        'priceGram16k' => $marketPrice ? round($gramPrice24 * 16/24, 2) : 0,
        'priceGram14k' => $marketPrice ? round($gramPrice24 * 14/24, 2) : 0,
        'priceGram10k' => $marketPrice ? round($gramPrice24 * 10/24, 2) : 0,
    ];

    $categoryName = null;
    if ($category) {
        $categoryTrans = $category->translate(app()->getLocale()) ?? $category->translate('en');
        $categoryName = $categoryTrans?->title;
    }

    $productTrans = $product->translate(app()->getLocale()) ?? $product->translate('en');
    $productName = $productTrans?->title ?? 'Product #' . $product->id;

    return array_merge([
        'id' => $product->id,
        'name' => $productName,
        'category' => $categoryName,
        'lowestPrice' => (float) $product->lowest_price,
        'highestPrice' => (float) $product->highest_price,
        'timestamp' => $product->created_at?->timestamp,
        'metal' => $metal === 'XAU' ? 'gold' : ($metal === 'XAG' ? 'silver' : $metal),
        'currency' => $currency,
        'symbol' => $symbol,
        'price' => $marketPrice !== null ? number_format($marketPrice, 2) : null,
        'karat' => $karat,
        'gramWeight' => $gramWeight,
        'approxPiecePrice' => $approxPiece,
        'prevClosePrice' => $quoteData['previous_close'] ?? null,
        'openPrice' => $quoteData['open'] ?? null,
        'lowPrice' => $quoteData['low'] ?? null,
        'highPrice' => $quoteData['high'] ?? null,
        'openTime' => isset($quoteData['datetime']) ? strtotime($quoteData['datetime']) : null,
        'ch' => $quoteData['change'] ?? null,
        'chp' => $quoteData['percent_change'] ?? null,
        'ask' => $quoteData['ask'] ?? null,
        'bid' => $quoteData['bid'] ?? null,
    ], $grams);
}

}
