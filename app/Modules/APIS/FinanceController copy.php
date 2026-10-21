<?php

namespace App\Modules\APIS;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\DepositsResource;
use App\Http\Resources\WithdrawsResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use stdClass;
use Wallet\Models\Transaction;
use Wallet\Models\USDT;
use Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Deposits;
use App\Modules\Wallet\Models\Withdraws;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Wallet\Models\PaymentMethods;



class FinanceController extends Controller
{
    public function currencies(){
        $data = currencies();
        return response()->json([
            'success' => true,
            'message' => transWord("Currencies"), 
            'result' => $data,
        ],200);
    }
    
    public function wallet()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'amount'              => 0,
                'amount_demo'         => 0,
                'wait_withdraw_live'  => 0,
                'wait_withdraw_demo'  => 0,
            ]
        );

        $balance = $mode === 'live' ? (float) $wallet->amount : (float) $wallet->amount_demo;

        return response()->json([
            'success' => true,
            'message' => transWord("Wallet"),
            'result'  => [
                'mode'    => $mode,
                'balance' => $balance,
                // (اختياري) لو تحب تعرض المجمد
                'wait_withdraw' => $mode === 'live'
                    ? (float) $wallet->wait_withdraw_live
                    : (float) $wallet->wait_withdraw_demo,
            ],
        ], 200);
    }

    public function reportDeposit()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';
        
        $deposits = Deposits::where('user_id', $user->id)
            ->where('status', $mode)
            ->orderBy('created_at', 'desc')
            ->get();
    
        $data = DepositsResource::collection($deposits);
        return response()->json([
            'success' => true,
            'message' => transWord("Deposits Report"),
            'result' => $data,
        ], 200);
    }

    public function reportWithdraw()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';
        
        $withdraws = Withdraws::where('user_id', $user->id)
            ->where('status', $mode)
            ->orderBy('created_at', 'desc')
            ->get();
            
        $data = WithdrawsResource::collection($withdraws);
        return response()->json([
            'success' => true,
            'message' => transWord("Withdraw Report"),
            'result' => $data,
        ], 200);
    }

    public function makeDeposit(Request $request)
    {
        $validated = $request->validate([
            'amount'            => ['required','numeric','gt:0'],
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods','id')
                    ->where(fn($q) => $q->where('is_active',1)->whereNull('deleted_at'))
            ],
            'note'        => ['nullable','string','max:1000'],
            'payer_name'  => ['required','string','max:150'],
            'payer_phone' => ['required','string','max:25'],
            'receipt'     => ['nullable','file','mimes:jpg,jpeg,png,webp,pdf','max:4096'],
        ]);

        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        if ($mode !== 'live') {
            return response()->json([
                'success' => false,
                'message' => transWord("Deposit is available in Live mode only"),
                'result'  => null,
            ], 403);
        }

        DB::beginTransaction();
        try {
            $pm = PaymentMethods::active()->findOrFail($validated['payment_method_id']);

            $path = null;
            if ($request->hasFile('receipt')) {
                $path = $request->file('receipt')->store('receipts/'.now()->format('Y/m'), 'public');
            }

            // محفظة المستخدم (للإظهار فقط، لن نزيد الرصيد إلا بعد موافقة الأدمن)
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'amount'             => 0,
                    'amount_demo'        => 0,
                    'wait_withdraw_live' => 0,
                    'wait_withdraw_demo' => 0,
                ]
            );

            $currentBalance = $mode === 'live' ? (float)$wallet->amount : (float)$wallet->amount_demo;

            $deposit = Deposits::create([
                'user_id'        => $user->id,
                'amount'         => (float)$validated['amount'],
                'status'         => $mode,                 // ✅ live / demo (sanitized)
                'payment_method' => $pm->name,
                'transaction_id' => null,
                'note'           => $validated['note'] ?? null,
                'is_approval'    => 0,
                'approve_at'     => null,
                'created_by'     => $user->id,            // (كما هو في جدول deposits عندك)
                'payer_name'     => $validated['payer_name'],
                'payer_phone'    => $validated['payer_phone'],
                'receipt_path'   => $path,
            ]);

            // Transaction Pending (لن يتم تعديل balance هنا)
            $transaction = Transaction::create([
                'user_id'     => $user->id,
                'credit'      => (float)$deposit->amount,
                'debit'       => 0,
                'balance'     => $currentBalance,         // الرصيد الحالي قبل الإضافة
                'type'        => 'Deposit',
                'amount_type' => $mode,
                'is_approval' => 0,
                'approve_at'  => null,
                'request_at'  => now(),
                'note'        => $validated['note'] ?? null,
                'created_by'  => 'client',                // ✅ مهم للفلترة في transactions()
            ]);

            $deposit->update(['transaction_id' => $transaction->id]);

            DB::commit();

            return response()->json([
                'success'     => true,
                'message'     => transWord("Make Deposit"),
                'result'      => transWord("Deposit is sent successfully"),
                'deposit_id'  => $deposit->id,
                'receipt_url' => $deposit->receipt_url ?? null,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Deposit Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => transWord("Deposit Error"),
                'result'  => transWord("An error occurred while processing your deposit."),
            ], 500);
        }
    }

    public function makeWithdraw(Request $request)
    {
        $validated = $request->validate([
            'amount'            => ['required','numeric','gt:0'],
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods','id')
                    ->where(fn($q) => $q->where('is_active',1)->whereNull('deleted_at'))
            ],
            'note'          => ['nullable','string','max:1000'],

            // بيانات الحساب
            'account_name'  => ['required','string','max:150'],
            'account_phone' => ['required','string','max:25'],
            'bank_name'     => ['required','string','max:150'],
            'bank_account'  => ['required','string','max:100'],
            'address'       => ['nullable','string','max:191'],
            'binanceId'     => ['nullable','string','max:50'],
        ]);

        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';
        if ($mode !== 'live') {
            return response()->json([
                'success' => false,
                'message' => transWord("Withdraw is available in Live mode only"),
                'result'  => null,
            ], 403);
        }

        $amount = (float)$validated['amount'];

        DB::beginTransaction();
        try {
            // لازم lock لأننا هنخصم رصيد
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("Withdrawal Error"),
                    'result'  => transWord("Wallet not found."),
                ], 404);
            }

            // Min withdraw setting (اختياري)
            if (function_exists('get_setting')) {
                $min = (float) get_setting('min_withdraw');
                if ($min > 0 && $amount < $min) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => transWord("Withdrawal Error"),
                        'result'  => transWord("Minimum withdrawal amount is :min", ['min'=>$min]),
                    ], 400);
                }
            }

            // تحقق الرصيد
            if ($mode === 'live' && $amount > (float)$wallet->amount) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("Withdrawal Error"),
                    'result'  => transWord("Insufficient balance in your wallet."),
                ], 400);
            }
            if ($mode === 'demo' && $amount > (float)$wallet->amount_demo) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("Withdrawal Error"),
                    'result'  => transWord("Insufficient balance in your demo wallet."),
                ], 400);
            }

            $pm = PaymentMethods::active()->findOrFail($validated['payment_method_id']);

            $withdraw = Withdraws::create([
                'user_id'        => $user->id,
                'amount'         => $amount,
                'status'         => $mode,            // ✅ sanitized
                'payment_method' => $pm->name,
                'transaction_id' => null,
                'is_approval'    => 0,
                'created_by'     => $user->id,
                'note'           => $validated['note'] ?? null,

                'account_name'   => $validated['account_name'],
                'account_phone'  => $validated['account_phone'],
                'bank_name'      => $validated['bank_name'],
                'bank_account'   => $validated['bank_account'],
                'address'        => $validated['address'] ?? null,
                'binanceId'      => $validated['binanceId'] ?? null,
            ]);

            // خصم + تجميد
            if ($mode === 'live') {
                $wallet->decrement('amount', $amount);
                $wallet->increment('wait_withdraw_live', $amount);
                $currentBalance = (float)$wallet->amount;
            } else {
                $wallet->decrement('amount_demo', $amount);
                $wallet->increment('wait_withdraw_demo', $amount);
                $currentBalance = (float)$wallet->amount_demo;
            }

            $transaction = Transaction::create([
                'user_id'     => $user->id,
                'credit'      => 0,
                'debit'       => $amount,
                'balance'     => $currentBalance,
                'type'        => 'Withdraw',
                'amount_type' => $mode,
                'is_approval' => 0,
                'approve_at'  => null,
                'request_at'  => now(),
                'note'        => $validated['note'] ?? null,
                'created_by'  => 'client',            // ✅ مهم للفلترة في transactions()
            ]);

            $withdraw->update(['transaction_id' => $transaction->id]);

            DB::commit();

            return response()->json([
                'success'     => true,
                'message'     => transWord("Make Withdraw"),
                'result'      => transWord("Withdraw is sent successfully"),
                'withdraw_id' => $withdraw->id,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Withdraw Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => transWord("Withdrawal Error"),
                'result'  => transWord("An error occurred while processing your withdrawal."),
            ], 500);
        }
    }

    public function payUSDT(Request $request)
    {
        $validated = $request->validate([
            'currency' => ['required','string','max:10'],
            'amount'   => ['required','numeric','gt:0'],
            'message'  => ['nullable','string','max:1000'],
        ]);

        USDT::create([
            'user_id'     => auth()->id(),
            'amount'      => $validated['amount'],
            'currency'    => $validated['currency'],
            'message'     => $validated['message'] ?? null,
            'is_approval' => 0,
            'approve_at'  => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => transWord("Pay USDT"),
            'result'  => transWord("USDT is sent successfully"),
        ], 200);
    }
    
    public function transactions(Request $request)
    {
        // التحقق من المدخلات (اختيارية للفلاتر)
        $validated = $request->validate([     // لتجاوز مود المستخدم لو عايز
            'type' => ['nullable', Rule::in([
                            'Deposit','Withdraw',
                            'buy','sell','delivery',
                            'cancel_trade','delivery_refund'
                        ])],
            'created_by'   => ['nullable', Rule::in(['client', 'admin'])],      // مصدر العملية
            'is_approval'  => ['nullable', Rule::in([0,1])],                    // 0 أو 1
            'date_from'    => ['nullable', 'date'],                             // YYYY-mm-dd أو ISO
            'date_to'      => ['nullable', 'date'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = auth()->user();

        // المود الافتراضي هو مود المستخدم الحالي (live/demo)
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        // بناء الاستعلام
        $q = Transaction::query()
            ->where('user_id', $user->id)
            ->where('amount_type', $mode); // مهم: يفلتر على Live/Demo

        // فلاتر اختيارية
        if (!empty($validated['type'])) {
            $q->where('type', $validated['type']);
        }

        if (array_key_exists('is_approval', $validated) && $validated['is_approval'] !== null) {
            $q->where('is_approval', (int)$validated['is_approval']);
        }

        if (!empty($validated['created_by'])) {
            $q->where('created_by', $validated['created_by']); // 'client' or 'admin'
        }

        if (!empty($validated['date_from'])) {
            $q->whereDate('created_at', '>=', Carbon::parse($validated['date_from'])->toDateString());
        }

        if (!empty($validated['date_to'])) {
            $q->whereDate('created_at', '<=', Carbon::parse($validated['date_to'])->toDateString());
        }

        // باجينيشن
        $perPage = $validated['per_page'] ?? 20;
        $q->orderBy('created_at', 'desc');

        $paginator = $q->paginate($perPage);

        // تلخيص سريع (إجماليات لنفس الشروط)
        // ملاحظة: لتوفير الموارد، بنعيد استخدام نفس الفلاتر لكن بدون الباجينيشن
        $summaryQuery = Transaction::query()
            ->where('user_id', $user->id)
            ->where('amount_type', $mode);

        if (!empty($validated['type'])) {
            $summaryQuery->where('type', $validated['type']);
        }
        if (array_key_exists('is_approval', $validated) && $validated['is_approval'] !== null) {
            $summaryQuery->where('is_approval', (int)$validated['is_approval']);
        }
        if (!empty($validated['created_by'])) {
            $summaryQuery->where('created_by', $validated['created_by']);
        }
        if (!empty($validated['date_from'])) {
            $summaryQuery->whereDate('created_at', '>=', Carbon::parse($validated['date_from'])->toDateString());
        }
        if (!empty($validated['date_to'])) {
            $summaryQuery->whereDate('created_at', '<=', Carbon::parse($validated['date_to'])->toDateString());
        }

        $totalCredit = (float) $summaryQuery->clone()->sum('credit');
        $totalDebit  = (float) $summaryQuery->clone()->sum('debit');

        // آخر رصيد معروف ضمن نفس المود (لو محتاجه في الواجهة)
        $lastBalance = optional(
            Transaction::where('user_id', $user->id)
                ->where('amount_type', $mode)
                ->orderBy('created_at', 'desc')
                ->first()
        )->balance;

        // تجهيز الداتا
        $data = TransactionResource::collection($paginator->items());

        return response()->json([
            'success' => true,
            'message' => transWord('Transactions'),
            'result'  => [
                'mode'     => $mode,                     // live أو demo
                'summary'  => [
                    'total_credit' => $totalCredit,
                    'total_debit'  => $totalDebit,
                    'last_balance' => $lastBalance,
                    'count'        => $paginator->total(),
                ],
                'data'     => $data,                     // العناصر الحالية في الصفحة
                'meta'     => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'last_page'    => $paginator->lastPage(),
                    'from'         => $paginator->firstItem(),
                    'to'           => $paginator->lastItem(),
                    'total'        => $paginator->total(),
                ],
            ],
        ], 200);
    }

    public function paymentMethodsForDeposit()
    {
        // Active فقط وبالترتيب
        $methods = PaymentMethods::active()->get(['id','name','value']);

        return response()->json([
            'success' => true,
            'message' => transWord('Payment Methods (Deposit)'),
            'result'  => $methods,
        ], 200);
    }

    // خيارات طرق الدفع للسحب: (name فقط)
    public function paymentMethodsForWithdraw()
    {
        $methods = PaymentMethods::active()->get(['id','name']);

        return response()->json([
            'success' => true,
            'message' => transWord('Payment Methods (Withdraw Options)'),
            'result'  => $methods,
        ], 200);
    }

}
