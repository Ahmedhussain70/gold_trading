<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web','auth']], function() {
    $namespace = "Product\Controllers";
    Route::namespace($namespace)->group(function () {
        Route::group(['prefix' => LaravelLocalization::setLocale(),'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]], function(){
            Route::prefix("backend/products")->group(function () {
                Route::get('/', 'ProductController@index')->name('show_product');
                Route::get('/edit/{product}', 'ProductController@edit')->name('edit_product');
                Route::get('/delete/{product}', 'ProductController@delete')->name('delete_product');
                Route::post('/store', 'ProductController@store')->name('store_product');
                Route::post('/update/{product}', 'ProductController@update')->name('update_product');

            });
        });
    });
});
