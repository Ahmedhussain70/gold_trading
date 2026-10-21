<?php

namespace App\Modules\APIS;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\DepositsResource;
use App\Http\Resources\WithdrawsResource;
use App\Modules\Wallet\Models\Deposits;
use App\Modules\Wallet\Models\Withdraws;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Wallet\Models\PaymentMethods;
use Wallet\Models\Transaction;
use Wallet\Models\USDT;
use Wallet\Models\Wallet;
use AppSettings\Models\AppSettings;
use App\Services\Trading\OpenPnlService;

class FinanceController extends Controller
{
    public function currencies()
    {
        $data = currencies();

        return response()->json([
            'success' => true,
            'message' => transWord("Currencies"),
            'result'  => $data,
        ], 200);
    }

    // // ✅ MODIFIED: أضفنا رصيد EGP
    // public function wallet()
    // {
    //     $user = auth()->user();
    //     $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

    //     $wallet = Wallet::firstOrCreate(
    //         ['user_id' => $user->id],
    //         [
    //             'amount'              => 0,
    //             'amount_egp'          => 0,
    //             'amount_demo'         => 0,
    //             'wait_withdraw_live'  => 0,
    //             'wait_withdraw_demo'  => 0,
    //             'wait_withdraw_egp'   => 0,
    //         ]
    //     );

    //     $balance     = $mode === 'live' ? (float) $wallet->amount      : (float) $wallet->amount_demo;
    //     $balance_egp = $mode === 'live' ? (float) $wallet->amount_egp  : 0; // Demo لا يحتاج EGP

    //     // سعر الصرف
    //     $exchangeRate = (float) (AppSettings::where('key', 'exchange_rate_usd_to_egp')->value('value') ?? 51);

    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Wallet"),
    //         'result'  => [
    //             'mode'              => $mode,
    //             'balance'           => $balance,           // ✅ USD (زي ما كان)
    //             'balance_egp'       => $balance_egp,       // ✅ NEW: EGP
    //             'wait_withdraw'     => $mode === 'live'
    //                 ? (float) $wallet->wait_withdraw_live
    //                 : (float) $wallet->wait_withdraw_demo,
    //             'wait_withdraw_egp' => $mode === 'live'
    //                 ? (float) $wallet->wait_withdraw_egp
    //                 : 0,
    //             'exchange_rate'     => $exchangeRate,       // ✅ NEW
    //         ],
    //     ], 200);
    // }

    // ✅ MODIFIED: أضفنا رصيد EGP + Floating P/L
    public function wallet(OpenPnlService $openPnlService)
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'amount'              => 0,
                'amount_egp'          => 0,
                'amount_demo'         => 0,
                'wait_withdraw_live'  => 0,
                'wait_withdraw_demo'  => 0,
                'wait_withdraw_egp'   => 0,
            ]
        );

        $balance     = $mode === 'live' ? (float) $wallet->amount     : (float) $wallet->amount_demo;
        $balance_egp = $mode === 'live' ? (float) $wallet->amount_egp : 0;

        $exchangeRate = (float) (AppSettings::where('key', 'exchange_rate_usd_to_egp')->value('value') ?? 51);

        $pnlSummary = $openPnlService->calculateSummaryForUser($user->id, $mode);

        $floatingPnlUsd = (float) $pnlSummary['floating_pnl_usd'];
        $floatingPnlEgp = (float) $pnlSummary['floating_pnl_egp'];

        // مهم:
        // equity USD = محفظة الدولار + P/L أوردرات الدولار فقط
        // equity EGP = محفظة الجنيه + P/L أوردرات الجنيه فقط
        $equityUsd = $balance + $floatingPnlUsd;
        $equityEgp = $balance_egp + $floatingPnlEgp;

        return response()->json([
            'success' => true,
            'message' => transWord("Wallet"),
            'result'  => [
                'mode'              => $mode,

                // Existing keys
                'balance'           => $balance,
                'balance_egp'       => $balance_egp,
                'wait_withdraw'     => $mode === 'live'
                    ? (float) $wallet->wait_withdraw_live
                    : (float) $wallet->wait_withdraw_demo,
                'wait_withdraw_egp' => $mode === 'live'
                    ? (float) $wallet->wait_withdraw_egp
                    : 0,
                'exchange_rate'     => $exchangeRate,

                // NEW: P/L مفصول حسب عملة الأوردرات
                'floating_pnl_usd'  => round($floatingPnlUsd, 6),
                'floating_pnl_egp'  => round($floatingPnlEgp, 2),

                // NEW: Equity مفصول حسب المحافظ
                'equity_usd'        => round($equityUsd, 6),
                'equity_egp'        => round($equityEgp, 2),

                'open_trades_count' => (int) $pnlSummary['open_trades_count'],
            ],
        ], 200);
    }

    /**
     * ✅ NEW: تحويل عملة من USD إلى EGP أو العكس
     * Live mode فقط
     */
    public function convertCurrency(Request $request)
    {
        $validated = $request->validate([
            'amount'    => ['required', 'numeric', 'gt:0'],
            'from'      => ['required', 'string', Rule::in(['USD', 'EGP'])],
            'to'        => ['required', 'string', Rule::in(['USD', 'EGP']), 'different:from'],
        ]);

        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        if ($mode !== 'live') {
            return response()->json([
                'success' => false,
                'message' => transWord("Currency conversion is available in Live mode only"),
                'result'  => null,
            ], 403);
        }

        $amount       = (float) $validated['amount'];
        $from         = strtoupper($validated['from']);
        $to           = strtoupper($validated['to']);
        $exchangeRate = (float) (AppSettings::where('key', 'exchange_rate_usd_to_egp')->value('value') ?? 51);

        if ($exchangeRate <= 0) {
            return response()->json([
                'success' => false,
                'message' => transWord("Exchange rate not configured"),
                'result'  => null,
            ], 500);
        }

        // حساب المبلغ المحول
        if ($from === 'USD' && $to === 'EGP') {
            $convertedAmount = round($amount * $exchangeRate, 2);
            $fromColumn = 'amount';
            $toColumn   = 'amount_egp';
        } else {
            // EGP → USD
            $convertedAmount = round($amount / $exchangeRate, 2);
            $fromColumn = 'amount_egp';
            $toColumn   = 'amount';
        }

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

            $available = (float) $wallet->{$fromColumn};

            if ($amount > $available) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("Insufficient :currency balance", ['currency' => $from]),
                    'result'  => [
                        'required'  => $amount,
                        'available' => $available,
                    ],
                ], 400);
            }

            // خصم من المصدر
            $wallet->decrement($fromColumn, $amount);
            // إضافة للوجهة
            $wallet->increment($toColumn, $convertedAmount);

            $wallet->refresh();

            // تسجيل Transaction
            Transaction::create([
                'user_id'     => $user->id,
                'credit'      => $convertedAmount,
                'debit'       => $amount,
                'balance'     => (float) $wallet->amount, // USD balance
                'type'        => 'currency_convert',
                'amount_type' => 'live',
                'currency'    => "{$from}_TO_{$to}", // ✅ مثلاً USD_TO_EGP
                'created_by'  => 'client',
                'is_approval' => 1,
                'approve_at'  => now(),
                'request_at'  => now(),
                'note'        => "{$from} {$amount} → {$to} {$convertedAmount} (Rate: {$exchangeRate})",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => transWord("Currency converted successfully"),
                'result'  => [
                    'from'             => $from,
                    'to'               => $to,
                    'amount'           => $amount,
                    'converted_amount' => $convertedAmount,
                    'exchange_rate'    => $exchangeRate,
                    'balance_usd'     => (float) $wallet->amount,
                    'balance_egp'     => (float) $wallet->amount_egp,
                ],
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ConvertCurrency Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => transWord("Currency conversion failed"),
                'result'  => null,
            ], 500);
        }
    }

    public function reportDeposit()
    {
        $user = auth()->user();
        $mode = in_array($user->mode, ['live','demo'], true) ? $user->mode : 'demo';

        $deposits = Deposits::where('user_id', $user->id)
            ->where('status', $mode)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => transWord("Deposits Report"),
            'result'  => DepositsResource::collection($deposits),
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

        return response()->json([
            'success' => true,
            'message' => transWord("Withdraw Report"),
            'result'  => WithdrawsResource::collection($withdraws),
        ], 200);
    }

    /**
     * ✅ Live only — الإيداع بالدولار فقط (بدون تغيير)
     */
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

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'amount'              => 0,
                    'amount_egp'          => 0,
                    'amount_demo'         => 0,
                    'wait_withdraw_live'  => 0,
                    'wait_withdraw_demo'  => 0,
                    'wait_withdraw_egp'   => 0,
                ]
            );

            $currentBalance = (float) $wallet->amount;

            $deposit = Deposits::create([
                'user_id'        => $user->id,
                'amount'         => (float) $validated['amount'],
                'status'         => $mode,
                'payment_method' => $pm->name,
                'transaction_id' => null,
                'note'           => $validated['note'] ?? null,
                'is_approval'    => 0,
                'approve_at'     => null,
                'created_by'     => $user->id,
                'payer_name'     => $validated['payer_name'],
                'payer_phone'    => $validated['payer_phone'],
                'receipt_path'   => $path,
            ]);

            $transaction = Transaction::create([
                'user_id'     => $user->id,
                'credit'      => (float) $deposit->amount,
                'debit'       => 0,
                'balance'     => $currentBalance,
                'type'        => 'Deposit',
                'amount_type' => $mode,
                'currency'    => 'USD',
                'is_approval' => 0,
                'approve_at'  => null,
                'request_at'  => now(),
                'note'        => $validated['note'] ?? null,
                'created_by'  => 'client',
            ]);

            $deposit->update(['transaction_id' => $transaction->id]);

            DB::commit();

            return response()->json([
                'success'     => true,
                'message'     => transWord("Make Deposit"),
                'result'      => transWord("Deposit is sent successfully"),
                'deposit_id'  => $deposit->id,
                'receipt_url' => $path ? asset('storage/' . $path) : null, 
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

    /**
     * ✅ MODIFIED: Live only — دعم السحب بالدولار والجنيه
     * - أضفنا currency parameter اختياري (default: USD)
     */
    public function makeWithdraw(Request $request)
    {
        $validated = $request->validate([
            'amount'            => ['required','numeric','gt:0'],
            'currency'          => ['nullable','string', Rule::in(['USD', 'EGP'])], // ✅ NEW
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods','id')
                    ->where(fn($q) => $q->where('is_active',1)->whereNull('deleted_at'))
            ],
            'note'          => ['nullable','string','max:1000'],

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

        $amount   = (float) $validated['amount'];
        $currency = strtoupper($validated['currency'] ?? 'USD'); // ✅ default USD

        // ✅ تحديد الأعمدة حسب العملة
        $balanceColumn      = $currency === 'EGP' ? 'amount_egp'         : 'amount';
        $waitWithdrawColumn = $currency === 'EGP' ? 'wait_withdraw_egp'  : 'wait_withdraw_live';

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("Withdrawal Error"),
                    'result'  => transWord("Wallet not found."),
                ], 404);
            }

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

            if ($amount > (float) $wallet->{$balanceColumn}) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => transWord("Withdrawal Error"),
                    'result'  => transWord("Insufficient balance in your wallet."),
                ], 400);
            }

            $pm = PaymentMethods::active()->findOrFail($validated['payment_method_id']);

            $withdraw = Withdraws::create([
                'user_id'        => $user->id,
                'amount'         => $amount,
                'currency'       => $currency, // ✅ NEW: حفظ العملة
                'status'         => $mode,
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

            $wallet->update([
                $balanceColumn      => DB::raw("{$balanceColumn} - {$amount}"),
                $waitWithdrawColumn => DB::raw("{$waitWithdrawColumn} + {$amount}"),
            ]);
            $currentBalance = (float) $wallet->fresh()->{$balanceColumn};

            $transaction = Transaction::create([
                'user_id'     => $user->id,
                'credit'      => 0,
                'debit'       => $amount,
                'balance'     => $currentBalance,
                'type'        => 'Withdraw',
                'amount_type' => $mode,
                'currency'    => $currency,
                'is_approval' => 0,
                'approve_at'  => null,
                'request_at'  => now(),
                'note'        => ($validated['note'] ?? '') . " [{$currency}]",
                'created_by'  => 'client',
            ]);

            $withdraw->update(['transaction_id' => $transaction->id]);

            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => transWord("Make Withdraw"),
                'result'       => transWord("Withdraw is sent successfully"),
                'withdraw_id'  => $withdraw->id,
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
        $validated = $request->validate([
            'type' => ['nullable', Rule::in([
                'Deposit', 'Withdraw',
                'buy', 'sell', 'delivery',
                'order_reserve', 'order_cancel_refund',
                'delivery_refund',
                'currency_convert',
                'trade_open',
            ])],
            'currency'    => ['nullable', Rule::in(['USD', 'EGP', 'DEMO'])],
            'created_by'  => ['nullable', Rule::in(['client', 'admin'])],
            'is_approval' => ['nullable', Rule::in([0, 1])],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = auth()->user();
        $mode = in_array($user->mode, ['live', 'demo'], true) ? $user->mode : 'demo';

        // ✅ Query أساسي مشترك (بدون currency filter)
        $baseQuery = Transaction::query()
            ->where('user_id', $user->id)
            ->where('amount_type', $mode);

        if (!empty($validated['type'])) {
            $baseQuery->where('type', $validated['type']);
        }
        if (array_key_exists('is_approval', $validated) && $validated['is_approval'] !== null) {
            $baseQuery->where('is_approval', (int) $validated['is_approval']);
        }
        if (!empty($validated['created_by'])) {
            $baseQuery->where('created_by', $validated['created_by']);
        }
        if (!empty($validated['date_from'])) {
            $baseQuery->whereDate('created_at', '>=', Carbon::parse($validated['date_from'])->toDateString());
        }
        if (!empty($validated['date_to'])) {
            $baseQuery->whereDate('created_at', '<=', Carbon::parse($validated['date_to'])->toDateString());
        }

        // ✅ Query للـ pagination والـ summary — يشمل currency filter
        $q = clone $baseQuery;
        if (!empty($validated['currency'])) {
            $q->where('currency', $validated['currency']);
        }

        $summaryQuery = clone $q;

        $perPage   = (int) ($validated['per_page'] ?? 20);
        $paginator = (clone $q)->orderBy('created_at', 'desc')->paginate($perPage);

        $totalCredit = (float) (clone $summaryQuery)->sum('credit');
        $totalDebit  = (float) (clone $summaryQuery)->sum('debit');

        // ✅ last_balance يراعي currency filter
        $lastBalanceQuery = clone $q;
        $lastBalance = optional(
            $lastBalanceQuery->orderBy('created_at', 'desc')->first()
        )->balance;

        // ✅ by_currency على baseQuery بدون currency filter
        $by_currency = [
            'USD'  => [
                'credit' => (float) (clone $baseQuery)->where('currency', 'USD')->sum('credit'),
                'debit'  => (float) (clone $baseQuery)->where('currency', 'USD')->sum('debit'),
            ],
            'EGP'  => [
                'credit' => (float) (clone $baseQuery)->where('currency', 'EGP')->sum('credit'),
                'debit'  => (float) (clone $baseQuery)->where('currency', 'EGP')->sum('debit'),
            ],
            'DEMO' => [
                'credit' => (float) (clone $baseQuery)->where('currency', 'DEMO')->sum('credit'),
                'debit'  => (float) (clone $baseQuery)->where('currency', 'DEMO')->sum('debit'),
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => transWord('Transactions'),
            'result'  => [
                'mode'    => $mode,
                'summary' => [
                    'total_credit' => $totalCredit,
                    'total_debit'  => $totalDebit,
                    'last_balance' => $lastBalance,
                    'count'        => $paginator->total(),
                    'by_currency'  => $by_currency,
                ],
                'data' => TransactionResource::collection($paginator->items()),
                'meta' => [
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
        $methods = PaymentMethods::active()->get(['id','name','value']);

        return response()->json([
            'success' => true,
            'message' => transWord('Payment Methods (Deposit)'),
            'result'  => $methods,
        ], 200);
    }

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