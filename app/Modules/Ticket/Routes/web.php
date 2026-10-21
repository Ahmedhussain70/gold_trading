<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web','auth']], function() {
    $namespace = "Ticket\Controllers";
    Route::namespace($namespace)->group(function () {
        Route::group(['prefix' => LaravelLocalization::setLocale(),'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]], function(){
            Route::prefix("backend/tickets")->group(function () {
                Route::get('/', 'TicketController@index')->name('show_tickets');
                Route::post('/store', 'TicketController@store')->name('store_tickets');
                Route::get('/responses/{ticket}', 'TicketController@responses')->name('responses_tickets');
                Route::post('/add-responses/{ticket}', 'TicketController@addResponse')->name('add_response_tickets');
                Route::get('/delete/{ticket}', 'TicketController@delete')->name('delete_tickets');

            });
        });
    });
});
