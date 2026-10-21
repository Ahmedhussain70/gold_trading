<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web','auth']], function() {
    $namespace = "Wallet\Controllers";
    Route::namespace($namespace)->group(function () {
        Route::group(['prefix' => LaravelLocalization::setLocale(),'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]], function(){
            Route::prefix("backend/wallets")->group(function () {
                Route::get('/', 'WalletController@index')->name('show_wallets');
                Route::get('/transactions/{id}', 'WalletController@transactions')->name('show_wallets_transactions');
                Route::get('/transactions/demo/{id}', 'WalletController@transactionsDemo')->name('show_wallets_transactions_demo');
                Route::get('/approve-transactions/{id}', 'WalletController@approveTransactions')->name('approve_wallets_transactions');
                Route::get('/approve-deposit/{id}', 'WalletController@approveDeposit')->name('approve_deposit');
                Route::get('/approve-withdraw/{id}', 'WalletController@approveWithdraw')->name('approve_withdraw');
                Route::post('/add-amount/{id}', 'WalletController@addAmount')->name('wallet.add.amount');
                Route::post('/subtract-amount/{id}', 'WalletController@subtractAmount')->name('wallet.subtract.amount');
                Route::get('/show_usdt', 'WalletController@usdt')->name('show_usdts');
                Route::get('/approve_usdts/{id}', 'WalletController@approveUSDT')->name('approve_usdts');
                Route::get('/pending-deposit', 'WalletController@pendingDeposit')->name('pending_deposit');
                Route::get('/approved-deposit', 'WalletController@approvedDeposit')->name('approved_deposit');
                Route::get('/pending-withdrawal', 'WalletController@pendingWithdrawal')->name('pending_withdrawal');
                Route::get('/approved-withdrawal', 'WalletController@approvedWithdrawal')->name('approved_withdrawal');


                Route::get('payment-methods',           'PaymentMethodController@index')->name('payment_methods.index');
                Route::post('payment-methods',          'PaymentMethodController@store')->name('payment_methods.store');
                Route::post('payment-methods/{id}',     'PaymentMethodController@update')->name('payment_methods.update');
                Route::post('payment-methods/{id}/toggle', 'PaymentMethodController@toggle')->name('payment_methods.toggle');
                Route::delete('payment-methods/{id}',   'PaymentMethodController@destroy')->name('payment_methods.destroy');
            });
        });
    });
});
