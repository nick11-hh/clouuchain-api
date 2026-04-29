<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Woocommerce')->middleware('wc')->name('wc.')->prefix('wc')->group(function () {

    //https://example.com/wp-json/wc/v3/orders
    Route::name('v3.')->prefix('v3')->group(function () {

        Route::name('orders.')->prefix('orders')->group(function () {
            Route::get('/test', function () {
                return 'Hello World11';
            });

            Route::get('/', 'OrderController@index');#订单列表
            Route::get('/{id}', 'OrderController@show')->where(['id' => '[0-9]+']);#订单详情
            Route::put('/{id}', 'OrderController@update')->where(['id' => '[0-9]+']);#更新订单
            Route::post('/{id}/NOTES', 'OrderController@addNotes')->where(['id' => '[0-9]+']);#新增订单备注
            Route::get('/{id}/NOTES', 'OrderController@notesList')->where(['id' => '[0-9]+']);#备注列表
            Route::get('/{id}/NOTES/{noteid}', 'OrderController@notesInfo')->where(['id' => '[0-9]+', 'noteid' => '[0-9]+']);#备注详情
        });

        Route::name('products.')->prefix('products')->group(function () {

            Route::get('/', 'ProductController@index');#产品列表
            Route::get('/{id}', 'ProductController@show')->where(['id' => '[0-9]+']);#产品详情
        });
    });

});
