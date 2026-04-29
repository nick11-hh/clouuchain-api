<?php

use App\Http\Controllers\Open\OpenAuthController;
use Illuminate\Support\Facades\Route;

Route::namespace('Open')->group(function () {
    // 开放api获取授权
    Route::prefix('oauth')->group(function() {
        Route::post('/token', [OpenAuthController::class, 'token']);
    });
    Route::middleware('open_api_check')->group(function () {
        //热销商品
        Route::prefix('hot-goods')->group(function () {
            // 商品分类
            Route::get('/category', [\App\Http\Controllers\Open\GoodsController::class, 'getCategoryList']);
            // 热销商品列表
            Route::get('/goods-list', [\App\Http\Controllers\Open\GoodsController::class, 'getGoodsList']);
            // 热销商品详情
            Route::get('/get-detail-spu', [\App\Http\Controllers\Open\GoodsController::class, 'getGoodsDetailBySpu']);
        });
        //中国热卖商品
        Route::prefix('1688-goods')->group(function () {
            // 商品分类
            Route::get('/category', [\App\Http\Controllers\Open\Goods1688Controller::class, 'getCategoryList']);
            // 热销商品列表
            Route::get('/goods-list', [\App\Http\Controllers\Open\Goods1688Controller::class, 'getGoodsList']);
            // 热销商品详情
            Route::get('/get-detail-spu', [\App\Http\Controllers\Open\Goods1688Controller::class, 'getGoodsDetailBySpu']);
        });

        //订单相关
        Route::prefix('order')->group(function () {
            //订单推送
            Route::post('/push', [\App\Http\Controllers\Open\OrderController::class, 'push']);
            //获取订单信息
            Route::get('/get-order-info', [\App\Http\Controllers\Open\OrderController::class, 'getOrderInfo']);
            //获取物流信息
            Route::get('/get-logistic-info', [\App\Http\Controllers\Open\OrderController::class, 'getLogisticInfo']);
        });
        
        //充值申请相关
        Route::prefix('recharge-apply')->group(function () {
            //充值申请列表
            Route::get('/', [\App\Http\Controllers\Open\RechargeApplyController::class, 'index']);
            //充值申请详情
            Route::get('/{id}', [\App\Http\Controllers\Open\RechargeApplyController::class, 'show']);
        });
    });
});