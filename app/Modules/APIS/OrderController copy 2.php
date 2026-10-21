<?php

namespace App\Modules\APIS;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Modules\Orders\Models\Order;
use Wallet\Models\Wallet;
use Wallet\Models\Transaction;
use Illuminate\Support\Facades\Schema;
use AppSettings\Models\AppSettings;
use App\Traits\TwelveDataApi;


class OrderController extends Controller
{
    use TwelveDataApi;
    /**
     * ✅ NEW: إنشاء Order معلّق (type=order, status=pending)
     * - المستخدم يحدد open_price (سعر الدخول)
     * - نموذج (A): نحجز/نخصم (open_price * qty) وقت إنشاء الأوردر
     * - التنفيذ الفعلي وتحويله لـ trade يتم داخل CheckOrdersJob عند تحقق السعر
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live', 'demo'], true) ? $user->mode : 'demo';

        $validated = $request->validate([
            'metal'    => ['required', Rule::in(['XAU', 'XAG'])],
            'currency' => ['required', 'string', 'max:10'],

            // ✅ سعر الدخول لازم من العميل
            'open_price' => ['required', 'numeric', 'gt:0'],

            'qty'          => ['required', 'numeric', 'gt:0'],
            'stop_loss'    => ['nullable', 'numeric', 'gt:0'],
            'take_profit'  => ['nullable', 'numeric', 'gt:0'],

            // Snapshot fields (اختياري)
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

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id'             => $user->id,
                    'amount'              => 0,
                    'amount_demo'         => 0,
                    'wait_withdraw_live'  => 0,
                    'wait_withdraw_demo'  => 0,
                ]);
            }

            $qty       = (float) $validated['qty'];
            $openPrice = (float) $validated['open_price'];

            // ✅ نموذج (A): حجز/خصم كامل قيمة الأوردر عند سعر الدخول (Limit)
            $reserved  = round($openPrice * $qty, 8);

            $currentFunds = $mode === 'live' ? (float) $wallet->amount : (float) $wallet->amount_demo;
            if ($reserved > $currentFunds) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("You don't have enough balance"),
                    'result'  => null,
                ], 400);
            }

            if ($mode === 'live') {
                $wallet->decrement('amount', $reserved);
                $newBalance = (float) $wallet->fresh()->amount;
            } else {
                $wallet->decrement('amount_demo', $reserved);
                $newBalance = (float) $wallet->fresh()->amount_demo;
            }

            Transaction::create([
                'user_id'     => $user->id,
                'credit'      => 0,
                'debit'       => $reserved,
                'balance'     => $newBalance,
                'type'        => 'order_reserve', // ✅ حجز أوردر
                'amount_type' => $mode,
                'created_by'  => 'client',
                'note'        => transWord("Order placed. Funds reserved."),
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
            ]);

            $orderData = [
                'user_id'   => $user->id,
                'mode'      => $mode,
                'type'      => 'order',
                'status'    => 'pending',

                'metal'     => $validated['metal'],
                'currency'  => $validated['currency'],

                'qty'        => $qty,
                'open_price' => $openPrice, // ✅ سعر الدخول

                'stop_loss'   => $validated['stop_loss'] ?? null,
                'take_profit' => $validated['take_profit'] ?? null,

                // نحتفظ بسعر الحجز/الخصم وقت إنشاء الأوردر (عشان الإلغاء يرجّع نفس الحجز)
                'price' => $openPrice,

                // Snapshots (اختياري)
                'prev_close_price' => $validated['prev_close_price'] ?? null,
                'low_price'        => $validated['low_price'] ?? null,
                'high_price'       => $validated['high_price'] ?? null,
                'open_time'        => $validated['open_time'] ?? null,
                'ch'               => $validated['ch'] ?? null,
                'chp'              => $validated['chp'] ?? null,
                'ask'              => $validated['ask'] ?? null,
                'bid'              => $validated['bid'] ?? null,

                'price_gram_24k'   => $validated['price_gram_24k'] ?? null,
                'price_gram_22k'   => $validated['price_gram_22k'] ?? null,
                'price_gram_21k'   => $validated['price_gram_21k'] ?? null,
                'price_gram_20k'   => $validated['price_gram_20k'] ?? null,
                'price_gram_18k'   => $validated['price_gram_18k'] ?? null,
                'price_gram_16k'   => $validated['price_gram_16k'] ?? null,
                'price_gram_14k'   => $validated['price_gram_14k'] ?? null,
                'price_gram_10k'   => $validated['price_gram_10k'] ?? null,
            ];

            $order = Order::create($orderData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => transWord("Order created successfully"),
                'result'  => new OrderResource($order->fresh()),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Order Store Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => transWord("Order failed"),
                'result'  => null,
            ], 500);
        }
    }

    /** الصفقات المفتوحة (trade + open) */
    public function allTrades(Request $request)
    {
        $user = auth()->user();
        $query = Order::where('user_id', $user->id)
            ->where('mode', $user->mode)  // ✅
            ->latest('id');

        // (اختياري) فلاتر بالكوِيري سترنج: ?status=executed&kind=sell&type=trade
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('type')) {      // trade | order
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('close_kind')) { // sell | delivery
            $query->where('close_kind', $request->query('close_kind'));
        }

        // (اختياري) Pagination: ?per_page=20
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

        // بدون Pagination
        $orders = $query->get();
        return response()->json([
            'success' => true,
            'message' => transWord("Orders"),
            'result'  => OrderResource::collection($orders),
        ], 200);
    }

    /** الصفقات المفتوحة (trade + open) */
    // public function trades()
    // {
    //     $user = auth()->user();
    //     $orders = Order::where('user_id', $user->id)
    //         ->where('type', 'trade')
    //         ->where('status', 'open')
    //         ->where('mode', $user->mode)
    //         ->latest('id')
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Active trades"),
    //         'result'  => OrderResource::collection($orders),
    //     ], 200);
    // }

    public function show($id)
    {
        $order = Order::where('id', $id)
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

    /** تحديث أوردر مفتوح فقط */
    public function update(Request $request)
    {
        // 1) Validation
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],

            // Optional: لو اتبعتت لازم > 0
            'stop_loss'   => ['sometimes', 'numeric', 'gt:0'],
            'take_profit' => ['sometimes', 'numeric', 'gt:0'],

            // Optional: مسموح null لمسح القيمة
            // (شرط >0 هنعمله يدويًا فقط لو مش null)
            'sell_when_price' => ['sometimes', 'nullable', 'numeric'],
        ]);

        // 2) Fetch order + ownership
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

        // 3) Guard: only open trades
        if ($order->type !== 'trade' || $order->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => transWord("Only open trades can be updated"),
                'result'  => null,
            ], 400);
        }

        // 4) Extra strict rule: sell_when_price must be > 0 when provided and not null
        if ($request->has('sell_when_price') && !is_null($request->input('sell_when_price'))) {
            if ((float) $request->input('sell_when_price') <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => transWord("sell_when_price must be greater than 0"),
                    'result'  => null,
                ], 422);
            }
        }

        // 5) Build update payload based on "sent fields" (supports null clearing)
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

        // 6) Update
        $order->update($data);

        return response()->json([
            'success' => true,
            'message' => transWord("Trade is updated successfully"),
            'result'  => new OrderResource($order->fresh()),
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

        // ===== 1) إعدادات الرمز والعملة والعمولة =====
        $metal    = $order->metal    ?? 'XAU';
        $currency = $order->currency ?? 'USD';

        // نسبة عمولة البيع (0–100)
        $feeRatePercent = (float) (AppSettings::where('key', 'sell_commission_rate')->value('value') ?? 0);
        if ($order->mode === 'demo') {
            $feeRatePercent = 0.0; // لا عمولة في الديمو
        }

        // ===== 2) جلب السعر من المزود =====
        $feedAt      = now();
        $feedSource  = 'twelvedata';
        $serverPrice = null;

        try {
            // ترجع ['market','buy','sell']
            $quote = $this->getMetalWithConversion($metal, $currency);
            if (is_array($quote) && !empty($quote['sell'])) {
                $serverPrice = (float) $quote['sell']; // سعر بيع بعد أي معالجة داخلية في التريد
            }
        } catch (\Throwable $e) {
            \Log::warning("Sell feed error: ".$e->getMessage());
        }

        // فالباك: استخدم price خام بدون سبريد
        if (!$serverPrice) {
            $raw = $this->getGoldPrice("{$metal}/{$currency}");
            if (is_array($raw) && isset($raw['price']) && (float)$raw['price'] > 0) {
                $serverPrice = (float) $raw['price']; // بدون سبريد
                $feedSource  = 'twelvedata:price';
            }
        }

        // في بيئة التطوير فقط اسمح بتمرير السعر يدويًا
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

        // ===== 3) الحسابات المالية =====
        $qty        = (float) $order->qty;
        $entryPrice = (float) ($order->entry_price ?? $order->price);

        $pnl   = ($serverPrice - $entryPrice) * $qty;     // ربح/خسارة الصفقة
        $gross = $serverPrice * $qty;                     // إجمالي المتحصل من البيع
        $fee   = round($gross * ($feeRatePercent / 100), 2); // عمولة التطبيق
        $net   = max(0, round($gross - $fee, 2));         // صافي المبلغ للمستخدم

        DB::beginTransaction();
        try {
            // حقول التحديث الأساسية
            $update = [
                'type'             => 'trade',
                'status'           => 'executed',
                'close_kind'       => 'sell',
                'close_price'      => $serverPrice,
                'realized_pnl'     => $pnl,
                'unrealized_pnl'   => 0,
                'closed_at'        => now(),

                // تنظيف بيانات التسليم
                'delivery_status'      => null,
                'delivery_method'      => null,
                'delivery_city'        => null,
                'delivery_phone'       => null,
                'delivery_fee'         => null,
                'delivery_tracking_no' => null,
            ];

            // أضف حقول مصدر السعر/وقته فقط إذا الأعمدة موجودة
            if (Schema::hasColumn('orders', 'close_feed_source')) {
                $update['close_feed_source'] = $feedSource;
            }
            if (Schema::hasColumn('orders', 'close_feed_at')) {
                $update['close_feed_at'] = $feedAt;
            }

            // (اختياري) لو عندك أعمدة لتوثيق العمولة
            if (Schema::hasColumn('orders', 'fee_rate_percent')) {
                $update['fee_rate_percent'] = $feeRatePercent;
            }
            if (Schema::hasColumn('orders', 'fee_amount')) {
                $update['fee_amount'] = $fee;
            }

            $order->update($update);

            // تحديث المحفظة بصافي المبلغ
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($order->mode === 'live') {
                $wallet->increment('amount', $net);
                $balance = $wallet->fresh()->amount;
            } else {
                $wallet->increment('amount_demo', $net);
                $balance = $wallet->fresh()->amount_demo;
            }

            // تسجيل الحركة
            Transaction::create([
                'user_id'     => $user->id,
                'credit'      => $net,
                'debit'       => 0,
                'balance'     => $balance,
                'type'        => 'sell',
                'amount_type' => $order->mode,
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
                'note'        => transWord(
                    "Sell closed at :price (:metal/:cur). Gross: :gross, Fee(:rate%): :fee, Net: :net, PnL: :pnl",
                    [
                        'price' => number_format($serverPrice, 2),
                        'metal' => $metal,
                        'cur'   => $currency,
                        'gross' => number_format($gross, 2),
                        'rate'  => rtrim(rtrim(number_format($feeRatePercent, 2), '0'), '.'),
                        'fee'   => number_format($fee, 2),
                        'net'   => number_format($net, 2),
                        'pnl'   => number_format($pnl, 2),
                    ]
                ),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => transWord("Trade sold successfully"),
                'result'  => new OrderResource($order->fresh()),
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


    public function requestDelivery(Request $request)
    {
        $request->validate([
            'order_id'         => ['required','integer'],
            'delivery_method'  => ['required','string','max:50'],
            'delivery_address' => ['required','string','max:500'],
            'delivery_city'    => ['required','string','max:100'],
            'delivery_phone'   => ['required','string','max:25'],
            // ما نخدش fee من العميل – تتحدد من الإعدادات
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

        // === 1) قراءة الرسوم من الإعدادات ===
        $shippingFee        = (float) (AppSettings::where('key','delivery_shipping_fee')->value('value') ?? 0);
        $stampPerGram       = (float) (AppSettings::where('key','gold_stamp_fee_per_gram')->value('value') ?? 0);
        $makingPerGram      = (float) (AppSettings::where('key','gold_making_fee_per_gram')->value('value') ?? 0);

        $grams = (float) $order->qty; // بافتراض أن qty بالجرام عندك
        $stampFee  = round($stampPerGram  * $grams, 2);
        $makingFee = round($makingPerGram * $grams, 2);
        $totalFees = round($shippingFee + $stampFee + $makingFee, 2);

        DB::beginTransaction();
        try {
            // === 2) خصم الرسوم من المحفظة مع قفل الصف ===
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = $order->mode === 'live' ? (float)$wallet->amount : (float)$wallet->amount_demo;

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

            if ($order->mode === 'live') {
                $wallet->decrement('amount', $totalFees);
                $balanceAfter = $wallet->fresh()->amount;
            } else {
                $wallet->decrement('amount_demo', $totalFees);
                $balanceAfter = $wallet->fresh()->amount_demo;
            }

            // === 3) تحويل الأوردر لتسليم + توثيق الرسوم (لو الأعمدة موجودة) ===
            $update = [
                'type'   => 'trade',
                'status' => 'pending_delivery',     // أو delivery_pending
                'close_kind' => 'delivery',
                'delivery_status' => 'pending_delivery',
                'delivery_requested_at' => now(),
                'delivery_method'       => $request->delivery_method,
                'address'               => $request->delivery_address,
                'delivery_city'         => $request->delivery_city,
                'delivery_phone'        => $request->delivery_phone,
            ];

            // عمود delivery_fee موجود عندك — نسجّل فيه الشحن فقط
            if (Schema::hasColumn('orders', 'delivery_fee')) {
                $update['delivery_fee'] = $shippingFee;
            }
            // لو عندك أعمدة إضافية هنضيفها (اختيارية)
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

            // === 4) تسجيل حركة المحفظة ===
            Transaction::create([
                'user_id'     => $user->id,
                'credit'      => 0,
                'debit'       => $totalFees,
                'balance'     => $balanceAfter,
                'type'        => 'delivery',       // أو delivery_fee
                'amount_type' => $order->mode,
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
                'result' => new OrderResource($order->fresh()),
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

    /** الصفقات النشطة حسب نوع المعدن (XAU/XAG) */
    public function tradesByMetal(Request $request)
    {
        $request->validate([
            'metal' => ['nullable', Rule::in(['XAU', 'XAG'])],
        ]);

        $user = auth()->user();
        $query = Order::where('user_id', $user->id)
            ->where('type', 'trade')
            ->where('mode', $user->mode)
            ->where('status', 'open');

        // فلترة حسب المعدن لو تم تمريره
        if ($request->filled('metal')) {
            $query->where('metal', $request->metal);
        }

        $orders = $query->latest('id')->get();

        // تجميع حسب المعدن للإحصائيات
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

    // public function cancelTrade(Request $request)
    // {
    //     $validated = $request->validate([
    //         'order_id' => ['required', 'integer'],
    //     ]);

    //     $user = auth()->user();

    //     // Fetch order + ownership
    //     $order = Order::where('id', $validated['order_id'])
    //         ->where('user_id', $user->id)
    //         ->first();

    //     if (!$order) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => transWord("Trade not found or unauthorized"),
    //             'result'  => null,
    //         ], 404);
    //     }

    //     // Only open trades can be cancelled
    //     if ($order->type !== 'trade' || $order->status !== 'open') {
    //         return response()->json([
    //             'success' => false,
    //             'message' => transWord("Only open trades can be cancelled"),
    //             'result'  => null,
    //         ], 400);
    //     }

    //     // Compute refundable amount = entry_price * qty (same logic used at store())
    //     $qty        = (float) $order->qty;
    //     $entryPrice = (float) ($order->entry_price ?? $order->price ?? 0);
    //     $refund     = $entryPrice * $qty;

    //     if ($refund <= 0) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => transWord("Invalid refund amount"),
    //             'result'  => null,
    //         ], 422);
    //     }

    //     DB::beginTransaction();
    //     try {
    //         // Lock wallet row
    //         $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

    //         if (!$wallet) {
    //             // If you always create wallet in store, this should rarely happen
    //             $wallet = Wallet::create([
    //                 'user_id'             => $user->id,
    //                 'amount'              => 0,
    //                 'amount_demo'         => 0,
    //                 'wait_withdraw_live'  => 0,
    //                 'wait_withdraw_demo'  => 0,
    //             ]);
    //         }

    //         // Refund to correct balance column
    //         if ($order->mode === 'live') {
    //             $wallet->increment('amount', $refund);
    //             $balance = (float) $wallet->fresh()->amount;
    //         } else {
    //             $wallet->increment('amount_demo', $refund);
    //             $balance = (float) $wallet->fresh()->amount_demo;
    //         }

    //         // Mark order as cancelled (do NOT hard-delete)
    //         $update = [
    //             'status'         => 'cancelled',
    //             'close_kind'     => 'cancel',
    //             'realized_pnl'   => 0,
    //             'unrealized_pnl' => 0,
    //         ];

    //         // Prefer having a closed_at column if exists
    //         if (Schema::hasColumn('orders', 'closed_at')) {
    //             $update['closed_at'] = now();
    //         }

    //         // Optional: keep as trade or convert to order for history.
    //         // I recommend keeping type=trade to preserve original meaning.
    //         // If you want it in "orders report" then set 'type' => 'order'
    //         // $update['type'] = 'order';

    //         $order->update($update);

    //         // Log transaction
    //         Transaction::create([
    //             'user_id'     => $user->id,
    //             'credit'      => $refund,
    //             'debit'       => 0,
    //             'balance'     => $balance,
    //             'type'        => 'cancel_trade',      // add it to your allowed types if needed
    //             'amount_type' => $order->mode,        // live/demo
    //             'created_by'  => 'client',
    //             'is_approval' => 1,
    //             'approve_at'  => now(),
    //             'request_at'  => now(),
    //             'note'        => transWord("Trade cancelled. Refunded: :amt", [
    //                 'amt' => number_format($refund, 2),
    //             ]),
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => transWord("Trade cancelled successfully"),
    //             'result'  => new OrderResource($order->fresh()),
    //         ], 200);

    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         \Log::error('CancelTrade Error: '.$e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => transWord("Cancel trade failed"),
    //             'result'  => null,
    //         ], 500);
    //     }
    // }

    /**
     * Cancel a pending delivery request and optionally refund delivery fees.
     * السيناريو: trade كان open -> requestDelivery حوله pending_delivery + خصم رسوم
     * الإلغاء هنا يرجّع الأوردر لحالة open trade + ينضف حقول التسليم.
     */
    public function cancelDeliveryRequest(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            // هل نرجع الرسوم؟ (اختياري) الافتراضي true
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

        // Only cancel when delivery is still pending
        // Adjust these checks to match your statuses exactly
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

        // Prevent cancellation if already shipped/processing (if your schema supports it)
        $deliveryStatus = $order->delivery_status ?? null;
        $blockedStatuses = ['shipped', 'in_transit', 'delivered', 'processing', 'approved'];
        if ($deliveryStatus && in_array($deliveryStatus, $blockedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => transWord("Delivery request already in progress and cannot be cancelled"),
                'result'  => null,
            ], 400);
        }

        // Determine fees to refund (based on your columns)
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
            // Refund fees if enabled and > 0
            $balance = null;
            if ($refundFees && $totalFees > 0) {
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

                if (!$wallet) {
                    $wallet = Wallet::create([
                        'user_id'             => $user->id,
                        'amount'              => 0,
                        'amount_demo'         => 0,
                        'wait_withdraw_live'  => 0,
                        'wait_withdraw_demo'  => 0,
                    ]);
                }

                if ($order->mode === 'live') {
                    $wallet->increment('amount', $totalFees);
                    $balance = (float) $wallet->fresh()->amount;
                } else {
                    $wallet->increment('amount_demo', $totalFees);
                    $balance = (float) $wallet->fresh()->amount_demo;
                }

                Transaction::create([
                    'user_id'     => $user->id,
                    'credit'      => $totalFees,
                    'debit'       => 0,
                    'balance'     => $balance,
                    'type'        => 'delivery_refund', // add it to your allowed types if needed
                    'amount_type' => $order->mode,
                    'created_by'  => 'client',
                    'is_approval' => 1,
                    'approve_at'  => now(),
                    'request_at'  => now(),
                    'note'        => transWord("Delivery request cancelled. Fees refunded: :amt", [
                        'amt' => number_format($totalFees, 2),
                    ]),
                ]);
            }

            // Revert order back to open trade (type=trade, status=open)
            $update = [
                'type'            => 'trade',
                'status'          => 'open',
                'close_kind'      => null,
                'delivery_status' => null,
            ];

            if (Schema::hasColumn('orders', 'delivery_requested_at')) {
                $update['delivery_requested_at'] = null;
            }

            // Clear delivery fields you set in requestDelivery()
            $update['delivery_method'] = null;
            $update['delivery_city']   = null;
            $update['delivery_phone']  = null;

            // address column used by delivery
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
                'result'  => new OrderResource($order->fresh()),
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

    /**
     * Pending delivery trades (trade + pending_delivery)
     * تعرض الصفقات التي طلب عليها المستخدم استلام/شحن ومازالت تحت الإجراء.
     */
    public function pendingDeliveries(Request $request)
    {
        $user = auth()->user();

        // (اختياري) فلترة حسب المعدن
        $validated = $request->validate([
            'metal'    => ['nullable', Rule::in(['XAU', 'XAG'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::where('user_id', $user->id)
            ->where('mode', $user->mode)
            ->where('type', 'trade')
            ->where(function ($q) {
                // دعم الحالتين: status أو delivery_status (حسب ما هو مطبق عندك)
                $q->where('status', 'pending_delivery')
                ->orWhere('delivery_status', 'pending_delivery');
            })
            ->latest('id');

        if (!empty($validated['metal'])) {
            $query->where('metal', $validated['metal']);
        }

        // Pagination (اختياري)
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

        // بدون Pagination
        $orders = $query->get();

        // Summary بسيط للواجهة (اختياري)
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

        $orders = Order::where('user_id', $user->id)
            ->where('type', 'trade')
            ->where('mode', $mode)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = OrderResource::collection($orders);

        return response()->json([
            'success' => true,
            'message' => transWord("Trades"),
            'result'  => $data,
        ], 200);
    }

    public function tradess()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        $orders = Order::where('user_id', $user->id)
            ->where('type', 'trade')
            ->where('status', 'open')
            ->where('mode', $mode)
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = $orders->groupBy('metal'); // XAU / XAG

        $result = $grouped->map(function ($items, $metal) {
            $title = match ($metal) {
                'XAU' => transWord('Gold'),
                'XAG' => transWord('Silver'),
                default => $metal,
            };

            return [
                'metal'  => $metal,
                'title'  => $title,
                'orders' => OrderResource::collection($items)->values(),
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

        $orders = Order::where('user_id', $user->id)
            ->where('type', 'order')
            ->where('mode', $mode)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = OrderResource::collection($orders);

        return response()->json([
            'success' => true,
            'message' => transWord("Orders"),
            'result'  => $data,
        ], 200);
    }

    public function orderss()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        $orders = Order::where('user_id', $user->id)
            ->where('type', 'order')
            ->where('mode', $mode)
            ->where('status', '!=', 'canceled')   // أو cancelled
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = $orders->groupBy('metal'); // XAU / XAG

        $result = $grouped->map(function ($items, $metal) {
            $title = match ($metal) {
                'XAU' => transWord('Gold'),
                'XAG' => transWord('Silver'),
                default => $metal,
            };

            return [
                'metal'  => $metal,
                'title'  => $title,
                'orders' => OrderResource::collection($items)->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => transWord("Orders"),
            'result'  => $result,
        ], 200);
    }

    /**
     * ✅ NEW: Close Trade (بديل cancelTrade)
     * - يقفل trade مفتوحة (status=open) إلى executed
     * - السعر من السيرفر (Sell) في الإنتاج
     * - نموذج (A): نرجّع settlement = principal + pnl
     */
    public function closeTrade(Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'integer'],

            // dev فقط: يسمح بسعر يدوي للتجارب
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

        // ممنوع الإغلاق لو داخل شحن
        if ($order->status === 'pending_delivery' || !empty($order->delivery_status)) {
            return response()->json([
                'success' => false,
                'message' => transWord("Trade is in delivery process and cannot be closed"),
                'result'  => null,
            ], 400);
        }

        $qty = (float) ($order->qty ?? 0);
        $entry = (float) ($order->entry_price ?? $order->executed_price ?? $order->price ?? 0);

        if ($qty <= 0 || $entry <= 0) {
            return response()->json([
                'success' => false,
                'message' => transWord("Invalid trade data"),
                'result'  => null,
            ], 422);
        }

        $metal    = $order->metal    ?? 'XAU';
        $currency = $order->currency ?? 'USD';

        // ✅ closePrice: سيرفر في الإنتاج
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

        // ✅ نموذج (A)
        $principal  = round($entry * $qty, 8);
        $pnl        = round(($closePrice - $entry) * $qty, 8);
        $settlement = round($principal + $pnl, 8);

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id'             => $user->id,
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

            $order->update($update);

            Transaction::create([
                'user_id'     => $user->id,
                'credit'      => $settlement,
                'debit'       => 0,
                'balance'     => $balance,
                'type'        => 'sell',
                'amount_type' => $order->mode,
                'created_by'  => 'client',
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
                'note'        => "Manual close. entry=$entry close=$closePrice qty=$qty pnl=$pnl",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => transWord("Trade closed successfully"),
                'result'  => new OrderResource($order->fresh()),
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


    /**
     * ✅ Cancel Order (قبل التنفيذ فقط)
     * - يقبل فقط: type=order + status=pending
     * - يرجّع مبلغ الحجز (price * qty) للمحفظة
     * - لا حذف (علشان التقارير)
     */
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

        // ✅ لازم يكون Order pending فقط
        if ($order->type !== 'order' || $order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => transWord("Only pending orders can be cancelled"),
                'result'  => null,
            ], 400);
        }

        $qty   = (float) ($order->qty ?? 0);
        $price = (float) ($order->price ?? $order->open_price ?? 0);
        $refund = round($qty * $price, 8);

        DB::beginTransaction();
        try {
            if ($refund > 0) {
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
                if (!$wallet) {
                    $wallet = Wallet::create([
                        'user_id'             => $user->id,
                        'amount'              => 0,
                        'amount_demo'         => 0,
                        'wait_withdraw_live'  => 0,
                        'wait_withdraw_demo'  => 0,
                    ]);
                }

                if ($order->mode === 'live') {
                    $wallet->increment('amount', $refund);
                    $balance = (float) $wallet->fresh()->amount;
                } else {
                    $wallet->increment('amount_demo', $refund);
                    $balance = (float) $wallet->fresh()->amount_demo;
                }

                Transaction::create([
                    'user_id'     => $user->id,
                    'credit'      => $refund,
                    'debit'       => 0,
                    'balance'     => $balance,
                    'type'        => 'order_cancel_refund',
                    'amount_type' => $order->mode,
                    'created_by'  => 'client',
                    'is_approval' => 1,
                    'approve_at'  => now(),
                    'request_at'  => now(),
                    'note'        => "Order cancelled. Refunded=$refund",
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
                'result'  => new OrderResource($order->fresh()),
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


    /**
     * ✅ NEW helper: سعر البيع من السيرفر (Sell)
     */
    private function getServerSellPrice(string $metal, string $currency): ?float
    {
        try {
            $quote = $this->getMetalWithConversion($metal, $currency); // ['market','buy','sell']
            if (is_array($quote) && !empty($quote['sell']) && (float)$quote['sell'] > 0) {
                return (float) $quote['sell'];
            }
        } catch (\Throwable $e) {
            \Log::warning("Sell feed error: ".$e->getMessage());
        }

        // fallback: raw price
        try {
            $raw = $this->getGoldPrice("{$metal}/{$currency}");
            if (is_array($raw) && isset($raw['price']) && (float)$raw['price'] > 0) {
                return (float) $raw['price'];
            }
        } catch (\Throwable $e) {
            \Log::warning("Sell raw feed error: ".$e->getMessage());
        }

        return null;
    }
}
