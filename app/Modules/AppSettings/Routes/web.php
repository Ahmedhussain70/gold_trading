<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web','auth']], function() {
    $namespace = "AppSettings\Controllers";
    Route::namespace($namespace)->group(function () {
        Route::group(['prefix' => LaravelLocalization::setLocale(),'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]], function(){
            Route::prefix("backend/app-settings")->group(function () {
                Route::get('/view', 'AppSettingController@index')->name('appsettings');
                Route::put('/update', 'AppSettingController@update')->name('update_appsettings');
            });
        });
    });
});
