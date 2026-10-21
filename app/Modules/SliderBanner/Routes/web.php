<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web','auth']], function() {
    $namespace = "SliderBanner\Controllers";
    Route::namespace($namespace)->group(function () {
        Route::group(['prefix' => LaravelLocalization::setLocale(),'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]], function(){
            Route::prefix("backend/slider_banners")->group(function () {
                Route::get('/all', 'SliderBannerController@index')->name('slider_banners');
                Route::get('/edit/{banner}', 'SliderBannerController@edit')->name('edit_slider_banners');
                Route::post('/update/{banner}', 'SliderBannerController@update')->name('update_slider_banners');
                Route::get('/create', 'SliderBannerController@create')->name('create_slider_banners');
                Route::post('/store', 'SliderBannerController@store')->name('store_slider_banners');
                Route::get('/destroy/{banner}', 'SliderBannerController@destroy')->name('destroy_slider_banners');
            });
        });
    });
});
