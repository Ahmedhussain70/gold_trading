<?php

namespace App\Modules\APIS;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Traits\TwelveDataApi;
use Illuminate\Support\Facades\Cache;
use Product\Models\Currency;
use Product\Models\Product;
use ProductCategory\Models\ProductCategory;

class ProductController extends Controller
{
    use TwelveDataApi;

    /**
     * TTL (بالثواني) لكاش أسعار المعادن وكاش الـ quotes.
     * ✅ نفس القيم اللي كانت بتتحسب لايف قبل كده، لكن دلوقتي بتتخزن مؤقتًا
     * عشان نقلل عدد النداءات لـ TwelveData بين الـ requests المتقاربة.
     */
    private const PRICES_CACHE_TTL = 30;
    private const QUOTE_CACHE_TTL = 30;

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
        // ✅ إذا تم تمرير رقم القسم، اجلب العملة من القسم
        if (!empty($cat) && empty($currency)) {
            $category = ProductCategory::find($cat);
            if ($category && $category->currency) {
                $currency = strtoupper($category->currency);
            }
        }

        // ✅ إذا لم يتم تحديد العملة، استخدم USD كافتراضي
        $currency = strtoupper($currency ?? 'USD');

        // ✅ اجلب الأسعار مرة واحدة (مع كاش قصير المدة)
        $prices = $this->getPrices();

        $query = Product::query()
            ->with(['category.translations', 'translations'])
            ->latest('id');

        if (!empty($cat)) {
            $query->where('product_category_id', $cat);
        }

        $products = $query->get();

        // ✅ اجلب كل الـ quotes مرة واحدة لكل symbol فريد بدل نداء لكل منتج
        $symbols = $products
            ->map(fn ($p) => $this->resolveMetal($p) . '/' . $currency)
            ->unique()
            ->values()
            ->all();
        $quotes = $this->getQuotesForSymbols($symbols);

        // ✅ مرر الأسعار والـ quotes للـ transform
        $productsArray = $products->map(function ($product) use ($currency, $prices, $quotes) {
            return $this->transformProduct($product, $currency, $prices, $quotes);
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

    /**
     * ✅ Get single product with live prices
     */
    public function show($id, $currency = null)
    {
        $product = Product::with(['category.translations', 'translations'])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => transWord("Product not found"),
                'result'  => null,
            ], 404);
        }

        // ✅ إذا لم يتم تمرير العملة، اجلبها من القسم
        if (empty($currency) && $product->category) {
            $currency = strtoupper($product->category->currency ?? 'USD');
        } else {
            $currency = strtoupper($currency ?? 'USD');
        }

        // جلب الأسعار (مع كاش قصير المدة)
        $prices = $this->getPrices();

        // ✅ نداء واحد فقط لـ quote المنتج ده (بدل ما كان بيتنادى جوه transformProduct مباشرة)
        $metal = $this->resolveMetal($product);
        $symbol = "{$metal}/{$currency}";
        $quotes = $this->getQuotesForSymbols([$symbol]);

        return response()->json([
            'success' => true,
            'message' => transWord("Product"),
            'result'  => $this->transformProduct($product, $currency, $prices, $quotes),
        ], 200);
    }

    /**
     * ✅ Get products grouped by category
     */
    public function grouped($currency = null)
    {
        $currency = strtoupper($currency ?? 'USD');

        // جلب الأسعار مرة واحدة (مع كاش قصير المدة)
        $prices = $this->getPrices();

        $products = Product::with(['category.translations', 'translations'])
            ->orderBy('gram_weight')
            ->get();

        // Group by category
        $grouped = $products->groupBy('product_category_id');

        // ✅ احسب عملة كل قسم مرة واحدة، واجمع كل الـ symbols الفريدة عبر كل الأقسام
        // عشان نجيب الـ quotes كلها بأقل عدد نداءات ممكن
        $groupCurrencies = [];
        $allSymbols = [];

        foreach ($grouped as $categoryId => $items) {
            $category = $items->first()->category;
            $categoryCurrency = $category?->currency ? strtoupper($category->currency) : $currency;
            $groupCurrencies[$categoryId] = $categoryCurrency;

            foreach ($items as $p) {
                $allSymbols[] = $this->resolveMetal($p) . '/' . $categoryCurrency;
            }
        }

        $quotes = $this->getQuotesForSymbols(array_values(array_unique($allSymbols)));

        $result = $grouped->map(function ($items, $categoryId) use ($groupCurrencies, $prices, $quotes) {
            $category = $items->first()->category;

            // ✅ استخدم عملة القسم إذا لم يتم تمرير عملة
            $categoryCurrency = $groupCurrencies[$categoryId];

            $categoryTrans = $category?->translate(app()->getLocale()) ?? $category?->translate('en');
            $categoryName = $categoryTrans?->title ?? 'Unknown';

            return [
                'category_id'   => $categoryId,
                'category_name' => $categoryName,
                'category_currency' => $categoryCurrency, // ✅ إضافة عملة القسم
                'products'      => $items->map(fn ($p) => $this->transformProduct($p, $categoryCurrency, $prices, $quotes))->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => transWord("Products grouped by category"),
            'prices'  => [
                'gold_usd' => $prices['gold_usd'],
                'gold_egp' => $prices['gold_egp'],
                'silver_usd' => $prices['silver_usd'],
                'silver_egp' => $prices['silver_egp'],
            ],
            'result'  => $result,
        ], 200);
    }

    /**
     * ✅ Get all categories
     */
    public function categories()
    {
        $categories = ProductCategory::with('translations')->get();

        $result = $categories->map(function ($category) {
            $trans = $category->translate(app()->getLocale()) ?? $category->translate('en');

            return [
                'id'         => $category->id,
                'name'       => $trans?->title ?? 'Category #' . $category->id,
                'api_symbol' => $category->api_symbol,
                'currency'   => strtoupper($category->currency ?? 'USD'), // ✅ إضافة العملة
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => transWord("Product Categories"),
            'result'  => $result,
        ], 200);
    }

    /**
     * ✅ يحسب أسعار الذهب/الفضة بالعملتين (USD/EGP) مرة واحدة، مع كاش قصير المدة
     * لتقليل نداءات TwelveData بين الـ requests المتقاربة.
     * نفس fallback القديم بالظبط لو حصل استثناء.
     */
    private function getPrices(): array
    {
        return Cache::remember('metal_prices_all', self::PRICES_CACHE_TTL, function () {
            try {
                return [
                    'gold_usd'   => $this->getMetalWithConversion('XAU', 'USD'),
                    'gold_egp'   => $this->getMetalWithConversion('XAU', 'EGP'),
                    'silver_usd' => $this->getMetalWithConversion('XAG', 'USD'),
                    'silver_egp' => $this->getMetalWithConversion('XAG', 'EGP'),
                ];
            } catch (\Throwable $e) {
                return [
                    'gold_usd' => ['market' => null],
                    'gold_egp' => ['market' => null],
                    'silver_usd' => ['market' => null],
                    'silver_egp' => ['market' => null],
                ];
            }
        });
    }

    /**
     * ✅ يحدد رمز المعدن الخاص بالمنتج (نفس المنطق اللي كان جوه transformProduct)
     */
    private function resolveMetal($product): string
    {
        $category = $product->category;

        return $product->metal ?? ($category?->api_symbol ?? 'XAU');
    }

    /**
     * ✅ يجيب quote لكل symbol فريد مرة واحدة بس (بدل نداء API لكل منتج)
     * مع كاش قصير المدة لكل symbol على حدة.
     * @param string[] $symbols
     * @return array<string, array>
     */
    private function getQuotesForSymbols(array $symbols): array
    {
        $quotes = [];

        foreach (array_unique($symbols) as $symbol) {
            if ($symbol === '' || $symbol === null) {
                continue;
            }

            $cacheKey = 'gold_quote_' . str_replace('/', '_', $symbol);

            $quotes[$symbol] = Cache::remember($cacheKey, self::QUOTE_CACHE_TTL, function () use ($symbol) {
                try {
                    return $this->getGoldQuote($symbol) ?? [];
                } catch (\Throwable $e) {
                    return [];
                }
            });
        }

        return $quotes;
    }

    /**
     * ✅ Transform product with live prices
     * - تم حذف lowestPrice و highestPrice
     * - $quotes: خريطة (symbol => quoteData) جاهزة مسبقًا لتقليل نداءات الـ API.
     *   لو الـ symbol مش موجود فيها (حالة نادرة)، بيرجع لنفس السلوك القديم كـ fallback.
     */
    private function transformProduct($product, $currency, $prices, $quotes = [])
    {
        $category = $product->category;
        $metal = $this->resolveMetal($product);
        $symbol = "{$metal}/{$currency}";

        // ✅ استخدم الأسعار الجاهزة بدلاً من API call جديد
        $priceKey = strtolower($metal) === 'xau' ? 'gold' : 'silver';
        $priceKey .= '_' . strtolower($currency);
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

        // Get buy/sell prices from the prices array
        $buyPrice = $prices[$priceKey]['buy'] ?? null;
        $sellPrice = $prices[$priceKey]['sell'] ?? null;

        // ✅ استخدم الـ quote الجاهز لو موجود، وإلا ارجع لنفس السلوك القديم كـ fallback
        $quoteData = $quotes[$symbol] ?? null;
        if ($quoteData === null) {
            $quoteData = [];
            try {
                $quoteData = $this->getGoldQuote($symbol) ?? [];
            } catch (\Throwable $e) {
            }
        }

        // سعر الجرام 24 قيراط
        $gramPrice24 = $marketPrice ? round($marketPrice / 31.1035, 2) : 0;

        // بيانات المنتج
        $karat = (int) ($product->karat ?? 24);
        $gramWeight = (float) ($product->gram_weight ?? 0);
        $karatFactor = max(0, min(24, $karat)) / 24;

        // ✅ سعر القطعة التقريبي (سعر الجرام × العيار × الوزن)
        $approxPiece = $gramWeight > 0 && $gramPrice24 > 0
            ? round($gramPrice24 * $karatFactor * $gramWeight, 2)
            : null;

        // ✅ سعر الشراء للقطعة (buy price)
        $buyGramPrice = $buyPrice ? round($buyPrice / 31.1035, 2) : null;
        $buyPiecePrice = $buyGramPrice && $gramWeight > 0
            ? round($buyGramPrice * $karatFactor * $gramWeight, 2)
            : null;

        // ✅ سعر البيع للقطعة (sell price)
        $sellGramPrice = $sellPrice ? round($sellPrice / 31.1035, 2) : null;
        $sellPiecePrice = $sellGramPrice && $gramWeight > 0
            ? round($sellGramPrice * $karatFactor * $gramWeight, 2)
            : null;

        // أسعار الجرام لكل العيارات
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

        // اسم التصنيف
        $categoryName = null;
        if ($category) {
            $categoryTrans = $category->translate(app()->getLocale()) ?? $category->translate('en');
            $categoryName = $categoryTrans?->title;
        }

        // اسم المنتج
        $productTrans = $product->translate(app()->getLocale()) ?? $product->translate('en');
        $productName = $productTrans?->title ?? 'Product #' . $product->id;

        return array_merge([
            'id' => $product->id,
            'name' => $productName,
            'category' => $categoryName,
            'categoryId' => $product->product_category_id,

            // ✅ تم حذف lowestPrice و highestPrice

            'timestamp' => $product->created_at?->timestamp,

            // Metal info
            'metal' => $metal === 'XAU' ? 'gold' : ($metal === 'XAG' ? 'silver' : $metal),
            'metalSymbol' => $metal,
            'currency' => $currency,
            'symbol' => $symbol,

            // ✅ بيانات المنتج الجديدة
            'karat' => $karat,
            'gramWeight' => $gramWeight,
            'karatFactor' => round($karatFactor, 4),

            // Market price (per ounce)
            'price' => $marketPrice !== null ? number_format($marketPrice, 2) : null,
            'priceRaw' => $marketPrice,

            // ✅ أسعار القطعة الواحدة
            'approxPiecePrice' => $approxPiece,      // سعر السوق
            'buyPiecePrice' => $buyPiecePrice,       // سعر الشراء (للمستخدم)
            'sellPiecePrice' => $sellPiecePrice,     // سعر البيع (للمستخدم)

            // ✅ سعر الجرام حسب عيار المنتج
            'gramPriceByKarat' => $gramPrice24 > 0 ? round($gramPrice24 * $karatFactor, 2) : null,

            // ✅ --- الحقول الجديدة ---
            'manufacturingFee' => (float) $product->manufacturing_fee,

            // ✅ سعر القطعة شامل المصنعية
            'approxPiecePriceWithFee' => $approxPiece !== null
                ? round($approxPiece + (float)$product->manufacturing_fee, 2)
                : null,
            'buyPiecePriceWithFee' => $buyPiecePrice !== null
                ? round($buyPiecePrice + (float)$product->manufacturing_fee, 2)
                : null,
            'sellPiecePriceWithFee' => $sellPiecePrice !== null
                ? round($sellPiecePrice + (float)$product->manufacturing_fee, 2)
                : null,

            // ✅ بيانات الشحن
            'hasDelivery' => (bool) $product->has_delivery,
            'shippingCost' => $product->has_delivery ? (float) $product->shipping_cost : null,

            // ✅ السعر النهائي شامل المصنعية + الشحن (لو في توصيل)
            'totalBuyPrice' => $buyPiecePrice !== null
                ? round(
                    $buyPiecePrice
                    + (float)$product->manufacturing_fee
                    + ($product->has_delivery ? (float)$product->shipping_cost : 0),
                    2
                )
                : null,
            // ✅ ---------------------------

            // Quote data
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