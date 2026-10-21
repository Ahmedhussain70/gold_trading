<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web','auth']], function() {
    $namespace = "Blogs\Controllers";
    Route::namespace($namespace)->group(function () {
        Route::group(['prefix' => LaravelLocalization::setLocale(),'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]], function(){
            Route::prefix("backend/blogs")->group(function () {
                Route::get('/all', 'BlogController@index')->name('blogs');
                Route::get('/create', 'BlogController@create')->name('create_blogs');
                Route::post('/store', 'BlogController@store')->name('store_blogs');
                Route::get('/edit/{blog}', 'BlogController@edit')->name('edit_blogs');
                Route::post('/update/{blog}', 'BlogController@update')->name('update_blogs');
                Route::get('/show/{blog}', 'BlogController@show')->name('show_blogs');
                Route::get('/delete/{blog}', 'BlogController@destroy')->name('destroy_blogs');
                Route::post('/upload', 'BlogController@upload')->name('upload_blogs');
            });
        });
    });
});
