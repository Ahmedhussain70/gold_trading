<?php

namespace App\Modules\APIS;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Modules\Orders\Models\Order;
use Product\Models\Product;
use Wallet\Models\Wallet;
use Wallet\Models\Transaction;
use Illuminate\Support\Facades\Schema;
use AppSettings\Models\AppSettings;
use App\Traits\TwelveDataApi;
use App\Services\Trading\OpenPnlService;


class OrderController extends Controller
{
    use TwelveDataApi;

    // =========================================================================
    // ✅ HELPER: تحديد عمود المحفظة حسب العملة والمود
    // =========================================================================
    // private function getWalletColumn(string $currency, string $mode): string
    // {
    //     if ($mode === 'demo') {
    //         return 'amount_demo'; // Demo بدون تحويل عملات
    //     }
    //     return strtoupper($currency) === 'EGP' ? 'amount_egp' : 'amount';
    // }
    private function getWalletColumn(string $currency, string $mode): string
    {
        if ($mode === 'demo') {
            return strtoupper($currency) === 'EGP' ? 'amount_demo_egp' : 'amount_demo';
        }
        return strtoupper($currency) === 'EGP' ? 'amount_egp' : 'amount';
    }

    /**
     * ✅ UPDATED: إنشاء Order مرتبط بمنتج
     * ✅ NEW: currency-aware wallet deduction
     * ✅ NEW: الحجز على sell_when_price للأوردرات المعلقة
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live', 'demo'], true) ? $user->mode : 'demo';

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:1000'],
            'currency'   => ['required', 'string', 'max:10'],
            'open_price' => ['required', 'numeric', 'gt:0'],
            'stop_loss'    => ['nullable', 'numeric', 'gt:0'],
            'take_profit'  => ['nullable', 'numeric', 'gt:0'],
            'sell_when_price' => ['nullable', 'numeric', 'gte:0'],

            'prev_close_price' => ['sometimes', 'nullable', 'numeric'],
            'low_price'        => ['sometimes', 'nullable', 'numeric'],
            'high_price'       => ['sometimes', 'nullable', 'numeric'],
            'open_time'        => ['sometimes', 'nullable', 'date_format:Y-m-d H:i:s'],
            'ch'               => ['sometimes', 'nullable', 'string', 'max:20'],
            'chp'              => ['sometimes', 'nullable', 'string', 'max:20'],
            'ask'              => ['sometimes', 'nullable', 'numeric'],
            'bid'              => ['sometimes', 'nullable', 'numeric'],

            'price_gram_24k' => ['sometimes', 'nullable', 'numeric'],
            'price_gram_22k' => ['sometimes', 'nullable', 'numeric'],
            'price_gram_21k' => ['sometimes', 'nullable', 'numeric'],
            'price_gram_20k' => ['sometimes', 'nullable', 'numeric'],
            'price_gram_18k' => ['sometimes', 'nullable', 'numeric'],
            'price_gram_16k' => ['sometimes', 'nullable', 'numeric'],
            'price_gram_14k' => ['sometimes', 'nullable', 'numeric'],
            'price_gram_10k' => ['sometimes', 'nullable', 'numeric'],
        ]);

        $product = Product::with('category')->find($validated['product_id']);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => transWord("Product not found"),
                'result'  => null,
            ], 404);
        }

        $quantity       = (int) $validated['quantity'];
        $unitGramWeight = (float) $product->gram_weight;
        $unitKarat      = (int) $product->karat;
        $totalGrams     = $unitGramWeight * $quantity;

        // ✅ open_price جاي بسعر الجرام مباشرة
        $openPrice = (float) $validated['open_price'];

        // ✅ TP/SL/sell_when جايين per bar — قسمة على وزن السبيكة
        if ($unitGramWeight > 1) {
            if (!empty($validated['stop_loss'])) {
                $validated['stop_loss'] = round((float)$validated['stop_loss'] / $unitGramWeight, 4);
            }
            if (!empty($validated['take_profit'])) {
                $validated['take_profit'] = round((float)$validated['take_profit'] / $unitGramWeight, 4);
            }
            if (!empty($validated['sell_when_price']) && (float)$validated['sell_when_price'] > 0) {
                $validated['sell_when_price'] = round((float)$validated['sell_when_price'] / $unitGramWeight, 4);
            }
        }

        // ✅ تحديد نوع الأوردر
        $sellWhenPriceRaw = $validated['sell_when_price'] ?? null;
        $sellWhenPrice    = null;
        if ($sellWhenPriceRaw !== null && (float) $sellWhenPriceRaw > 0) {
            $sellWhenPrice = (float) $sellWhenPriceRaw;
        }
        $isFutureOrder = $sellWhenPrice !== null;
        $orderType     = $isFutureOrder ? 'order' : 'trade';
        $orderStatus   = $isFutureOrder ? 'pending' : 'open';

        // ✅ حساب التكلفة — الحجز على السعر المستهدف للأوردر المعلق
        $manufacturingFee = 0.0;
        $shippingCost     = 0.0;

        if ($isFutureOrder) {
            $metalCost = round($sellWhenPrice * $totalGrams, 8); // الحجز على sell_when_price
        } else {
            $metalCost = round($openPrice * $totalGrams, 8);     // الشراء الفوري
        }
        $totalCost = round($metalCost, 2);

        // ✅ العملة وعمود المحفظة
        $orderCurrency = strtoupper($validated['currency']);
        $walletColumn  = $this->getWalletColumn($orderCurrency, $mode);

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id'             => $user->id,
                    'amount'              => 0,
                    'amount_egp'          => 0,
                    'amount_demo'         => 0,
                    'amount_demo_egp'     => 0,
                    'wait_withdraw_live'  => 0,
                    'wait_withdraw_demo'  => 0,
                    'wait_withdraw_egp'   => 0,
                ]);
            }

            // ✅ التحقق من الرصيد
            $currentFunds = (float) $wallet->{$walletColumn};
            if ($totalCost > $currentFunds) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("You don't have enough balance"),
                    'result'  => [
                        'required_amount'   => $totalCost,
                        'available_balance' => $currentFunds,
                        'currency'          => $orderCurrency,
                    ],
                ], 400);
            }

            // ✅ خصم من العمود الصح
            $wallet->decrement($walletColumn, $totalCost);
            $newBalance = (float) $wallet->fresh()->{$walletColumn};

            $productName = $product->title ?? "Product #{$product->id}";

            // ✅ سعر السبيكة للـ note
            $pricePerBar = $isFutureOrder
                ? round($sellWhenPrice * $unitGramWeight, 2)
                : round($openPrice * $unitGramWeight, 2);

            $transactionNote = $isFutureOrder
                ? "حجز {$quantity} قطعة × {$productName} | سعر الحجز: {$pricePerBar} {$orderCurrency} | إجمالي: {$totalCost} {$orderCurrency}"
                : "شراء {$quantity} قطعة × {$productName} | سعر السبيكة: {$pricePerBar} {$orderCurrency} | إجمالي: {$totalCost} {$orderCurrency}";

            Transaction::create([
                'user_id'     => $user->id,
                'credit'      => 0,
                'debit'       => $totalCost,
                'balance'     => $newBalance,
                'type'        => $isFutureOrder ? 'order_reserve' : 'trade_open',
                'amount_type' => $mode,
                'currency'    => $orderCurrency,
                'created_by'  => 'client',
                'note'        => $transactionNote,
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
            ]);

            $orderData = [
                'user_id'   => $user->id,
                'mode'      => $mode,
                'type'      => $orderType,
                'status'    => $orderStatus,

                'product_id'       => $product->id,
                'quantity'         => $quantity,
                'unit_gram_weight' => $unitGramWeight,
                'unit_karat'       => $unitKarat,

                'metal'    => $product->metal ?? ($product->category?->api_symbol ?? 'XAU'),
                'currency' => $orderCurrency,

                'qty' => $totalGrams,
                'open_price' => $openPrice,
                'price'      => $openPrice,

                'stop_loss'       => $validated['stop_loss'] ?? null,
                'take_profit'     => $validated['take_profit'] ?? null,
                'sell_when_price' => $sellWhenPrice,

                'prev_close_price' => $validated['prev_close_price'] ?? null,
                'low_price'        => $validated['low_price'] ?? null,
                'high_price'       => $validated['high_price'] ?? null,
                'open_time'        => $validated['open_time'] ?? null,
                'ch'               => $validated['ch'] ?? null,
                'chp'              => $validated['chp'] ?? null,
                'ask'              => $validated['ask'] ?? null,
                'bid'              => $validated['bid'] ?? null,

                'price_gram_24k' => $validated['price_gram_24k'] ?? null,
                'price_gram_22k' => $validated['price_gram_22k'] ?? null,
                'price_gram_21k' => $validated['price_gram_21k'] ?? null,
                'price_gram_20k' => $validated['price_gram_20k'] ?? null,
                'price_gram_18k' => $validated['price_gram_18k'] ?? null,
                'price_gram_16k' => $validated['price_gram_16k'] ?? null,
                'price_gram_14k' => $validated['price_gram_14k'] ?? null,
                'price_gram_10k' => $validated['price_gram_10k'] ?? null,

                'manufacturing_fee_snapshot' => $manufacturingFee,
                'shipping_cost_snapshot'     => $shippingCost,
                'metal_cost_snapshot'        => $metalCost,
            ];

            if (!$isFutureOrder && Schema::hasColumn('orders', 'entry_price')) {
                $orderData['entry_price'] = $openPrice;
            }

            $order = Order::create($orderData);

            DB::commit();

            $successMessage = $isFutureOrder
                ? transWord("Future order created successfully. Will execute when price reaches :price", [
                    'price' => number_format($sellWhenPrice, 2)
                ])
                : transWord("Trade opened successfully");

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'result'  => new OrderResource($order->fresh()->load('product')),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Order Store Error: ' . $e->getMessage(), [
                'trace'      => $e->getTraceAsString(),
                'user_id'    => $user->id,
                'product_id' => $validated['product_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => transWord("Order failed"),
                'result'  => [
                    'error' => app()->environment('local') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /** الصفقات والأوردرات */
    public function allTrades(Request $request)
    {
        $user = auth()->user();
        $query = Order::with('product')
            ->where('user_id', $user->id)
            ->where('mode', $user->mode)
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('close_kind')) {
            $query->where('close_kind', $request->query('close_kind'));
        }
        if ($request->filled('metal')) {
            $query->where('metal', $request->query('metal'));
        }
        // ✅ NEW: فلتر بالعملة
        if ($request->filled('currency')) {
            $query->where('currency', strtoupper($request->query('currency')));
        }

        $perPage = (int) $request->query('per_page', 0);
        if ($perPage > 0) {
            $perPage = max(1, min($perPage, 100));
            $orders = $query->paginate($perPage);
            return response()->json([
                'success' => true,
                'message' => transWord("Orders"),
                'result'  => OrderResource::collection($orders),
                'meta'    => [
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                    'per_page'     => $orders->perPage(),
                    'total'        => $orders->total(),
                ],
            ], 200);
        }

        $orders = $query->get();
        return response()->json([
            'success' => true,
            'message' => transWord("Orders"),
            'result'  => OrderResource::collection($orders),
        ], 200);
    }

    public function show($id)
    {
        $order = Order::with('product')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Order not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => transWord("Order"),
            'result'  => new OrderResource($order),
        ], 200);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'order_id'        => ['required', 'integer'],
            'stop_loss'       => ['sometimes', 'numeric', 'gt:0'],
            'take_profit'     => ['sometimes', 'numeric', 'gt:0'],
            'sell_when_price' => ['sometimes', 'nullable', 'numeric'],
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Trade not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        $isOpenTrade    = ($order->type === 'trade' && $order->status === 'open');
        $isPendingOrder = ($order->type === 'order' && $order->status === 'pending');

        if (!$isOpenTrade && !$isPendingOrder) {
            return response()->json([
                'success' => false,
                'message' => transWord("Only open trades or pending orders can be updated"),
                'result'  => null,
            ], 400);
        }

        if ($request->has('sell_when_price') && !is_null($request->input('sell_when_price'))) {
            if ((float) $request->input('sell_when_price') <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => transWord("sell_when_price must be greater than 0"),
                    'result'  => null,
                ], 422);
            }
        }

        $data = [];
        foreach (['stop_loss', 'take_profit', 'sell_when_price'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => transWord("No changes provided"),
                'result'  => null,
            ], 422);
        }

        // أضف بعد جلب الـ order وقبل update
        $gramWeight = (float) ($order->unit_gram_weight ?? 1);
        $quantity   = (int)   ($order->quantity ?? 1);
        $totalGrams = $gramWeight * $quantity;

        if ($gramWeight > 1) {
            if (isset($data['stop_loss'])) {
                $data['stop_loss'] = round((float)$data['stop_loss'] / $gramWeight, 4);
            }
            if (isset($data['take_profit'])) {
                $data['take_profit'] = round((float)$data['take_profit'] / $gramWeight, 4);
            }
            if (isset($data['sell_when_price'])) {
                $data['sell_when_price'] = round((float)$data['sell_when_price'] / $gramWeight, 4);
            }
        }


        $order->update($data);

        return response()->json([
            'success' => true,
            'message' => transWord("Trade is updated successfully"),
            'result'  => new OrderResource($order->fresh()->load('product')),
        ], 200);
    }
    /** بيع للتطبيق (إغلاق بالبيع) */
    public function sell(Request $request)
    {
        $request->validate([
            'order_id'   => ['required','integer'],
            'sell_price' => app()->environment(['local','development'])
                            ? ['nullable','numeric','gt:0']
                            : ['prohibited'],
        ]);

        $user = auth()->user();

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Trade not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        if (!($order->type === 'trade' && $order->status === 'open')) {
            return response()->json([
                'success' => false,
                'message' => transWord("Order is not open for selling"),
                'result'  => null,
            ], 400);
        }

        $metal    = $order->metal    ?? 'XAU';
        $currency = $order->currency ?? 'USD';

        $walletColumn = $this->getWalletColumn($currency, $order->mode);

        $feeRatePercent = (float) (AppSettings::where('key', 'sell_commission_rate')->value('value') ?? 0);
        if ($order->mode === 'demo') {
            $feeRatePercent = 0.0;
        }

        $feedAt      = now();
        $feedSource  = 'twelvedata';
        $serverPrice = null;

        try {
            $quote = $this->getMetalWithConversion($metal, $currency);
            if (is_array($quote) && !empty($quote['sell']) && (float)$quote['sell'] > 0) {
                $serverPrice = (float) $quote['sell'];
                $feedSource  = 'twelvedata:conversion';
            }
        } catch (\Throwable $e) {
            \Log::warning("Sell feed error (conversion): ".$e->getMessage());
        }

        if (!$serverPrice) {
            try {
                $raw = $this->getGoldPrice("XAU/USD"); // ← دايماً USD مش EGP
                if (is_array($raw) && isset($raw['price']) && (float)$raw['price'] > 0) {
                    $usdPricePerGram = (float)$raw['price'] / 31.1035;
                    
                    if (strtoupper($currency) === 'EGP') {
                        // ✅ تحويل يدوي لـ EGP
                        $egpRate     = (float) (\AppSettings\Models\AppSettings::where('key', 'exchange_rate_usd_to_egp')->value('value') ?? 51);
                        $serverPrice = round($usdPricePerGram * $egpRate, 4);
                    } else {
                        $serverPrice = round($usdPricePerGram, 4);
                    }
                    
                    $feedSource = 'twelvedata:manual';
                }
            } catch (\Throwable $e) {
                \Log::warning("Sell feed error (fallback): ".$e->getMessage());
            }
        }

        if (app()->environment(['local','development']) && $request->filled('sell_price')) {
            $serverPrice = (float) $request->sell_price;
            $feedSource  = 'manual-dev';
        }

        if (!$serverPrice || $serverPrice <= 0) {
            return response()->json([
                'success' => false,
                'message' => transWord("Live price unavailable. Please try again."),
                'result'  => null,
            ], 503);
        }

        $qty        = (float) $order->qty;
        $entryPrice = (float) ($order->entry_price ?? $order->price);

        // ✅ round على كل الحسابات
        $pnl   = round(($serverPrice - $entryPrice) * $qty, 8);
        $gross = round($serverPrice * $qty, 8);
        $fee   = round($gross * ($feeRatePercent / 100), 2);
        $net   = max(0, round($gross - $fee, 2));

        DB::beginTransaction();
        try {
            $update = [
                'type'                 => 'trade',
                'status'               => 'executed',
                'close_kind'           => 'sell',
                'close_price'          => $serverPrice,
                'realized_pnl'         => $pnl,
                'unrealized_pnl'       => 0,
                'closed_at'            => now(),
                'delivery_status'      => null,
                'delivery_method'      => null,
                'delivery_city'        => null,
                'delivery_phone'       => null,
                'delivery_fee'         => null,
                'delivery_tracking_no' => null,
            ];

            if (Schema::hasColumn('orders', 'close_feed_source')) {
                $update['close_feed_source'] = $feedSource;
            }
            if (Schema::hasColumn('orders', 'close_feed_at')) {
                $update['close_feed_at'] = $feedAt;
            }
            if (Schema::hasColumn('orders', 'fee_rate_percent')) {
                $update['fee_rate_percent'] = $feeRatePercent;
            }
            if (Schema::hasColumn('orders', 'fee_amount')) {
                $update['fee_amount'] = $fee;
            }

            $order->update($update);

            // ✅ first() مع check بدل firstOrFail()
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("Wallet not found"),
                    'result'  => null,
                ], 404);
            }

            $wallet->increment($walletColumn, $net);
            $balance = (float) $wallet->fresh()->{$walletColumn};

            $gramWeight = (float) ($order->unit_gram_weight ?? 1);   // ✅
            $quantity   = (int)   ($order->quantity ?? 1);            // ✅

            $closePricePerBar = round($serverPrice * $gramWeight, 2); // ✅
            $entryPerBar      = round($entryPrice * $gramWeight, 2);  // ✅
            $pnlPerBar        = $quantity > 0 ? round($pnl / $quantity, 2) : 0; // ✅

            
            Transaction::create([
                'user_id'     => $user->id,
                'credit'      => $net,
                'debit'       => 0,
                'balance'     => $balance,
                'type'        => 'sell',
                'amount_type' => $order->mode,
                'currency'    => strtoupper($order->currency ?? 'USD'), // ✅
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
                // 'note'        => transWord(
                //     "Sell closed at :price (:metal/:cur). Gross: :gross, Fee(:rate%): :fee, Net: :net, PnL: :pnl",
                //     [
                //         'price' => number_format($serverPrice, 2),
                //         'metal' => $metal,
                //         'cur'   => $currency,
                //         'gross' => number_format($gross, 2),
                //         'rate'  => rtrim(rtrim(number_format($feeRatePercent, 2), '0'), '.'),
                //         'fee'   => number_format($fee, 2),
                //         'net'   => number_format($net, 2),
                //         'pnl'   => number_format($pnl, 2),
                //     ]
                // ),
                // في Transaction::create()
                'note' => "بيع {$quantity} قطعة | سعر البيع: {$closePricePerBar} {$currency}/سبيكة | عائد: " . number_format($net, 2) . " | ربح/خسارة: " . number_format($pnl, 2) . " {$currency}",

            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => transWord("Trade sold successfully"),
                'result'  => new OrderResource($order->fresh()->load('product')),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Sell Error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => transWord("Sell failed"),
                'result'  => null,
            ], 500);
        }
    }

    /** الصفقات النشطة حسب نوع المعدن (XAU/XAG) */
    public function tradesByMetal(Request $request)
    {
        $request->validate([
            'metal' => ['nullable', Rule::in(['XAU', 'XAG'])],
        ]);

        $user = auth()->user();
        $query = Order::with('product')
            ->where('user_id', $user->id)
            ->where('type', 'trade')
            ->where('mode', $user->mode)
            ->where('status', 'open');

        if ($request->filled('metal')) {
            $query->where('metal', $request->metal);
        }

        $orders = $query->latest('id')->get();

        $summary = [
            'XAU' => [
                'count' => $orders->where('metal', 'XAU')->count(),
                'total_qty' => $orders->where('metal', 'XAU')->sum('qty'),
            ],
            'XAG' => [
                'count' => $orders->where('metal', 'XAG')->count(),
                'total_qty' => $orders->where('metal', 'XAG')->sum('qty'),
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => transWord("Active trades by metal"),
            'result'  => OrderResource::collection($orders),
            'summary' => $summary,
        ], 200);
    }

    public function requestDelivery(Request $request)
    {
        $request->validate([
            'order_id'         => ['required','integer'],
            'delivery_method'  => ['required','string','max:50'],
            'delivery_address' => ['required','string','max:500'],
            'delivery_city'    => ['required','string','max:100'],
            'delivery_phone'   => ['required','string','max:25'],
            'delivery_fee'     => ['prohibited'],
        ]);

        $user  = auth()->user();
        $order = Order::where('id', $request->order_id)
                    ->where('user_id', $user->id)
                    ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Trade not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        if (!($order->type === 'trade' && $order->status === 'open')) {
            return response()->json([
                'success' => false,
                'message' => transWord("Order is not open for delivery"),
                'result'  => null,
            ], 400);
        }

        $walletColumn = $this->getWalletColumn($order->currency ?? 'USD', $order->mode);

        $shippingFee   = (float) (AppSettings::where('key','delivery_shipping_fee')->value('value') ?? 0);
        $stampPerGram  = (float) (AppSettings::where('key','gold_stamp_fee_per_gram')->value('value') ?? 0);
        $makingPerGram = (float) (AppSettings::where('key','gold_making_fee_per_gram')->value('value') ?? 0);

        $grams     = (float) $order->qty;
        $stampFee  = round($stampPerGram  * $grams, 2);
        $makingFee = round($makingPerGram * $grams, 2);
        $totalFees = round($shippingFee + $stampFee + $makingFee, 2);

        DB::beginTransaction();
        try {
            // ✅ first() مع check بدل firstOrFail()
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("Wallet not found"),
                    'result'  => null,
                ], 404);
            }

            $balanceBefore = (float) $wallet->{$walletColumn};

            if ($totalFees > $balanceBefore) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("Insufficient balance to cover delivery & stamp/making fees"),
                    'result'  => [
                        'required' => $totalFees,
                        'balance'  => $balanceBefore,
                    ],
                ], 400);
            }

            $wallet->decrement($walletColumn, $totalFees);
            $balanceAfter = (float) $wallet->fresh()->{$walletColumn};

            $update = [
                'type'                  => 'trade',
                'status'                => 'pending_delivery',
                'close_kind'            => 'delivery',
                'delivery_status'       => 'pending_delivery',
                'delivery_requested_at' => now(),
                'delivery_method'       => $request->delivery_method,
                'address'               => $request->delivery_address,
                'delivery_city'         => $request->delivery_city,
                'delivery_phone'        => $request->delivery_phone,
            ];

            if (Schema::hasColumn('orders', 'delivery_fee')) {
                $update['delivery_fee'] = $shippingFee;
            }
            if (Schema::hasColumn('orders', 'stamp_fee')) {
                $update['stamp_fee'] = $stampFee;
            }
            if (Schema::hasColumn('orders', 'making_fee')) {
                $update['making_fee'] = $makingFee;
            }
            if (Schema::hasColumn('orders', 'delivery_total_fees')) {
                $update['delivery_total_fees'] = $totalFees;
            }

            $order->update($update);

            Transaction::create([
                'user_id'     => $user->id,
                'credit'      => 0,
                'debit'       => $totalFees,
                'balance'     => $balanceAfter,
                'type'        => 'delivery',
                'amount_type' => $order->mode,
                'currency'    => strtoupper($order->currency ?? 'USD'), // ✅
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
                'note'        => transWord("Delivery fees charged. Shipping: :ship, Stamp: :stamp, Making: :making. Total: :total", [
                    'ship'   => number_format($shippingFee, 2),
                    'stamp'  => number_format($stampFee, 2),
                    'making' => number_format($makingFee, 2),
                    'total'  => number_format($totalFees, 2),
                ]),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => transWord("Delivery request submitted"),
                'result'  => new OrderResource($order->fresh()->load('product')),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('RequestDelivery Error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => transWord("Delivery request failed"),
                'result'  => null,
            ], 500);
        }
    }

    public function cancelDeliveryRequest(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'refund_fees' => ['sometimes', 'boolean'],
        ]);

        $user = auth()->user();
        $refundFees = $request->has('refund_fees') ? (bool)$request->refund_fees : true;

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Order not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        $isPendingDelivery =
            ($order->status === 'pending_delivery') ||
            (($order->delivery_status ?? null) === 'pending_delivery');

        if (!$isPendingDelivery) {
            return response()->json([
                'success' => false,
                'message' => transWord("Delivery request cannot be cancelled at this stage"),
                'result'  => null,
            ], 400);
        }

        $deliveryStatus = $order->delivery_status ?? null;
        $blockedStatuses = ['shipped', 'in_transit', 'delivered', 'processing', 'approved'];
        if ($deliveryStatus && in_array($deliveryStatus, $blockedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => transWord("Delivery request already in progress and cannot be cancelled"),
                'result'  => null,
            ], 400);
        }

        // ✅ عمود المحفظة حسب عملة الأوردر
        $walletColumn = $this->getWalletColumn($order->currency ?? 'USD', $order->mode);

        $shippingFee = (float) ($order->delivery_fee ?? 0);
        $stampFee    = (float) ($order->stamp_fee ?? 0);
        $makingFee   = (float) ($order->making_fee ?? 0);

        $totalFees = 0.0;
        if (Schema::hasColumn('orders', 'delivery_total_fees') && (float)$order->delivery_total_fees > 0) {
            $totalFees = (float) $order->delivery_total_fees;
        } else {
            $totalFees = $shippingFee + $stampFee + $makingFee;
        }
        $totalFees = round($totalFees, 2);

        DB::beginTransaction();
        try {
            $balance = null;
            if ($refundFees && $totalFees > 0) {
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

                if (!$wallet) {
                    $wallet = Wallet::create([
                        'user_id'             => $user->id,
                        'amount'              => 0,
                        'amount_egp'          => 0,
                        'amount_demo'         => 0,
                        'amount_demo_egp'     => 0,
                        'wait_withdraw_live'  => 0,
                        'wait_withdraw_demo'  => 0,
                        'wait_withdraw_egp'   => 0,
                    ]);
                }

                // ✅ إرجاع الرسوم لنفس عمود العملة
                $wallet->increment($walletColumn, $totalFees);
                $balance = (float) $wallet->fresh()->{$walletColumn};

                Transaction::create([
                    'user_id'     => $user->id,
                    'credit'      => $totalFees,
                    'debit'       => 0,
                    'balance'     => $balance,
                    'type'        => 'delivery_refund',
                    'amount_type' => $order->mode,
                    'currency'    => strtoupper($order->currency ?? 'USD'), // ✅
                    'created_by'  => 'client',
                    'is_approval' => 1,
                    'approve_at'  => now(),
                    'request_at'  => now(),
                    'note'        => transWord("Delivery request cancelled. Fees refunded: :amt", [
                        'amt' => number_format($totalFees, 2),
                    ]),
                ]);
            }

            $update = [
                'type'            => 'trade',
                'status'          => 'open',
                'close_kind'      => null,
                'delivery_status' => null,
            ];

            if (Schema::hasColumn('orders', 'delivery_requested_at')) {
                $update['delivery_requested_at'] = null;
            }

            $update['delivery_method'] = null;
            $update['delivery_city']   = null;
            $update['delivery_phone']  = null;

            if (Schema::hasColumn('orders', 'address')) {
                $update['address'] = null;
            }
            if (Schema::hasColumn('orders', 'delivery_fee')) {
                $update['delivery_fee'] = null;
            }
            if (Schema::hasColumn('orders', 'stamp_fee')) {
                $update['stamp_fee'] = null;
            }
            if (Schema::hasColumn('orders', 'making_fee')) {
                $update['making_fee'] = null;
            }
            if (Schema::hasColumn('orders', 'delivery_total_fees')) {
                $update['delivery_total_fees'] = null;
            }
            if (Schema::hasColumn('orders', 'delivery_tracking_no')) {
                $update['delivery_tracking_no'] = null;
            }

            $order->update($update);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => transWord("Delivery request cancelled successfully"),
                'result'  => new OrderResource($order->fresh()->load('product')),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('CancelDeliveryRequest Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => transWord("Cancel delivery request failed"),
                'result'  => null,
            ], 500);
        }
    }

    /** Pending delivery trades */
    public function pendingDeliveries(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'metal'    => ['nullable', Rule::in(['XAU', 'XAG'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::with('product')
            ->where('user_id', $user->id)
            ->where('mode', $user->mode)
            ->where('type', 'trade')
            ->where(function ($q) {
                $q->where('status', 'pending_delivery')
                  ->orWhere('delivery_status', 'pending_delivery');
            })
            ->latest('id');

        if (!empty($validated['metal'])) {
            $query->where('metal', $validated['metal']);
        }

        $perPage = (int) ($validated['per_page'] ?? 0);
        if ($perPage > 0) {
            $orders = $query->paginate($perPage);
            return response()->json([
                'success' => true,
                'message' => transWord("Pending Deliveries"),
                'result'  => OrderResource::collection($orders),
                'meta'    => [
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                    'per_page'     => $orders->perPage(),
                    'total'        => $orders->total(),
                ],
            ], 200);
        }

        $orders = $query->get();

        $summary = [
            'XAU' => [
                'count'     => $orders->where('metal', 'XAU')->count(),
                'total_qty' => (float) $orders->where('metal', 'XAU')->sum('qty'),
            ],
            'XAG' => [
                'count'     => $orders->where('metal', 'XAG')->count(),
                'total_qty' => (float) $orders->where('metal', 'XAG')->sum('qty'),
            ],
            'count' => $orders->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => transWord("Pending Deliveries"),
            'result'  => OrderResource::collection($orders),
            'summary' => $summary,
        ], 200);
    }

    public function trades()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        $orders = Order::with('product')
            ->where('user_id', $user->id)
            ->where('type', 'trade')
            ->where('mode', $mode)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => transWord("Trades"),
            'result'  => OrderResource::collection($orders),
        ], 200);
    }

    // public function tradess()
    // {
    //     $openPnlService = app(OpenPnlService::class);
    //     $user = auth()->user();
    //     $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

    //     $orders = Order::with(['product:id,metal,karat,gram_weight'])
    //         ->select([
    //             'id', 'type', 'status', 'mode',
    //             'product_id', 'quantity', 'unit_gram_weight', 'unit_karat',
    //             'metal', 'currency', 'qty',
    //             'open_price', 'price', 'entry_price', 'executed_price',
    //             'stop_loss', 'take_profit', 'sell_when_price',
    //             'realized_pnl', 'unrealized_pnl',
    //             'manufacturing_fee_snapshot', 'shipping_cost_snapshot',
    //             'created_at',
    //         ])
    //         ->where('user_id', $user->id)
    //         ->where('type', 'trade')
    //         ->where('status', 'open')
    //         ->where('mode', $mode)
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     $grouped = $orders->groupBy(function ($order) {
    //         return $order->metal . '_' . $order->currency;
    //     });

    //     $result = $grouped->map(function ($items, $key) use ($openPnlService) { // ✅ أضف هنا
    //         [$metal, $currency] = explode('_', $key);
    //         $metalName = match ($metal) {
    //             'XAU' => transWord('Gold'),
    //             'XAG' => transWord('Silver'),
    //             default => $metal,
    //         };

    //         $ordersData = $items->map(function ($order) use ($openPnlService) { // ✅ أضف هنا
    //             $gramWeight = (float) ($order->unit_gram_weight ?? 1);
    //             $resource   = (new OrderResource($order))->toArray(request());

    //             $resource['open_price_per_bar']      = round((float)$order->open_price * $gramWeight, 2);
    //             $resource['stop_loss_per_bar']       = $order->stop_loss
    //                 ? round((float)$order->stop_loss * $gramWeight, 2) : null;
    //             $resource['take_profit_per_bar']     = $order->take_profit
    //                 ? round((float)$order->take_profit * $gramWeight, 2) : null;
    //             $resource['sell_when_price_per_bar'] = $order->sell_when_price
    //                 ? round((float)$order->sell_when_price * $gramWeight, 2) : null;

    //             $pnl      = $openPnlService->calculateForOrder($order);
    //             $currency = strtoupper($order->currency ?? 'USD');
    //             $quantity = (int) ($order->quantity ?? 1);

    //             $pnlInCurrency = $currency === 'EGP'
    //                 ? $pnl['floating_pnl_egp']
    //                 : $pnl['floating_pnl_usd'];

    //             $pnlPerBar = $quantity > 0
    //                 ? round($pnlInCurrency / $quantity, 2)
    //                 : 0;

    //             $resource['live_pnl'] = [
    //                 'currency'             => $currency,
    //                 'current_price'        => $pnl['current_price'],
    //                 'floating_pnl'         => $pnlInCurrency,
    //                 'floating_pnl_percent' => $pnl['floating_pnl_percent'],
    //                 'floating_pnl_per_bar' => $pnlPerBar,
    //             ];
    //             return $resource;
    //         })->values();

    //         return [
    //             'metal'    => $metal,
    //             'currency' => $currency,
    //             'title'    => $metalName . ' - ' . strtoupper($currency),
    //             'orders'   => $ordersData,
    //         ];
    //     })->values();

    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Trades"),
    //         'result'  => $result,
    //     ], 200);
    // }
    public function tradess()
    {
        $openPnlService = app(OpenPnlService::class);
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        $orders = Order::with(['product:id,metal,karat,gram_weight'])
            ->select([
                'id', 'type', 'status', 'mode',
                'product_id', 'quantity', 'unit_gram_weight', 'unit_karat',
                'metal', 'currency', 'qty',
                'open_price', 'price', 'entry_price', 'executed_price',
                'stop_loss', 'take_profit', 'sell_when_price',
                'realized_pnl', 'unrealized_pnl',
                'manufacturing_fee_snapshot', 'shipping_cost_snapshot',
                'created_at',
            ])
            ->where('user_id', $user->id)
            ->where('type', 'trade')
            ->where('status', 'open')
            ->where('mode', $mode)
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = $orders->groupBy(function ($order) {
            return $order->metal . '_' . $order->currency;
        });

        $result = $grouped->map(function ($items, $key) use ($openPnlService) {
            [$metal, $currency] = explode('_', $key);
            $metalName = match ($metal) {
                'XAU' => transWord('Gold'),
                'XAG' => transWord('Silver'),
                default => $metal,
            };

            $ordersData = $items->map(function ($order) use ($openPnlService) {
                $gramWeight  = (float) ($order->unit_gram_weight ?? 1);
                $quantity    = (int)   ($order->quantity ?? 1);
                $currency    = strtoupper($order->currency ?? 'USD');
                $entryPrice  = (float) ($order->entry_price ?? $order->open_price ?? 0);

                $resource = (new OrderResource($order))->toArray(request());

                // ✅ أسعار السبيكة الواحدة — جاهزة للعرض مباشرة
                $resource['open_price_per_bar']      = round((float)$order->open_price * $gramWeight, 2);
                $resource['entry_price_per_bar']     = round($entryPrice * $gramWeight, 2);
                $resource['stop_loss_per_bar']       = $order->stop_loss
                    ? round((float)$order->stop_loss * $gramWeight, 2) : null;
                $resource['take_profit_per_bar']     = $order->take_profit
                    ? round((float)$order->take_profit * $gramWeight, 2) : null;
                $resource['sell_when_price_per_bar'] = $order->sell_when_price
                    ? round((float)$order->sell_when_price * $gramWeight, 2) : null;

                // ✅ PnL لحظي
                $pnl = $openPnlService->calculateForOrder($order);

                $pnlInCurrency = $currency === 'EGP'
                    ? $pnl['floating_pnl_egp']
                    : $pnl['floating_pnl_usd'];

                $resource['live_pnl'] = [
                    'currency'              => $currency,
                    // ✅ السعر الحالي بسعر السبيكة — للعرض مباشرة
                    'current_price_per_bar' => round((float)$pnl['current_price'] * $gramWeight, 2),
                    // ✅ إجمالي PnL للصفقة كلها
                    'floating_pnl'          => round($pnlInCurrency, 2),
                    // ✅ PnL للسبيكة الواحدة
                    'floating_pnl_per_bar'  => $quantity > 0
                        ? round($pnlInCurrency / $quantity, 2)
                        : 0,
                    'floating_pnl_percent'  => round((float)$pnl['floating_pnl_percent'], 4),
                ];

                return $resource;
            })->values();

            return [
                'metal'    => $metal,
                'currency' => $currency,
                'title'    => $metalName . ' - ' . strtoupper($currency),
                'orders'   => $ordersData,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => transWord("Trades"),
            'result'  => $result,
        ], 200);
    }

    public function orders()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        $orders = Order::with('product')
            ->where('user_id', $user->id)
            ->where('type', 'order')
            ->where('mode', $mode)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => transWord("Orders"),
            'result'  => OrderResource::collection($orders),
        ], 200);
    }

    // public function orderss()
    // {
    //     $user = auth()->user();
    //     $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

    //     $orders = Order::with(['product:id,metal,karat,gram_weight'])
    //         ->select([
    //             'id', 'type', 'status', 'mode',
    //             'product_id', 'quantity', 'unit_gram_weight', 'unit_karat',
    //             'metal', 'currency', 'qty',
    //             'open_price', 'price', 'entry_price',
    //             'stop_loss', 'take_profit', 'sell_when_price',
    //             'realized_pnl', 'unrealized_pnl',
    //             'manufacturing_fee_snapshot', 'shipping_cost_snapshot',
    //             'created_at',
    //         ])
    //         ->where('user_id', $user->id)
    //         ->where('type', 'order')
    //         ->where('mode', $mode)
    //         ->where('status', 'pending')
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     $grouped = $orders->groupBy(function ($order) {
    //         return $order->metal . '_' . ($order->currency ?? 'USD');
    //     });

    //     $result = $grouped->map(function ($items, $key) {
    //         [$metal, $currency] = explode('_', $key);
    //         $metalName = match ($metal) {
    //             'XAU' => transWord('Gold'),
    //             'XAG' => transWord('Silver'),
    //             default => $metal,
    //         };

    //         $ordersData = $items->map(function ($order) {
    //             $gramWeight = (float) ($order->unit_gram_weight ?? 1);
    //             $resource   = (new OrderResource($order))->toArray(request());

    //             $resource['open_price_per_bar']      = round((float)$order->open_price * $gramWeight, 2);
    //             $resource['stop_loss_per_bar']       = $order->stop_loss
    //                 ? round((float)$order->stop_loss * $gramWeight, 2) : null;
    //             $resource['take_profit_per_bar']     = $order->take_profit
    //                 ? round((float)$order->take_profit * $gramWeight, 2) : null;
    //             $resource['sell_when_price_per_bar'] = $order->sell_when_price
    //                 ? round((float)$order->sell_when_price * $gramWeight, 2) : null;

    //             return $resource;
    //         })->values();

    //         return [
    //             'metal'    => $metal,
    //             'currency' => $currency,
    //             'title'    => $metalName . ' - ' . strtoupper($currency),
    //             'orders'   => $ordersData,
    //         ];
    //     })->values();

    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Orders"),
    //         'result'  => $result,
    //     ], 200);
    // }

    public function orderss()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        $orders = Order::with(['product:id,metal,karat,gram_weight'])
            ->select([
                'id', 'type', 'status', 'mode',
                'product_id', 'quantity', 'unit_gram_weight', 'unit_karat',
                'metal', 'currency', 'qty',
                'open_price', 'price', 'entry_price',
                'stop_loss', 'take_profit', 'sell_when_price',
                'realized_pnl', 'unrealized_pnl',
                'manufacturing_fee_snapshot', 'shipping_cost_snapshot',
                'created_at',
            ])
            ->where('user_id', $user->id)
            ->where('type', 'order')
            ->where('mode', $mode)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = $orders->groupBy(function ($order) {
            return $order->metal . '_' . ($order->currency ?? 'USD');
        });

        $result = $grouped->map(function ($items, $key) {
            [$metal, $currency] = explode('_', $key);
            $metalName = match ($metal) {
                'XAU' => transWord('Gold'),
                'XAG' => transWord('Silver'),
                default => $metal,
            };

            $ordersData = $items->map(function ($order) {
                $gramWeight = (float) ($order->unit_gram_weight ?? 1);

                $resource = (new OrderResource($order))->toArray(request());

                // ✅ أسعار السبيكة الواحدة — جاهزة للعرض مباشرة
                $resource['open_price_per_bar']      = round((float)$order->open_price * $gramWeight, 2);
                $resource['stop_loss_per_bar']       = $order->stop_loss
                    ? round((float)$order->stop_loss * $gramWeight, 2) : null;
                $resource['take_profit_per_bar']     = $order->take_profit
                    ? round((float)$order->take_profit * $gramWeight, 2) : null;
                $resource['sell_when_price_per_bar'] = $order->sell_when_price
                    ? round((float)$order->sell_when_price * $gramWeight, 2) : null;

                return $resource;
            })->values();

            return [
                'metal'    => $metal,
                'currency' => $currency,
                'title'    => $metalName . ' - ' . strtoupper($currency),
                'orders'   => $ordersData,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => transWord("Orders"),
            'result'  => $result,
        ], 200);
    }

    /** Close Trade */
    public function closeTrade(Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'integer'],
            'close_price' => app()->environment(['local','development'])
                ? ['nullable', 'numeric', 'gt:0']
                : ['prohibited'],
        ]);

        $user = auth()->user();

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Trade not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        if ($order->type !== 'trade' || $order->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => transWord("Only open trades can be closed"),
                'result'  => null,
            ], 400);
        }

        if ($order->status === 'pending_delivery' || !empty($order->delivery_status)) {
            return response()->json([
                'success' => false,
                'message' => transWord("Trade is in delivery process and cannot be closed"),
                'result'  => null,
            ], 400);
        }

        $qty   = (float) ($order->qty ?? 0);
        $entry = (float) ($order->entry_price ?? $order->executed_price ?? $order->price ?? 0);

        if ($qty <= 0 || $entry <= 0) {
            return response()->json([
                'success' => false,
                'message' => transWord("Invalid trade data"),
                'result'  => null,
            ], 422);
        }

        $metal    = $order->metal    ?? 'XAU';
        $currency = strtoupper($order->currency ?? 'USD');

        // ✅ عمود المحفظة حسب عملة الأوردر
        $walletColumn = $this->getWalletColumn($currency, $order->mode);

        $closePrice = null;

        if (app()->environment(['local','development']) && $request->filled('close_price')) {
            $closePrice = (float) $request->close_price;
        } else {
            $closePrice = $this->getServerSellPrice($metal, $currency);
        }

        if (!$closePrice || $closePrice <= 0) {
            return response()->json([
                'success' => false,
                'message' => transWord("Live price unavailable. Please try again."),
                'result'  => null,
            ], 503);
        }

        $feeRatePercent = 0.0;
        if ($order->mode === 'live') {
            $feeRatePercent = (float) (AppSettings::where('key', 'sell_commission_rate')->value('value') ?? 0);
        }

        $principal  = round($entry * $qty, 8);
        $pnl        = round(($closePrice - $entry) * $qty, 8);
        $gross      = round($closePrice * $qty, 8);
        $fee        = round($gross * ($feeRatePercent / 100), 2);
        $net        = max(0, round($gross - $fee, 2));
        $settlement = $net;

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id'             => $user->id,
                    'amount'              => 0,
                    'amount_egp'          => 0,
                    'amount_demo'         => 0,
                    'amount_demo_egp'     => 0,
                    'wait_withdraw_live'  => 0,
                    'wait_withdraw_demo'  => 0,
                    'wait_withdraw_egp'   => 0,
                ]);
            }

            // ✅ إرجاع العائد لنفس عمود العملة
            $wallet->increment($walletColumn, $settlement);
            $balance = (float) $wallet->fresh()->{$walletColumn};

            $update = [
                'status'         => 'executed',
                'close_kind'     => 'sell',
                'close_price'    => $closePrice,
                'realized_pnl'   => $pnl,
                'unrealized_pnl' => 0,
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

            $gramWeight       = (float) ($order->unit_gram_weight ?? 1);
            $quantity         = (int)   ($order->quantity ?? 1);
            $closePricePerBar = round($closePrice * $gramWeight, 2);
            $entryPerBar      = round($entry * $gramWeight, 2);
            $pnlPerBar        = $quantity > 0 ? round($pnl / $quantity, 2) : 0;

            $note =
                "إغلاق يدوي (manual) — المنتج #{$order->product_id} | "
                . "الكمية: {$quantity} قطعة ({$qty} جم) | "
                . "سعر الدخول: {$entryPerBar} | "
                . "سعر الإغلاق: {$closePricePerBar} {$currency}/سبيكة | "
                . "الإجمالي: " . number_format($gross, 2) . " | "
                . "العمولة ({$feeRatePercent}%): " . number_format($fee, 2) . " | "
                . "الصافي: " . number_format($net, 2) . " | "
                . "ربح/خسارة: " . number_format($pnl, 2) . " ({$pnlPerBar}/سبيكة) [{$currency}]";
            
            Transaction::create([
                'user_id'     => $user->id,
                'credit'      => $settlement,
                'debit'       => 0,
                'balance'     => $balance,
                'type'        => 'sell',
                'amount_type' => $order->mode,
                'currency'    => $currency,
                'created_by'  => 'client',
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
                'note'        => $note,
                // 'note' => "إغلاق يدوي — {$quantity} قطعة | دخول: {$entryPerBar} | خروج: {$closePricePerBar} {$currency}/سبيكة | ربح/خسارة: " . number_format($pnl, 2) . " {$currency}",
                // 'note'        => "Manual close. entry=$entry close=$closePrice gross=$gross fee({$feeRatePercent}%)=$fee net=$net pnl=$pnl [{$currency}]",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => transWord("Trade closed successfully"),
                'result'  => new OrderResource($order->fresh()->load('product')),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('CloseTrade Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => transWord("Close trade failed"),
                'result'  => null,
            ], 500);
        }
    }

    // public function closedTrades(Request $request)
    // {
    //     $user = auth()->user();
    //     $mode = in_array($user->mode, ['live', 'demo'], true) ? $user->mode : 'demo';

    //     $validated = $request->validate([
    //         'metal'      => ['nullable', Rule::in(['XAU', 'XAG'])],
    //         'currency'   => ['nullable', Rule::in(['USD', 'EGP'])], // ✅ NEW
    //         'close_kind' => ['nullable', Rule::in(['sell', 'cancel', 'stop_loss', 'take_profit', 'delivery'])],
    //         'date_from'  => ['nullable', 'date'],
    //         'date_to'    => ['nullable', 'date'],
    //         'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
    //     ]);

    //     $query = Order::with('product')
    //         ->where('user_id', $user->id)
    //         ->where('mode', $mode)
    //         ->where('type', 'trade')
    //         ->where('status', 'executed')
    //         ->latest('closed_at');

    //     if (!empty($validated['metal']))      $query->where('metal', $validated['metal']);
    //     if (!empty($validated['currency']))   $query->where('currency', strtoupper($validated['currency'])); // ✅ NEW
    //     if (!empty($validated['close_kind'])) $query->where('close_kind', $validated['close_kind']);
    //     if (!empty($validated['date_from']))  $query->whereDate('closed_at', '>=', \Carbon\Carbon::parse($validated['date_from'])->toDateString());
    //     if (!empty($validated['date_to']))    $query->whereDate('closed_at', '<=', \Carbon\Carbon::parse($validated['date_to'])->toDateString());

    //     $summaryQuery = clone $query;
    //     $summary = [
    //         'total_count'    => $summaryQuery->count(),
    //         'total_pnl'      => round((float) $summaryQuery->sum('realized_pnl'), 2),
    //         'total_qty'      => round((float) $summaryQuery->sum('qty'), 4),
    //         'by_metal' => [
    //             'XAU' => [
    //                 'count'    => (clone $query)->where('metal', 'XAU')->count(),
    //                 'total_pnl'=> round((float) (clone $query)->where('metal', 'XAU')->sum('realized_pnl'), 2),
    //             ],
    //             'XAG' => [
    //                 'count'    => (clone $query)->where('metal', 'XAG')->count(),
    //                 'total_pnl'=> round((float) (clone $query)->where('metal', 'XAG')->sum('realized_pnl'), 2),
    //             ],
    //         ],
    //         // ✅ NEW
    //         'by_currency' => [
    //             'USD' => (clone $query)->where('currency', 'USD')->count(),
    //             'EGP' => (clone $query)->where('currency', 'EGP')->count(),
    //         ],
    //     ];

    //     $perPage = (int) ($validated['per_page'] ?? 20);
    //     $orders  = $query->paginate($perPage);

    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Closed Trades"),
    //         'result'  => [
    //             'mode'    => $mode,
    //             'summary' => $summary,
    //             'data'    => OrderResource::collection($orders->items()),
    //             'meta'    => [
    //                 'current_page' => $orders->currentPage(),
    //                 'last_page'    => $orders->lastPage(),
    //                 'per_page'     => $orders->perPage(),
    //                 'from'         => $orders->firstItem(),
    //                 'to'           => $orders->lastItem(),
    //                 'total'        => $orders->total(),
    //             ],
    //         ],
    //     ], 200);
    // }

    // public function pendingOrders(Request $request)
    // {
    //     $user = auth()->user();
    //     $mode = in_array($user->mode, ['live', 'demo'], true) ? $user->mode : 'demo';

    //     $validated = $request->validate([
    //         'metal'    => ['nullable', Rule::in(['XAU', 'XAG'])],
    //         'currency' => ['nullable', Rule::in(['USD', 'EGP'])], // ✅ NEW
    //         'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
    //     ]);

    //     $query = Order::with('product')
    //         ->where('user_id', $user->id)
    //         ->where('mode', $mode)
    //         ->where('type', 'order')
    //         ->where('status', 'pending')
    //         ->latest('id');

    //     if (!empty($validated['metal'])) {
    //         $query->where('metal', $validated['metal']);
    //     }

    //     // ✅ NEW: فلتر بالعملة
    //     if (!empty($validated['currency'])) {
    //         $query->where('currency', strtoupper($validated['currency']));
    //     }

    //     // ✅ UPDATED: summary يشمل العملات
    //     $summary = [
    //         'total_count' => (clone $query)->count(),
    //         'by_metal' => [
    //             'XAU' => (clone $query)->where('metal', 'XAU')->count(),
    //             'XAG' => (clone $query)->where('metal', 'XAG')->count(),
    //         ],
    //         'by_currency' => [
    //             'USD' => (clone $query)->where('currency', 'USD')->count(),
    //             'EGP' => (clone $query)->where('currency', 'EGP')->count(),
    //         ],
    //     ];

    //     $perPage = (int) ($validated['per_page'] ?? 0);

    //     if ($perPage > 0) {
    //         $orders = $query->paginate($perPage);
    //         return response()->json([
    //             'success' => true,
    //             'message' => transWord("Pending Orders"),
    //             'result'  => [
    //                 'mode'    => $mode,
    //                 'summary' => $summary,
    //                 'data'    => OrderResource::collection($orders->items()),
    //                 'meta'    => [
    //                     'current_page' => $orders->currentPage(),
    //                     'last_page'    => $orders->lastPage(),
    //                     'per_page'     => $orders->perPage(),
    //                     'total'        => $orders->total(),
    //                 ],
    //             ],
    //         ], 200);
    //     }

    //     $orders = $query->get();

    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Pending Orders"),
    //         'result'  => [
    //             'mode'    => $mode,
    //             'summary' => $summary,
    //             'data'    => OrderResource::collection($orders),
    //         ],
    //     ], 200);
    // }

    public function closedTrades(Request $request)
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live', 'demo'], true) ? $user->mode : 'demo';

        $validated = $request->validate([
            'metal'      => ['nullable', Rule::in(['XAU', 'XAG'])],
            'currency'   => ['nullable', Rule::in(['USD', 'EGP'])],
            'close_kind' => ['nullable', Rule::in(['sell', 'cancel', 'stop_loss', 'take_profit', 'delivery'])],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::with('product')
            ->where('user_id', $user->id)
            ->where('mode', $mode)
            ->where('type', 'trade')
            ->where('status', 'executed')
            ->latest('closed_at');

        if (!empty($validated['metal']))      $query->where('metal', $validated['metal']);
        if (!empty($validated['currency']))   $query->where('currency', strtoupper($validated['currency']));
        if (!empty($validated['close_kind'])) $query->where('close_kind', $validated['close_kind']);
        if (!empty($validated['date_from']))  $query->whereDate('closed_at', '>=', \Carbon\Carbon::parse($validated['date_from'])->toDateString());
        if (!empty($validated['date_to']))    $query->whereDate('closed_at', '<=', \Carbon\Carbon::parse($validated['date_to'])->toDateString());

        $summaryQuery = clone $query;
        $summary = [
            'total_count' => $summaryQuery->count(),
            'total_pnl'   => round((float) $summaryQuery->sum('realized_pnl'), 2),
            'total_qty'   => round((float) $summaryQuery->sum('qty'), 4),
            'by_metal' => [
                'XAU' => [
                    'count'     => (clone $query)->where('metal', 'XAU')->count(),
                    'total_pnl' => round((float) (clone $query)->where('metal', 'XAU')->sum('realized_pnl'), 2),
                ],
                'XAG' => [
                    'count'     => (clone $query)->where('metal', 'XAG')->count(),
                    'total_pnl' => round((float) (clone $query)->where('metal', 'XAG')->sum('realized_pnl'), 2),
                ],
            ],
            'by_currency' => [
                'USD' => (clone $query)->where('currency', 'USD')->count(),
                'EGP' => (clone $query)->where('currency', 'EGP')->count(),
            ],
        ];

        $perPage = (int) ($validated['per_page'] ?? 20);
        $orders  = $query->paginate($perPage);

        // ✅ أضف per_bar fields
        $data = collect($orders->items())->map(function ($order) {
            $gramWeight = (float) ($order->unit_gram_weight ?? 1);
            $entryPrice = (float) ($order->entry_price ?? $order->open_price ?? 0);
            $resource   = (new OrderResource($order))->toArray(request());

            $resource['open_price_per_bar']   = round((float)$order->open_price * $gramWeight, 2);
            $resource['entry_price_per_bar']  = round($entryPrice * $gramWeight, 2);
            $resource['close_price_per_bar']  = $order->close_price
                ? round((float)$order->close_price * $gramWeight, 2) : null;
            $resource['stop_loss_per_bar']    = $order->stop_loss
                ? round((float)$order->stop_loss * $gramWeight, 2) : null;
            $resource['take_profit_per_bar']  = $order->take_profit
                ? round((float)$order->take_profit * $gramWeight, 2) : null;
            $resource['realized_pnl_per_bar'] = $order->realized_pnl && $order->quantity > 0
                ? round((float)$order->realized_pnl / $order->quantity, 2) : null;

            return $resource;
        })->values();

        return response()->json([
            'success' => true,
            'message' => transWord("Closed Trades"),
            'result'  => [
                'mode'    => $mode,
                'summary' => $summary,
                'data'    => $data,
                'meta'    => [
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                    'per_page'     => $orders->perPage(),
                    'from'         => $orders->firstItem(),
                    'to'           => $orders->lastItem(),
                    'total'        => $orders->total(),
                ],
            ],
        ], 200);
    }

    public function pendingOrders(Request $request)
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live', 'demo'], true) ? $user->mode : 'demo';

        $validated = $request->validate([
            'metal'    => ['nullable', Rule::in(['XAU', 'XAG'])],
            'currency' => ['nullable', Rule::in(['USD', 'EGP'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::with('product')
            ->where('user_id', $user->id)
            ->where('mode', $mode)
            ->where('type', 'order')
            ->where('status', 'pending')
            ->latest('id');

        if (!empty($validated['metal'])) {
            $query->where('metal', $validated['metal']);
        }
        if (!empty($validated['currency'])) {
            $query->where('currency', strtoupper($validated['currency']));
        }

        $summary = [
            'total_count' => (clone $query)->count(),
            'by_metal' => [
                'XAU' => (clone $query)->where('metal', 'XAU')->count(),
                'XAG' => (clone $query)->where('metal', 'XAG')->count(),
            ],
            'by_currency' => [
                'USD' => (clone $query)->where('currency', 'USD')->count(),
                'EGP' => (clone $query)->where('currency', 'EGP')->count(),
            ],
        ];

        // ✅ helper لإضافة per_bar
        $addPerBar = function ($orders) {
            return collect($orders)->map(function ($order) {
                $gramWeight = (float) ($order->unit_gram_weight ?? 1);
                $resource   = (new OrderResource($order))->toArray(request());

                $resource['open_price_per_bar']      = round((float)$order->open_price * $gramWeight, 2);
                $resource['stop_loss_per_bar']        = $order->stop_loss
                    ? round((float)$order->stop_loss * $gramWeight, 2) : null;
                $resource['take_profit_per_bar']      = $order->take_profit
                    ? round((float)$order->take_profit * $gramWeight, 2) : null;
                $resource['sell_when_price_per_bar']  = $order->sell_when_price
                    ? round((float)$order->sell_when_price * $gramWeight, 2) : null;

                return $resource;
            })->values();
        };

        $perPage = (int) ($validated['per_page'] ?? 0);

        if ($perPage > 0) {
            $orders = $query->paginate($perPage);
            return response()->json([
                'success' => true,
                'message' => transWord("Pending Orders"),
                'result'  => [
                    'mode'    => $mode,
                    'summary' => $summary,
                    'data'    => $addPerBar($orders->items()),
                    'meta'    => [
                        'current_page' => $orders->currentPage(),
                        'last_page'    => $orders->lastPage(),
                        'per_page'     => $orders->perPage(),
                        'total'        => $orders->total(),
                    ],
                ],
            ], 200);
        }

        $orders = $query->get();

        return response()->json([
            'success' => true,
            'message' => transWord("Pending Orders"),
            'result'  => [
                'mode'    => $mode,
                'summary' => $summary,
                'data'    => $addPerBar($orders),
            ],
        ], 200);
    }

    /** Cancel Order */
    public function cancelOrder(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $user = auth()->user();

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Order not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        if ($order->type !== 'order' || $order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => transWord("Only pending orders can be cancelled"),
                'result'  => null,
            ], 400);
        }

        $walletColumn = $this->getWalletColumn($order->currency ?? 'USD', $order->mode);

        $qty   = (float) ($order->qty ?? 0);
        $price = (float) ($order->price ?? $order->open_price ?? 0);

        // ✅ استخدم الـ snapshot مباشرة مع تقريب مطابق لـ store()
        $refund = round(
            (float)($order->metal_cost_snapshot ?? round($qty * $price, 8))
            + (float)($order->manufacturing_fee_snapshot ?? 0)
            + (float)($order->shipping_cost_snapshot ?? 0),
            2
        );

        DB::beginTransaction();
        try {
            if ($refund > 0) {
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
                if (!$wallet) {
                    $wallet = Wallet::create([
                        'user_id'            => $user->id,
                        'amount'             => 0,
                        'amount_egp'         => 0,
                        'amount_demo'        => 0,
                        'amount_demo_egp'    => 0,
                        'wait_withdraw_live' => 0,
                        'wait_withdraw_demo' => 0,
                        'wait_withdraw_egp'  => 0,
                    ]);
                }

                $wallet->increment($walletColumn, $refund);
                $balance = (float) $wallet->fresh()->{$walletColumn};

                $gramWeight   = (float) ($order->unit_gram_weight ?? 1);
                $quantity     = (int)   ($order->quantity ?? 1);
                $pricePerBar  = round((float)($order->open_price) * $gramWeight, 2);

                

                Transaction::create([
                    'user_id'     => $user->id,
                    'credit'      => $refund,
                    'debit'       => 0,
                    'balance'     => $balance,
                    'type'        => 'order_cancel_refund',
                    'amount_type' => $order->mode,
                    'currency'    => strtoupper($order->currency ?? 'USD'), // ✅
                    'created_by'  => 'client',
                    'is_approval' => 1,
                    'approve_at'  => now(),
                    'request_at'  => now(),
                    'note' => "إلغاء أوردر — {$quantity} قطعة | سعر: {$pricePerBar} {$order->currency}/سبيكة | مُسترد: " . number_format($refund, 2) . " {$order->currency}",
                ]);
            }

            $update = [
                'status'     => 'cancelled',
                'close_kind' => 'cancel',
            ];

            if (Schema::hasColumn('orders', 'cancelled_at')) {
                $update['cancelled_at'] = now();
            }
            if (Schema::hasColumn('orders', 'closed_at')) {
                $update['closed_at'] = now();
            }

            $order->update($update);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => transWord("Order cancelled successfully"),
                'result'  => new OrderResource($order->fresh()->load('product')),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('CancelOrder Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => transWord("Cancel order failed"),
                'result'  => null,
            ], 500);
        }
    }

    public function cancelledOrders(Request $request)
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live', 'demo'], true) ? $user->mode : 'demo';

        $orders = Order::with('product')
            ->where('user_id', $user->id)
            ->where('mode', $mode)
            ->where('status', 'cancelled')
            ->latest('closed_at')
            ->get();

        $data = collect($orders)->map(function ($order) {
            $gramWeight = (float) ($order->unit_gram_weight ?? 1);
            $resource   = (new OrderResource($order))->toArray(request());

            $resource['open_price_per_bar']      = round((float)$order->open_price * $gramWeight, 2);
            $resource['sell_when_price_per_bar'] = $order->sell_when_price
                ? round((float)$order->sell_when_price * $gramWeight, 2) : null;
            $resource['stop_loss_per_bar']       = $order->stop_loss
                ? round((float)$order->stop_loss * $gramWeight, 2) : null;
            $resource['take_profit_per_bar']     = $order->take_profit
                ? round((float)$order->take_profit * $gramWeight, 2) : null;

            return $resource;
        })->values();

        return response()->json([
            'success' => true,
            'message' => transWord("Cancelled Orders"),
            'result'  => [
                'mode'  => $mode,
                'count' => $orders->count(),
                'data'  => $data,
            ],
        ], 200);
    }

    /**
     * ✅ NEW: Portfolio Summary — رصيد الجرامات المفصل
     * يجمع الصفقات المفتوحة حسب المنتج ويحسب القيمة الحالية
     */
    public function portfolioSummary()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live', 'demo'], true) ? $user->mode : 'demo';
 
        $openTrades = Order::with('product')
            ->where('user_id', $user->id)
            ->where('mode', $mode)
            ->where('type', 'trade')
            ->where('status', 'open')
            ->get();
 
        if ($openTrades->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => transWord("Portfolio Summary"),
                'result'  => [
                    'mode'     => $mode,
                    'totals'   => [
                        'total_grams'  => 0,
                        'total_pieces' => 0,
                        'trade_count'  => 0,
                    ],
                    'by_metal'    => [],
                    'by_product'  => [],
                ],
            ], 200);
        }
 
        // ✅ أسعار حية للتقييم
        $prices = [];
        try {
            $prices['XAU_USD'] = $this->getServerSellPrice('XAU', 'USD');
            $prices['XAU_EGP'] = $this->getServerSellPrice('XAU', 'EGP');
            $prices['XAG_USD'] = $this->getServerSellPrice('XAG', 'USD');
            $prices['XAG_EGP'] = $this->getServerSellPrice('XAG', 'EGP');
        } catch (\Throwable $e) {}
 
        // ✅ تجميع حسب المعدن + العملة
        $byMetal = $openTrades->groupBy(function ($order) {
            return $order->metal . '_' . $order->currency;
        })->map(function ($items, $key) use ($prices) {
            [$metal, $currency] = explode('_', $key);
 
            $totalGrams  = round((float) $items->sum('qty'), 4);
            $totalPieces = (int) $items->sum('quantity');
            $totalCost   = round((float) $items->sum(function ($o) {
                return ((float)$o->qty * (float)($o->entry_price ?? $o->open_price ?? $o->price))
                     + (float)($o->manufacturing_fee_snapshot ?? 0)
                     + (float)($o->shipping_cost_snapshot ?? 0);
            }), 2);
 
            // ✅ الصح — getServerSellPrice بترجع per gram مباشرة
            $currentGramPrice = $prices["{$metal}_{$currency}"] ?? null;
            $currentValue     = $currentGramPrice ? round($currentGramPrice * $totalGrams, 2) : null;
            $unrealizedPnl    = ($currentValue !== null) ? round($currentValue - $totalCost, 2) : null;
 
            return [
                'metal'             => $metal,
                'currency'          => $currency,
                'total_grams'       => $totalGrams,
                'total_pieces'      => $totalPieces,
                'trade_count'       => $items->count(),
                'total_cost'        => $totalCost,
                'current_gram_price'=> $currentGramPrice,
                'current_value'     => $currentValue,
                'unrealized_pnl'    => $unrealizedPnl,
            ];
        })->values();
 
        // ✅ تجميع حسب المنتج
        $byProduct = $openTrades->groupBy('product_id')->map(function ($items, $productId) use ($prices) {
            $first   = $items->first();
            $product = $first->product;
            $metal   = $first->metal ?? 'XAU';
            $currency = $first->currency ?? 'USD';
 
            $productName = $product?->title ?? "Product #{$productId}";
            $karat       = (int) ($first->unit_karat ?? $product?->karat ?? 24);
            $gramWeight  = (float) ($first->unit_gram_weight ?? $product?->gram_weight ?? 0);
 
            $totalGrams  = round((float) $items->sum('qty'), 4);
            $totalPieces = (int) $items->sum('quantity');
 
            return [
                'product_id'   => (int) $productId,
                'product_name' => $productName,
                'metal'        => $metal,
                'currency'     => $currency,
                'karat'        => $karat,
                'gram_weight'  => $gramWeight,
                'total_grams'  => $totalGrams,
                'total_pieces' => $totalPieces,
                'trade_count'  => $items->count(),
            ];
        })->values();
 
        // ✅ الإجمالي
        $totals = [
            'total_grams'  => round((float) $openTrades->sum('qty'), 4),
            'total_pieces' => (int) $openTrades->sum('quantity'),
            'trade_count'  => $openTrades->count(),
        ];
 
        return response()->json([
            'success' => true,
            'message' => transWord("Portfolio Summary"),
            'result'  => [
                'mode'       => $mode,
                'totals'     => $totals,
                'by_metal'   => $byMetal,
                'by_product' => $byProduct,
            ],
        ], 200);
    }
 

    /** Helper: سعر البيع من السيرفر */
    private function getServerSellPrice(string $metal, string $currency): ?float
    {
        // المحاولة الأولى
        try {
            $quote = $this->getMetalWithConversion($metal, $currency);
            if (is_array($quote) && !empty($quote['sell']) && (float)$quote['sell'] > 0) {
                return (float) $quote['sell'];
            }
        } catch (\Throwable $e) {
            \Log::warning("Sell feed error: ".$e->getMessage());
        }

        // ✅ المحاولة الثانية — USD دايماً ثم تحويل
        try {
            $raw = $this->getGoldPrice("{$metal}/USD");
            if (is_array($raw) && isset($raw['price']) && (float)$raw['price'] > 0) {
                $usdPricePerGram = (float)$raw['price'] / 31.1035;
                
                if (strtoupper($currency) === 'EGP') {
                    $egpRate = (float) (\AppSettings\Models\AppSettings::where('key', 'exchange_rate_usd_to_egp')->value('value') ?? 51);
                    return round($usdPricePerGram * $egpRate, 4);
                }
                
                return round($usdPricePerGram, 4);
            }
        } catch (\Throwable $e) {
            \Log::warning("Sell raw feed error: ".$e->getMessage());
        }

        return null;
    }
    
    public function getCommissionRate()
    {
        $commissionRate = (float) (AppSettings::where('key', 'sell_commission_rate')->value('value') ?? 0);
        
        return response()->json([
            'success' => true,
            'message' => transWord("Commission Rate"),
            'result'  => [
                'commission_rate' => $commissionRate,
                'commission_rate_percent' => $commissionRate . '%',
            ],
        ], 200);
    }

    public function exchangeRateInfo()
    {
        $rate = AppSettings::where('key', 'exchange_rate_usd_to_egp')->value('value');

        return response()->json([
            'success' => true,
            'message' => transWord("Exchange Rate"),
            'result'  => [
                'from'     => 'USD',
                'to'       => 'EGP',
                'rate'     => $rate ? (float) $rate : 51.0,
                'last_updated' => now()->toDateTimeString(),
            ],
        ], 200);
    }
}