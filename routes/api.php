<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\APIS\OrderController;
use App\Modules\APIS\FinanceController;


Route::middleware(['localization'])->group(function(){
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::group(["namespace"=>"App\Modules\APIS"],function (){
        Route::post('/auth/login', 'Auth\UserController@login');
        Route::post('/auth/register', 'Auth\UserController@createNewAccount');
        
        // Forgot Password (Public)
        Route::post('/auth/forgot-password', 'Auth\UserController@forgotPassword');            // يرسل OTP
        Route::post('/auth/verify-reset-otp', 'Auth\UserController@verifyResetOtp');          // (اختياري) يتحقق من الكود
        Route::post('/auth/reset-password', 'Auth\UserController@resetPasswordWithOtp');  
        
        Route::middleware(['auth:sanctum'])->group(function(){
            Route::post('/auth/logout', 'Auth\UserController@logout');
            Route::get('/auth/profile', 'Auth\UserController@profile');
            Route::post('/auth/update-profile', 'Auth\UserController@updateProfile');
            Route::post('/auth/change-mode', 'Auth\UserController@changeMode');
            
            Route::get('/news','NewsController@index');
            Route::get('/single-news/{id}','NewsController@single');
            Route::get('/pages','PageController@index');
            Route::get('/single-pages/{id}','PageController@single');
            Route::get('/sliders','SliderController@index');
            Route::get('/socialmedia','SocialMediaController@index');
            Route::get('/categories','CategoryController@index');
            Route::get('/currencies','ProductController@currencies');
            Route::get('/products/{cat?}/{currency?}','ProductController@index');
            Route::get('/faq','FaqController@faq');
            Route::get('/tickets','TicketController@tickets');
            Route::post('/make-tickets','TicketController@makeTicket');
            Route::get('/ticket-responses/{id}','TicketController@responses');
            Route::post('/make-response','TicketController@makeResponse');


            // =====================
            // Finance Routes
            // =====================
            Route::get('/finance/currencies', [FinanceController::class, 'currencies']);
            Route::get('/wallet', [FinanceController::class, 'wallet']);

            Route::post('/make-deposit', [FinanceController::class, 'makeDeposit']);
            Route::post('/make-withdraw', [FinanceController::class, 'makeWithdraw']);

            Route::get('/report-deposit', [FinanceController::class, 'reportDeposit']);
            Route::get('/report-withdraw', [FinanceController::class, 'reportWithdraw']);

            Route::post('/pay-usdt', [FinanceController::class, 'payUSDT']);

            Route::get('/finance/transactions', [FinanceController::class, 'transactions']);
            Route::post('/finance/convert-currency', [FinanceController::class, 'convertCurrency']);

            Route::get('/payment-methods/deposit', [FinanceController::class, 'paymentMethodsForDeposit']);
            Route::get('/payment-methods/withdraw-options', [FinanceController::class, 'paymentMethodsForWithdraw']);


            // =====================
            // Order Routes (Trading lifecycle)
            // =====================
            Route::post('/order/store', [OrderController::class, 'store']);
            Route::get('/order/trades', [OrderController::class, 'trades']);
            Route::get('/order/trades-by-metal', [OrderController::class, 'tradesByMetal']);
            Route::get('/order/allTrades', [OrderController::class, 'allTrades']);
            Route::get('/order/pending-deliveries', [OrderController::class, 'pendingDeliveries']);
            Route::get('/order/{id}', [OrderController::class, 'show'])->whereNumber('id');
            Route::get('/order/closed-trades', [OrderController::class, 'closedTrades']);
            Route::get('/order/pending', [OrderController::class, 'pendingOrders']);

            Route::put('/order/update', [OrderController::class, 'update']);
            Route::post('/order/sell', [OrderController::class, 'sell']);

            Route::post('/trade/close', [OrderController::class, 'closeTrade']);
            Route::post('/order/request-delivery', [OrderController::class, 'requestDelivery']);
            Route::post('/delivery/cancel', [OrderController::class, 'cancelDeliveryRequest']);
            // Route::post('/trade/cancel', [OrderController::class, 'cancelTrade']);
            Route::post('/order/cancel', [OrderController::class, 'cancelOrder']);
            Route::get('order/cancelled', [OrderController::class, 'cancelledOrders']);

            Route::get('/orders',  [OrderController::class, 'orders']);
            Route::get('/orderss', [OrderController::class, 'orderss']);
            Route::get('/trades',  [OrderController::class, 'trades']);
            Route::get('/tradess', [OrderController::class, 'tradess']);

            Route::get('/settings/commission-rate', [OrderController::class, 'getCommissionRate']);
            Route::get('/settings/exchange-rate', [OrderController::class, 'exchangeRateInfo']);

            Route::get('/portfolio/summary', [OrderController::class, 'portfolioSummary']);

        });
        Route::middleware(['localization','price.key','throttle:120,1'])
            ->get('/price', 'PriceController@index');

        Route::middleware(['localization','price.key','throttle:60,1'])
            ->get('/price/stream', 'PriceController@stream');

        // Route::get('/ping/stream', function () {
        //     @ini_set('zlib.output_compression', 0);
        //     @ini_set('output_buffering', 'off');
        //     @ini_set('implicit_flush', 1);
        //     @set_time_limit(0);

        //     return response()->stream(function () {
        //         $i=0;
        //         while (true) {
        //             echo "event: pong\n";
        //             echo 'data: {"i":'.($i++).',"ts":"'.now()->toIso8601String().'"}'."\n\n";
        //             @ob_flush(); @flush();
        //             sleep(1);
        //         }
        //     }, 200, [
        //         'Content-Type' => 'text/event-stream',
        //         'Cache-Control'=> 'no-cache',
        //         'X-Accel-Buffering' => 'no',
        //     ]);
        // });

    });
});

// Test WebSocket Broadcast

// WebSocket Configuration for Mobile
Route::get('/ws/config', function () {
    return response()->json([
        'success' => true,
        'result' => [
            'host' => request()->getHost(),
            'port' => 6001,
            'key'  => 'newGoldKey2026',
            'encrypted' => false,
            'channel' => 'gold-price',        // ✅ صح
            'event' => 'PriceUpdated',        // ✅ صح
        ],
    ]);
});
