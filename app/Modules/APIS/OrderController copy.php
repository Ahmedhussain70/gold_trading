<?php

namespace App\Modules\APIS;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Http\Resources\OrderResource;
use Blogs\Models\Blog;
use Illuminate\Http\Request;
use App\Modules\Orders\Models\Order;
use Product\Models\Product;
use Wallet\Models\Wallet;
use Wallet\Models\Transaction;

class OrderController extends Controller
{
    //  public function store(Request $request){
    //     $currenMode = auth()->user()->mode;
    //     $wallet = Wallet::where('user_id',auth()->user()->id)->first();
    //     if($currenMode == "live"){
    //         $currentWallet = $wallet->amount;
    //     }elseif($currenMode == "demo"){
    //         $currentWallet = $wallet->amount_demo;
    //     }

    //     $totalPrice = $request->price * $request->qty;
    //     if($totalPrice > $currentWallet){
    //         return response()->json([
    //             'success' => false,
    //             'message' => transWord("You don't have enough balance"),
    //             'result' => null,
    //         ],400);
    //     }else{
    //         $newWallet = $currentWallet - $totalPrice;
    //         if($currenMode == "live"){
    //             $wallet->amount = $newWallet;
                
    //         }elseif($currenMode == "demo"){
    //             $wallet->amount_demo = $newWallet;
    //         }
    //         $wallet->save();

    //         Transaction::create([
    //             'user_id' => auth()->user()->id,
    //             'credit' => 0,
    //             'debit' => $totalPrice,
    //             'balance' => $newWallet,
    //             'type' => 'buy',
    //             'amount_type' => $currenMode,
    //             'created_by' => 'client',
    //             'note' => transWord("Order is done successfully"),
    //             'is_approval' => 1,
    //             'approve_at' => now(),
    //             'request_at' => now(),
    //         ]);

    //         $order = Order::create([
    //             'user_id' => auth()->user()->id,
    //             "metal" => $request->metal,
    //             "currency" => $request->currency,
    //             "prev_close_price" => $request->prev_close_price,
    //             "open_price" => $request->open_price,
    //             "low_price" => $request->low_price,
    //             "high_price" => $request->high_price,
    //             "open_time" => $request->open_time,
    //             "price" => $request->price,
    //             "ch" => $request->ch,
    //             "chp" => $request->chp,
    //             "ask" => $request->ask,
    //             "bid" => $request->bid,
    //             "price_gram_24k" => $request->price_gram_24k,
    //             "price_gram_22k" => $request->price_gram_22k,
    //             "price_gram_21k" => $request->price_gram_21k,
    //             "price_gram_20k" => $request->price_gram_20k,
    //             "price_gram_18k" => $request->price_gram_18k,
    //             "price_gram_16k" => $request->price_gram_16k,
    //             "price_gram_14k" => $request->price_gram_14k,
    //             "price_gram_10k" => $request->price_gram_10k,
    //             "qty" => $request->qty,
    //             "stop_loss" => $request->stop_loss,
    //             "sell_when_price" => $request->sell_when_price,
    //             "take_profit" => $request->take_profit,
    //             "type" => "trade",
    //             "mode" => $currenMode,
    //         ]);
    
    //         return response()->json([
    //             'success' => true,
    //             'message' => transWord("Order is done successfully"),
    //             'result' => $order,
    //         ],200);

    //     }
    // }
    
    // public function trades(){
    //     $orders = Order::where('user_id',auth()->user()->id)->where('type','trade')->get();
    //     $data = OrderResource::collection($orders);
    //     return response()->json([
    //         'success' => true, 
    //         'message' => transWord("Trades"),
    //         'result' => $data,
    //     ],200);
    // }
    
    // public function update(Request $request){
    //     $request->validate([
    //         'order_id' => 'required'
    //     ]);
        
        
    //     $order = Order::where('id',$request->order_id)->first();
    //     if($order){
    //         $order->update([
    //         "metal" => $request->metal,
    //         "currency" => $request->currency,
    //         "prev_close_price" => $request->prev_close_price,
    //         "open_price" => $request->open_price,
    //         "low_price" => $request->low_price,
    //         "high_price" => $request->high_price,
    //         "open_time" => $request->open_time,
    //         "price" => $request->price,
    //         "ch" => $request->ch,
    //         "chp" => $request->chp,
    //         "ask" => $request->ask,
    //         "bid" => $request->bid,
    //         "price_gram_24k" => $request->price_gram_24k,
    //         "price_gram_22k" => $request->price_gram_22k,
    //         "price_gram_21k" => $request->price_gram_21k,
    //         "price_gram_20k" => $request->price_gram_20k,
    //         "price_gram_18k" => $request->price_gram_18k,
    //         "price_gram_16k" => $request->price_gram_16k,
    //         "price_gram_14k" => $request->price_gram_14k,
    //         "price_gram_10k" => $request->price_gram_10k,
    //         "qty" => $request->qty,
    //         "stop_loss" => $request->stop_loss,
    //         "sell_when_price" => $request->sell_when_price,
    //         "take_profit" => $request->take_profit,
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Trade is updated successfully"),
    //         'result' => $order,
    //     ],200);
    //     }else{
    //         return response()->json([
    //         'success' => true,
    //         'message' => transWord("Trade is not found"),
    //         'result' => null,
    //     ],404);
    //     }
        
    // }
    
    // public function delete(Request $request){
    //     $request->validate([
    //         'order_id' => 'required'
    //     ]);
        
        
    //     $order = Order::where('id',$request->order_id)->first();
    //     if($order){
    //         $order->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Trade is deleted successfully"),
    //         'result' => $order,
    //     ],200);
    //     }else{
    //         return response()->json([
    //         'success' => true,
    //         'message' => transWord("Trade is not found"),
    //         'result' => null,
    //     ],404);
    //     }
        
    // }
    
    // public function sell(Request $request){
    //     $request->validate([
    //         'order_id' => 'required',
    //         'address' => 'required'
    //     ]);
        
        
    //     $order = Order::where('id',$request->order_id)->first();
    //     if($order){
    //         $order->address = $request->address;
    //         $order->type = 'order';
    //         $order->save();

    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Trade is sell successfully"),
    //         'result' => $order,
    //     ],200);
    //     }else{
    //         return response()->json([
    //         'success' => true,
    //         'message' => transWord("Trade is not found"),
    //         'result' => null,
    //     ],404);
    //     }
        
    // }

    public function store(Request $request)
    {
        $currenMode = auth()->user()->mode;
        $wallet = Wallet::where('user_id', auth()->user()->id)->first();
        $currentWallet = ($currenMode == "live") ? $wallet->amount : $wallet->amount_demo;

        $totalPrice = $request->price * $request->qty;
        if ($totalPrice > $currentWallet) {
            return response()->json([
                'success' => false,
                'message' => transWord("You don't have enough balance"),
                'result' => null,
            ], 400);
        } else {
            $newWallet = $currentWallet - $totalPrice;
            if ($currenMode == "live") {
                $wallet->amount = $newWallet;
            } elseif ($currenMode == "demo") {
                $wallet->amount_demo = $newWallet;
            }
            $wallet->save();

            Transaction::create([
                'user_id'      => auth()->user()->id,
                'credit'       => 0,
                'debit'        => $totalPrice,
                'balance'      => $newWallet,
                'type'         => 'buy',
                'amount_type'  => $currenMode,
                'created_by'   => 'client',
                'note'         => transWord("Order is done successfully"),
                'is_approval'  => 1,
                'approve_at'   => now(),
                'request_at'   => now(),
            ]);

            $order = Order::create([
                'user_id'            => auth()->user()->id,
                "metal"              => $request->metal,
                "currency"           => $request->currency,
                "prev_close_price"   => $request->prev_close_price,
                "open_price"         => $request->open_price,
                "low_price"          => $request->low_price,
                "high_price"         => $request->high_price,
                "open_time"          => $request->open_time,
                "price"              => $request->price,
                "ch"                 => $request->ch,
                "chp"                => $request->chp,
                "ask"                => $request->ask,
                "bid"                => $request->bid,
                "price_gram_24k"     => $request->price_gram_24k,
                "price_gram_22k"     => $request->price_gram_22k,
                "price_gram_21k"     => $request->price_gram_21k,
                "price_gram_20k"     => $request->price_gram_20k,
                "price_gram_18k"     => $request->price_gram_18k,
                "price_gram_16k"     => $request->price_gram_16k,
                "price_gram_14k"     => $request->price_gram_14k,
                "price_gram_10k"     => $request->price_gram_10k,
                "qty"                => $request->qty,
                "stop_loss"          => $request->stop_loss,
                "sell_when_price"    => $request->sell_when_price,
                "take_profit"        => $request->take_profit,
                "type"               => "trade", // نوع الطلب الابتدائي "trade" يعني غير مكتمل
                "mode"               => $currenMode,
            ]);

            return response()->json([
                'success' => true,
                'message' => transWord("Order is done successfully"),
                'result'  => $order,
            ], 200);
        }
    }

    public function trades()
    {
        $orders = Order::where('user_id', auth()->user()->id)
                       ->where('type', 'trade')
                       ->get();
        $data = OrderResource::collection($orders);
        return response()->json([
            'success' => true,
            'message' => transWord("Trades"),
            'result'  => $data,
        ], 200);
    }

    public function update(Request $request)
    {
        $request->validate([
            'order_id' => 'required'
        ]);

        // التحقق من أن الطلب يخص المستخدم الحالي
        $order = Order::where('id', $request->order_id)
                      ->where('user_id', auth()->user()->id)
                      ->first();
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Trade not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        // منع تعديل الطلب إذا كان مكتمل (مثلاً إذا تغير النوع عن "trade")
        if ($order->type != 'trade') {
            return response()->json([
                'success' => false,
                'message' => transWord("Completed orders cannot be updated"),
                'result'  => null,
            ], 400);
        }

        if ($order->status == 'executed') {
            return response()->json([
                'success' => false,
                'message' => transWord("Executed orders cannot be updated"),
                'result'  => null,
            ], 400);
        }

        $order->update([
            "metal"              => $request->metal,
            "currency"           => $request->currency,
            "prev_close_price"   => $request->prev_close_price,
            "open_price"         => $request->open_price,
            "low_price"          => $request->low_price,
            "high_price"         => $request->high_price,
            "open_time"          => $request->open_time,
            "price"              => $request->price,
            "ch"                 => $request->ch,
            "chp"                => $request->chp,
            "ask"                => $request->ask,
            "bid"                => $request->bid,
            "price_gram_24k"     => $request->price_gram_24k,
            "price_gram_22k"     => $request->price_gram_22k,
            "price_gram_21k"     => $request->price_gram_21k,
            "price_gram_20k"     => $request->price_gram_20k,
            "price_gram_18k"     => $request->price_gram_18k,
            "price_gram_16k"     => $request->price_gram_16k,
            "price_gram_14k"     => $request->price_gram_14k,
            "price_gram_10k"     => $request->price_gram_10k,
            "qty"                => $request->qty,
            "stop_loss"          => $request->stop_loss,
            "sell_when_price"    => $request->sell_when_price,
            "take_profit"        => $request->take_profit,
        ]);

        return response()->json([
            'success' => true,
            'message' => transWord("Trade is updated successfully"),
            'result'  => $order,
        ], 200);
    }

    public function delete(Request $request)
    {
        $request->validate([
            'order_id' => 'required'
        ]);

        // التحقق من ملكية الطلب
        $order = Order::where('id', $request->order_id)
                      ->where('user_id', auth()->user()->id)
                      ->first();
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Trade not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        // منع حذف الطلب إذا كان مكتمل
        if ($order->type != 'trade') {
            return response()->json([
                'success' => false,
                'message' => transWord("Completed orders cannot be deleted"),
                'result'  => null,
            ], 400);
        }

        if ($order->status == 'executed') {
            return response()->json([
                'success' => false,
                'message' => transWord("Executed orders cannot be deleted"),
                'result'  => null,
            ], 400);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => transWord("Trade is deleted successfully"),
            'result'  => $order,
        ], 200);
    }

    public function sell(Request $request)
    {
        $request->validate([
            'order_id' => 'required',
            'address'  => 'required'
        ]);

        // التحقق من ملكية الطلب
        $order = Order::where('id', $request->order_id)
                      ->where('user_id', auth()->user()->id)
                      ->first();
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => transWord("Trade not found or unauthorized"),
                'result'  => null,
            ], 404);
        }

        // منع عملية البيع إذا كان الطلب غير صالح (مثلاً إذا لم يكن من نوع "trade")
        if ($order->type != 'trade') {
            return response()->json([
                'success' => false,
                'message' => transWord("Order is already completed or sold"),
                'result'  => null,
            ], 400);
        }

        $order->address = $request->address;
        $order->type = 'order'; // تغيير الحالة إلى طلب مكتمل/منفذ
        $order->save();

        return response()->json([
            'success' => true,
            'message' => transWord("Trade is sold successfully"),
            'result'  => $order,
        ], 200);
    }
}
