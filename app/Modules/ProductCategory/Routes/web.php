<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web','auth']], function() {
    $namespace = "ProductCategory\Controllers";
    Route::namespace($namespace)->group(function () {
        Route::group(['prefix' => LaravelLocalization::setLocale(),'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]], function(){
            Route::prefix("backend/product-category")->group(function () {
                Route::get('/', 'ProductCategoryController@index')->name('show_product_category');
                Route::get('/edit/{product_catgeory}', 'ProductCategoryController@edit')->name('edit_product_category');
                Route::get('/delete/{product_catgeory}', 'ProductCategoryController@delete')->name('delete_product_category');
                Route::post('/store', 'ProductCategoryController@store')->name('store_product_category');
                Route::post('/update/{product_catgeory}', 'ProductCategoryController@update')->name('update_product_category');

            });
        });
    });
});
