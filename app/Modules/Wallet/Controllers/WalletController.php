<?php

namespace Wallet\Controllers;
use App\Http\Controllers\Controller;
use App\Http\Trait\UploadImage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Wallet\Models\Transaction;
use Wallet\Models\USDT;
use Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Modules\Wallet\Models\Deposits;
use App\Modules\Wallet\Models\Withdraws;


class WalletController extends Controller
{
    public $path;
    use UploadImage;

    public function __construct()
    {
        $this->middleware('auth');
        $this->path = 'Wallet::';
    }

    public function index() 
    {
        $title = transWord('Wallet');
        $pages = [
            [transWord('Wallet'),'show_wallets']
        ];

        $wallets = Wallet::latest()->get();

        return view($this->path.'index',compact('wallets','pages','title'));
    }
    
    public function transactions($id) 
    {
        $title = transWord('Transactions (Live)');
        $pages = [
            [transWord('Transactions'),'show_wallets']
        ];

        $transactions = Transaction::where('user_id',$id)->where('amount_type' , 'live')->get();
        $deposits = Transaction::where('user_id',$id)->where('type','Deposit')->where('amount_type' , 'live')->where('is_approval',1)->sum('credit');
        $withdraws = Transaction::where('user_id',$id)->where('type','Withdraw')->where('amount_type' , 'live')->where('is_approval',1)->sum('debit');

        return view($this->path.'transactions',compact('transactions','pages','title','deposits','withdraws'));
    }

    public function transactionsDemo($id) 
    {
        $title = transWord('Transactions (Demo)');
        $pages = [
            [transWord('Transactions'),'show_wallets_demo']
        ];

        $transactions = Transaction::where('user_id',$id)->where('amount_type' , 'demo')->get();
        $deposits = Transaction::where('user_id',$id)->where('type','Deposit')->where('amount_type' , 'demo')->where('is_approval',1)->sum('credit');
        $withdraws = Transaction::where('user_id',$id)->where('type','Withdraw')->where('amount_type' , 'demo')->where('is_approval',1)->sum('debit');

        return view($this->path.'transactions',compact('transactions','pages','title','deposits','withdraws'));
    }
    
    // public function approveDeposit($id) 
    // {
    //     DB::beginTransaction();
    //     try {
    //         $deposit = Deposits::findOrFail($id);
    //         if(!$deposit){
    //             return back()->with('error','This deposit is not found');
    //         }
    //         if($deposit->is_approval == 1){
    //             return back()->with('error','This deposit is already approved');
    //         }

    //         $wallet = Wallet::where('user_id', $deposit->user_id)->lockForUpdate()->firstOrFail();
    //         if($deposit->status == 'live'){
    //             $wallet->amount += $deposit->amount;
    //             $current_balance = $wallet->amount;
    //         }elseif($deposit->status == 'demo'){
    //             $wallet->amount_demo += $deposit->amount;
    //             $current_balance = $wallet->amount_demo;
    //         }
    //         $wallet->save();

    //         $transaction = Transaction::create([
    //             'user_id' => $deposit->id,
    //             'credit' => $deposit->amount,
    //             'debit' => 0,
    //             'balance' => $current_balance,
    //             'type' => 'Deposit',
    //             'amount_type' => $deposit->status,
    //             'is_approval' => 1,
    //             'approve_at' => Carbon::now(),
    //             'request_at' => Carbon::now()
    //         ]);

    //         $deposit->transaction_id = $transaction->id;
    //         $deposit->is_approval = 1;
    //         $deposit->approve_at = Carbon::now();
    //         $deposit->approved_by = auth()->user()->id;
    //         $deposit->save();

    //         DB::commit();
    //         return back()->with('success', 'Deposit is approved successfully');
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->with('error', 'An error occurred: ' . $e->getMessage());
    //     }
    // }
    public function approveDeposit($id) 
    {
        DB::beginTransaction();
        try {
            $deposit = Deposits::lockForUpdate()->findOrFail($id);

            if ($deposit->is_approval == 1) {
                DB::rollBack();
                return back()->with('error','This deposit is already approved');
            }

            $wallet = Wallet::where('user_id', $deposit->user_id)->lockForUpdate()->firstOrFail();

            if ($deposit->status === 'live') {
                $wallet->amount = (float)$wallet->amount + (float)$deposit->amount;
                $current_balance = (float)$wallet->amount;
            } else { // demo
                $wallet->amount_demo = (float)$wallet->amount_demo + (float)$deposit->amount;
                $current_balance = (float)$wallet->amount_demo;
            }
            $wallet->save();

            // حدّث نفس الحركة بدل إنشاء واحدة جديدة
            if ($deposit->transaction_id) {
                Transaction::where('id', $deposit->transaction_id)->update([
                    'is_approval' => 1,
                    'approve_at'  => now(),
                    'balance'     => $current_balance,
                ]);
            } else {
                // احتياطياً لو مفيش حركة لأي سبب
                $t = Transaction::create([
                    'user_id'     => $deposit->user_id,
                    'credit'      => $deposit->amount,
                    'debit'       => 0,
                    'balance'     => $current_balance,
                    'type'        => 'Deposit',
                    'amount_type' => $deposit->status,
                    'is_approval' => 1,
                    'approve_at'  => now(),
                    'request_at'  => $deposit->created_at ?? now(),
                    'note'        => $deposit->note,
                ]);
                $deposit->transaction_id = $t->id;
            }

            $deposit->is_approval = 1;
            $deposit->approve_at = now();
            $deposit->approved_by = auth()->id();
            $deposit->save();

            DB::commit();
            return back()->with('success', 'Deposit is approved successfully');

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }


    // public function approveWithdraw($id) 
    // {
    //     DB::beginTransaction();
    //     try {
    //         $withdraw = Withdraws::findOrFail($id);
            
    //         if (!$withdraw) {
    //             return back()->with('error', 'This withdrawal is not found');
    //         }

    //         if ($withdraw->is_approval == 1) {
    //             return back()->with('error', 'This withdrawal is already approved');
    //         }

    //         $wallet = Wallet::where('user_id', $withdraw->user_id)->lockForUpdate()->firstOrFail();
    //         if ($withdraw->status == 'live' && $wallet->wait_withdraw_live < $withdraw->amount) {
    //             return back()->with('error', 'Insufficient balance in live wallet.');
    //         }

    //         if ($withdraw->status == 'demo' && $wallet->wait_withdraw_demo < $withdraw->amount) {
    //             return back()->with('error', 'Insufficient balance in demo wallet.');
    //         }

    //         if ($withdraw->status == 'live') {
    //             $wallet->decrement('wait_withdraw_live', $withdraw->amount);
    //         } elseif ($withdraw->status == 'demo') {
    //             $wallet->decrement('wait_withdraw_demo', $withdraw->amount);
    //         }
    //         $wallet->save();

    //         $withdraw->update([
    //             'is_approval' => 1,
    //             'approve_at' => Carbon::now(),
    //             'approved_by' => auth()->user()->id
    //         ]);

    //         DB::commit();
    //         return back()->with('success', 'Withdrawal is approved successfully');

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->with('error', 'An error occurred: ' . $e->getMessage());
    //     }
    // }
    public function approveWithdraw($id)  
    {
        DB::beginTransaction();
        try {
            $withdraw = Withdraws::lockForUpdate()->findOrFail($id);

            if ($withdraw->is_approval == 1) {
                DB::rollBack();
                return back()->with('error', 'This withdrawal is already approved');
            }

            $wallet = Wallet::where('user_id', $withdraw->user_id)->lockForUpdate()->firstOrFail();

            if ($withdraw->status == 'live') {
                if ($wallet->wait_withdraw_live < $withdraw->amount) {
                    DB::rollBack();
                    return back()->with('error', 'Insufficient balance in live wallet.');
                }
                $wallet->wait_withdraw_live -= (float)$withdraw->amount;
            } else { // demo
                if ($wallet->wait_withdraw_demo < $withdraw->amount) {
                    DB::rollBack();
                    return back()->with('error', 'Insufficient balance in demo wallet.');
                }
                $wallet->wait_withdraw_demo -= (float)$withdraw->amount;
            }
            $wallet->save();

            // لو الحركة كانت Pending—نخليها Approved
            if ($withdraw->transaction_id) {
                Transaction::where('id', $withdraw->transaction_id)->update([
                    'is_approval' => 1,
                    'approve_at'  => now(),
                ]);
            }

            $withdraw->update([
                'is_approval' => 1,
                'approve_at'  => now(),
                'approved_by' => auth()->id()
            ]);

            DB::commit();
            return back()->with('success', 'Withdrawal is approved successfully');

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }



    public function approveTransactions($id) 
    {
        DB::beginTransaction();
        try {
            $transactions = Transaction::findOrFail($id);
            $user_wallet = Wallet::where('user_id', $transactions->user_id)->firstOrFail();

            if ($transactions->type == 'Deposit') {
                if ($transactions->amount_type == 'live') {
                    $user_wallet->amount += $transactions->amount;
                } elseif ($transactions->amount_type == 'demo') {
                    $user_wallet->amount_demo += $transactions->amount;
                }
            } elseif ($transactions->type == 'Withdraw') {
                if ($transactions->amount_type == 'live' && $user_wallet->amount < $transactions->amount) {
                    return back()->with('error', 'Insufficient balance');
                } elseif ($transactions->amount_type == 'demo' && $user_wallet->amount_demo < $transactions->amount) {
                    return back()->with('error', 'Insufficient demo balance');
                }

                if ($transactions->amount_type == 'live') {
                    $user_wallet->amount -= $transactions->amount;
                } elseif ($transactions->amount_type == 'demo') {
                    $user_wallet->amount_demo -= $transactions->amount;
                }
            }

            $user_wallet->save();

            $transactions->is_approval = 1;
            $transactions->approve_at = Carbon::now();
            $transactions->save();

            DB::commit();
            return back()->with('success', 'Transaction is approved successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function addAmount(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'account_type' => 'required|in:live,demo',
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction(); 

        try {
            // جلب المحفظة
            $wallet = Wallet::where('user_id', $id)->first();

            if (!$wallet) {
                throw new \Exception('Wallet not found for this user.');
            }
            // تحديث الرصيد
            if ($request->account_type == 'live') {
                $wallet->amount += $request->amount;
            } elseif ($request->account_type == 'demo') {
                $wallet->amount_demo += $request->amount;
            }
            $wallet->save();

            Transaction::create([ 
                'user_id' => $id,
                'credit' => $request->amount,
                'debit' => 0,
                'balance' => $wallet->amount, // أو $wallet->amount_demo حسب النوع
                'type' => 'Deposit',
                'amount_type' => $request->account_type,
                'created_by' => 'admin',
                'note' => $request->notes,
                'is_approval' => 1,
                'approve_at' => Carbon::now(),
                'request_at' => Carbon::now(),
            ]);

            DB::commit(); 
            return redirect()->back()->with('success', 'Amount added successfully!');
        } catch (\Exception $e) {
            DB::rollBack(); 
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function subtractAmount(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'account_type' => 'required|in:live,demo',
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction(); 

        try {
            $wallet = Wallet::where('user_id', $id)->first();

            if (!$wallet) {
                throw new \Exception('Wallet not found for this user.');
            }

            if ($request->account_type == 'live') {
                if ($wallet->amount < $request->amount) {
                    throw new \Exception('Insufficient live balance.');
                }
                $wallet->amount -= $request->amount;
            } elseif ($request->account_type == 'demo') {
                if ($wallet->amount_demo < $request->amount) {
                    throw new \Exception('Insufficient demo balance.');
                }
                $wallet->amount_demo -= $request->amount;
            }
            $wallet->save();

            Transaction::create([ 
                'user_id' => $id,
                'credit' => 0,
                'debit' => $request->amount,
                'balance' => $wallet->amount, // أو $wallet->amount_demo حسب النوع
                'type' => 'Withdraw',
                'amount_type' => $request->account_type,
                'created_by' => 'admin',
                'note' => $request->notes,
                'is_approval' => 1,
                'approve_at' => Carbon::now(),
                'request_at' => Carbon::now(),
            ]);
            DB::commit(); 
            return redirect()->back()->with('success', 'Amount subtracted successfully!');
        } catch (\Exception $e) {
            DB::rollBack(); 
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function usdt()
    {
        $title = transWord('USDT');
        $pages = [
            [transWord('USDT'),'show_usdts']
        ];

        $usdts = USDT::all();

        return view($this->path.'usdts',compact('usdts','pages','title'));
    }

    public function approveUSDT($id)
    {
        $usdt = USDT::where('id',$id)->first();
        $usdt->is_approval = 1;
        $usdt->approve_at = Carbon::now();
        $usdt->save();
        return back()->with('success','USDT is approved successfully');
    }

    public function pendingDeposit() 
    {
        $title = transWord('Pending Deposit');
        $pages = [
            [transWord('Wallet'),'pendingDeposit']
        ];

        // $liveTransactions = Deposits::with(['user'])->where('is_approval', 0)->where('status', 'live')->orderBy('created_at', 'desc')->get();
        // $demoTransactions = Deposits::with(['user'])->where('is_approval', 0)->where('status', 'demo')->orderBy('created_at', 'desc')->get();
        $liveTransactions = Deposits::with(['user', 'approvedBy'])->where('is_approval', 0)->where('status', 'live')->latest()->get();
        $demoTransactions = Deposits::with(['user', 'approvedBy'])->where('is_approval', 0)->where('status', 'demo')->latest()->get();

        return view($this->path.'pendingDeposit',compact('liveTransactions','demoTransactions','pages','title'));
    }

    public function approvedDeposit() 
    {
        $title = transWord('Approved Deposit');
        $pages = [
            [transWord('Wallet'),'approvedDeposit']
        ];

        $liveTransactions = Deposits::with(['user','approvedBy'])->where('is_approval', 1)->where('status', 'live')->orderBy('created_at', 'desc')->get();
        $demoTransactions = Deposits::with(['user','approvedBy'])->where('is_approval', 1)->where('status', 'demo')->orderBy('created_at', 'desc')->get();

        return view($this->path.'approvedDeposit',compact('liveTransactions','demoTransactions','pages','title'));
    }

    public function pendingWithdrawal() 
    {
        $title = transWord('Pending Withdrawal');
        $pages = [
            [transWord('Wallet'),'pendingWithdrawal']
        ];

        // $liveTransactions = Withdraws::with(['user'])->where('is_approval', 0)->where('status', 'live')->orderBy('created_at', 'desc')->get();
        // $demoTransactions = Withdraws::with(['user'])->where('is_approval', 0)->where('status', 'demo')->orderBy('created_at', 'desc')->get();
        $liveTransactions = Withdraws::with(['user', 'approvedBy'])->where('is_approval', 0)->where('status', 'live')->latest()->get();
        $demoTransactions = Withdraws::with(['user', 'approvedBy'])->where('is_approval', 0)->where('status', 'demo')->latest()->get();
        return view($this->path.'pendingWithdrawal',compact('liveTransactions','demoTransactions','pages','title'));
    }

    public function approvedWithdrawal() 
    {
        $title = transWord('Approved Withdrawal');
        $pages = [
            [transWord('Wallet'),'approvedWithdrawal']
        ];

        $liveTransactions = Withdraws::with(['user','approvedBy'])->where('is_approval', 1)->where('status', 'live')->orderBy('created_at', 'desc')->get();
        $demoTransactions = Withdraws::with(['user','approvedBy'])->where('is_approval', 1)->where('status', 'demo')->orderBy('created_at', 'desc')->get();
        return view($this->path.'approvedWithdrawal',compact('liveTransactions','demoTransactions','pages','title'));
    }



}
