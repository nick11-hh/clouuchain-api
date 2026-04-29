<?php

use App\Http\Controllers\Admin\StockUpController;
use App\Http\Controllers\Client\FortySeasController;
use Illuminate\Support\Facades\Route;

Route::namespace('Client')->group(function() {

    Route::prefix('shopify')->group(function() {
        Route::post('/authenticate', [\App\Http\Controllers\Client\ShopController::class, 'authenticate'])->middleware('auth:client');

        # 新的路由不在使用uuid作为租户判断，而是使用客户端域名
        Route::get('/auth/{uuid}', [\App\Http\Controllers\Client\ShopController::class, 'installAuth']);
        Route::get('/redirect/{uuid}', [\App\Http\Controllers\Client\ShopController::class, 'redirect']);
        Route::get('/auth', [\App\Http\Controllers\Client\ShopController::class, 'installAuth']);
        Route::get('/redirect', [\App\Http\Controllers\Client\ShopController::class, 'redirect']);

        Route::post('/carrier-service/callback', [\App\Http\Controllers\Client\ShopController::class, 'carrierServiceCallback']);  // shopify 平台的物流运费

        // shopify  Webhook
        Route::prefix('customer')->middleware('auth.shopify.webhook')->group(function () {
            Route::post('/clear', [\App\Http\Controllers\Client\ShopController::class, 'clearCustomerData']);
            Route::post('/destroy', [\App\Http\Controllers\Client\ShopController::class, 'destroyCustomer']);
            Route::post('/data', [\App\Http\Controllers\Client\ShopController::class, 'customerData']);
        });

        // webhook 对接
        Route::post('/webhook', [\App\Http\Controllers\Client\ShopController::class, 'shopifyWebhook'])->middleware('auth.shopify.webhook');
        Route::post('/webhook/{uuid}', [\App\Http\Controllers\Client\ShopController::class, 'shopifyWebhook'])->middleware('auth.shopify.webhook');

        Route::prefix('callback')->group(function () {
            Route::post('fulfillment_order_notification', [\App\Http\Controllers\Client\ShopController::class, 'fulfillmentOrderNotification']);
        });

    });
    // 40Seas
    Route::prefix('forty-seas')->group(function () {
        Route::any('/webhook', [FortySeasController::class, 'webhook']);
    });
    // 企微
    Route::prefix('wecom')->group(function () {
        Route::get('/webhook', [StockUpController::class, 'webhookVerification']);
        Route::post('/webhook', [StockUpController::class, 'webhookApproval']);

    });
});
