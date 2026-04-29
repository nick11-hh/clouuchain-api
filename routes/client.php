<?php

use App\Http\Controllers\Client\AuthController;
use App\Http\Controllers\Client\BalanceController;
use App\Http\Controllers\Client\ConfigureController;
use App\Http\Controllers\Client\FortySeasController;
use App\Http\Controllers\Client\RechargeApplyController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\ShopController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\ShoppingCartController;

Route::namespace('Client')->middleware('tenant')->group(function() {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', 'AuthController@register');
    Route::post('forgot-password', 'AuthController@forgotPassword');
    Route::get('get-captcha', 'AuthController@getCaptcha');
    Route::get('/verification-code/email', 'AuthController@emailVerificationCode'); //发送注册邮箱验证码
    Route::get('language-list', [AuthController::class, 'getLanguageList']);
    Route::get('phone-area-code-list', [AuthController::class, 'getPhoneAreaCodeList']);
    Route::get('get-ip-location', [AuthController::class, 'getIpLocation']);



    Route::post('send-reset-password-email', [\App\Http\Controllers\Client\AuthController::class, 'sendResetPasswordEmail']);
    Route::post('reset-password-by-email-link', [\App\Http\Controllers\Client\AuthController::class, 'resetPasswordByEmailLink']);

    //无需登录可访问的热销产品相关接口
    Route::prefix('admin-goods')->group(function () {
        Route::get('/category', [\App\Http\Controllers\Client\AdminGoodsController::class, 'getCategoryList']);
        Route::get('/goods-list', [\App\Http\Controllers\Client\AdminGoodsController::class, 'getGoodsList']);
        Route::get('/get-detail-by-spu/{spu}', [\App\Http\Controllers\Client\AdminGoodsController::class, 'getGoodsDetailBySpu']);
    });

    //无需登录即可访问的1688产品相关接口
    Route::prefix('1688')->group(function () {
        //设置限制1分钟可请求120次，约等于1秒请求2次
        Route::middleware(['throttle:120,1'])->group(function () {
            Route::get('product-list', [\App\Http\Controllers\Client\Y1688Controller::class, 'getProductList']);
        });
    });

    //无需登录可访问的渠道物流价格查询接口
    Route::prefix('express-fee-query')->group(function () {
        Route::post('/', [\App\Http\Controllers\Client\ExpressPriceController::class, 'adminQuery']);
        Route::get('/warehouses', [\App\Http\Controllers\Client\WarehouseAddressController::class, 'filterList']);
    });
    //国家管理
    Route::prefix('countries')->group(function () {
        Route::get('/', [\App\Http\Controllers\Client\ExpressLineController::class, 'getCountriesList']);
        Route::get('/world', [\App\Http\Controllers\Client\HomeController::class, 'worldCountries']);
        Route::get('/search', [\App\Http\Controllers\Admin\ExpressLineController::class, 'searchCountry']);
    });


    Route::middleware('auth:client')->group(function () {
        Route::post('refresh-token', [AuthController::class, 'refreshToken']);
        Route::get('menu-tree', 'AuthController@getMenuTree');

        //图片上传
        Route::prefix('upload')->group(function () {
            Route::post('/images', [\App\Http\Controllers\Client\UploadController::class, 'uploadImages']);
            Route::post('/files', [\App\Http\Controllers\Client\UploadController::class, 'uploadFiles']);
        });

        Route::prefix('user')->group(function () {
            Route::get('/', 'UserController@index');
            Route::post('/', 'UserController@store');
            Route::put('/{id}', 'UserController@update');
            Route::post('/update-status', 'UserController@updateStatus');
            Route::post('/change-password/{id}', 'UserController@changePassword');
            Route::delete('/', 'UserController@deletes');
        });

        Route::prefix('user-group')->group(function () {
            Route::get('/', 'UserGroupController@index');
            Route::get('/{id}', 'UserGroupController@show');
            Route::post('/', 'UserGroupController@store');
            Route::put('/{id}', 'UserGroupController@update')->where(['id' => '[0-9]+']);
            Route::delete('/', 'UserGroupController@deletes');
            Route::get('/permissions/{id}', 'UserGroupController@getPermissions');
            Route::put('/permissions/{id}', 'UserGroupController@updatePermissions');
        });

        // 店铺管理
        Route::prefix('shop')->group(function () {
            Route::get('/', [ShopController::class, 'index']);
            Route::get('/{id}', [ShopController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::post('/', [ShopController::class, 'store']);
            Route::put('/{id}', [ShopController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::put('/enable', [ShopController::class, 'enable']);
            Route::delete('/', [ShopController::class, 'destroy']);
            Route::put('/revoke-authorization/{id}', [ShopController::class, 'revokeAuthorization']);
            Route::post('/auth', [ShopController::class, 'oauth']);
            Route::get('/tiktok-auth-url', [ShopController::class, 'getTiktokAuthUrl']);
            Route::get('/platform-list', [ShopController::class, 'getPlatformList']);
            Route::post('/zid-auth-url', [ShopController::class, 'zidOAuth']); //获取zid授权链接
        });

        // 购物车
        Route::prefix('shopping-cart')->group(function () {
            Route::get('/', [ShoppingCartController::class, 'index']);
            Route::post('/add', [ShoppingCartController::class, 'add']);
            Route::post('/submit-order', [ShoppingCartController::class, 'storeOrder']);
            Route::delete('/', [ShoppingCartController::class, 'deletes']);
        });

        // 订单管理
        Route::prefix('order')->group(function () {
            Route::get('/pull', [OrderController::class, 'pullPlatformOrders']);
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/get-status-count', [OrderController::class, 'statusCount']);
            Route::put('/ask/quote', [OrderController::class, 'askQuote']);
            Route::put('/payment', [OrderController::class, 'payment']);
            Route::post('/refund', [OrderController::class, 'orderRefund']);//订单退款
            Route::put('/cancel', [OrderController::class, 'cancelOrder']);
            Route::post('/', [OrderController::class, 'store']);
            Route::post('/{id}/fulfillment/request', [OrderController::class, 'fulfillmentRequest']);
            Route::post('/import', [OrderController::class, 'import']);
            Route::get('/get-order-pay-detail/{id}', [OrderController::class, 'orderPayDetail']);
            Route::put('/update-address', [OrderController::class, 'updateAddress']);
            Route::delete('/delete-items', [OrderController::class, 'deleteItems']);//删除订单产品
            // 支付配置
            Route::get('/pay-method', [OrderController::class, 'payMethod']);
            Route::get('/default-amount', [OrderController::class, 'defaultAmount']);
            Route::post('/restore-items', [OrderController::class, 'restoreItems']);//恢复删除后的订单产品

            Route::get('/{id}', [OrderController::class, 'detail'])->where(['id' => '[0-9]+']); //详情

            Route::post('/tracking-info', [\App\Http\Controllers\Client\ExpressOrderController::class, 'getTrackingInfo']);

            Route::post('/get-ids', [\App\Http\Controllers\Client\OrderController::class, 'getIds']); //获取订单ID合集

            Route::get('/export', [\App\Http\Controllers\Client\OrderController::class, 'export']);

            Route::post('/{id}/buy-again', [\App\Http\Controllers\Client\OrderController::class, 'buyAgain']);  // 再买一单
        });

        // 热销产品库
        Route::prefix('admin-goods')->group(function () {
            Route::get('/goods-detail/{id}', 'AdminGoodsController@getGoodsDetail');
            Route::post('/add-client-goods/{id}', 'AdminGoodsController@addToClientGoods');
        });

        // 选中的产品
        Route::prefix('client-goods')->group(function () {
            Route::get('/', 'ClientGoodsController@index');
            Route::get('/{id}', 'ClientGoodsController@show');
            Route::post('/', 'ClientGoodsController@store');
            Route::put('/{id}', 'ClientGoodsController@update')->where(['id' => '[0-9]+']);
            Route::delete('/', 'ClientGoodsController@deletes');
            Route::post('/publish', 'ClientGoodsController@publish');
            Route::post('/publish/batch', 'ClientGoodsController@publishBatch');
        });

        // 充值审核记录
        Route::prefix('recharge-apply')->group(function () {
            Route::get('/', 'RechargeApplyController@index');
            Route::get('/{id}', 'RechargeApplyController@show')->where(['id' => '[0-9]+']);
            Route::post('/', 'RechargeApplyController@store');
            Route::put('/{id}', 'RechargeApplyController@update')->where(['id' => '[0-9]+']);
            Route::get('/balance', 'RechargeApplyController@balance');
            Route::get('/credit-card-recharge', [RechargeApplyController::class, 'creditCardRecharge']);
        });

        // 用户余额流水
        Route::prefix('balance')->group(function () {
            Route::get('/', 'BalanceController@balance');
            Route::get('/payment-list', 'BalanceController@paymentList');
            Route::get('/records', 'BalanceController@records');
            Route::get('/transfer-records', 'BalanceController@transferRecords');
            Route::post('/payment', 'BalanceController@payment');
        });

        // 40Seas支付平台
        Route::prefix('forty-seas')->group(function () {
            Route::post('/checkout', [FortySeasController::class, 'checkout']);

        });

        // 线上产品
        Route::prefix('platform-product')->group(function () {
            Route::get('/', 'PlatformProductController@index');
            Route::post('/sync-shop/{shopId}', 'PlatformProductController@syncShopProduct');
            Route::post('/sync-product/{productId}', 'PlatformProductController@syncOneProduct');
            Route::delete('/delete/{productId}', 'PlatformProductController@deleteProduct');
            Route::post('/sync-product-all', [\App\Http\Controllers\Client\PlatformProductController::class, 'syncPlatformProductAll']);
        });


        // 推广返佣
        Route::prefix('agent')->group(function () {
            Route::get('/withdraw', 'CommissionController@withdrawList');
            Route::get('/commission', 'CommissionController@commissionList');
            Route::get('/invite', 'CommissionController@inviteList');
            Route::get('/type-list', 'CommissionController@withdrawTypeList');
            Route::post('/withdraw', 'CommissionController@withdrawApply');
        });

        // 1688平台产品
        Route::prefix('1688')->group(function () {
            //Route::get('product-list', 'Y1688Controller@getProductList');
            Route::get('image-search', 'Y1688Controller@imageSearch');
            Route::get('detail/{id}', 'Y1688Controller@detail');
            Route::post('claim/{id}', 'Y1688Controller@claim');
            Route::get('global/auth/url', 'Y1688Controller@getAuthUrl');
            Route::post('create/order', 'Y1688Controller@createCrossOrder');
            Route::get('get-product-freight', 'Y1688Controller@getProductFreight');
        });

        // 首页数据
        Route::prefix('home')->group(function () {
            Route::get('order-statistics', 'HomeController@orderStatistics');
            Route::get('product-statistics', 'HomeController@productStatistics');
            Route::get('income-statistics', 'HomeController@incomeStatistics');
            Route::get('order-total-statistics', 'HomeController@orderTotalStatistics');
            Route::get('custom-info', 'HomeController@getCustomInfo');
            Route::post('update-custom', 'HomeController@updateCustom');
            Route::post('update-email', 'HomeController@updateEmail');
            Route::post('update-avatar', 'HomeController@updateAvatar');
            Route::post('update-password', 'HomeController@updatePassword');
            Route::get('get-custom-config', 'HomeController@getCustomConfig');
            Route::post('save-custom-config', 'HomeController@saveCustomConfig');
            Route::get('today-statistics', 'HomeController@todayStatistics');
            Route::get('revenue-statistics', [\App\Http\Controllers\Client\HomeController::class, 'revenueDataStatistics']);
        });


        /*Route::prefix('express-fee-query')->group(function () {
            Route::get('/warehouses', [\App\Http\Controllers\Client\WarehouseAddressController::class, 'filterList']);
        });*/
        // 寻源报价
        Route::prefix('resources')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\OrderResourcesController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Client\OrderResourcesController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Client\OrderResourcesController::class, 'update']);
            Route::delete('/', [\App\Http\Controllers\Client\OrderResourcesController::class, 'del']);
            Route::put('/submit/{id}', [\App\Http\Controllers\Client\OrderResourcesController::class, 'submit']);
            Route::put('/accept/{id}', [\App\Http\Controllers\Client\OrderResourcesController::class, 'accept']);
            Route::get('/status/count', [\App\Http\Controllers\Client\OrderResourcesController::class, 'statusCount']);
            Route::post('/batch-change-status', [\App\Http\Controllers\Client\OrderResourcesController::class, 'batchChangeStatus']);

        });

        //仓库管理
        Route::prefix('warehouse-address')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\WarehouseAddressController::class, 'index']);
        });

        // 库存
        Route::prefix('client-stock')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\WarehouseStockController::class, 'index']);
        });

        // 寻源报价沟通
        Route::prefix('consult')->group(function () {
            Route::post('/', [\App\Http\Controllers\Client\ConsultController::class, 'store']);
            Route::get('/', [\App\Http\Controllers\Client\ConsultController::class, 'index']);
            Route::get('/{order_id}', [\App\Http\Controllers\Client\ConsultController::class, 'show']);
            Route::put('/mark/{id}', [\App\Http\Controllers\Client\ConsultController::class, 'mark']);
        });

        // 汇率管理
        Route::prefix('exchange-rate')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\ExchangeRateController::class, 'getRates']);
        });

        //系统消息
        Route::prefix('ctu-user-message')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\CTUUserMessageController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Client\CTUUserMessageController::class, 'show']);
            Route::put('/read', [\App\Http\Controllers\Client\CTUUserMessageController::class, 'read']);
            Route::put('/destroy', [\App\Http\Controllers\Client\CTUUserMessageController::class, 'destroy']);
            Route::put('/read-all', [\App\Http\Controllers\Client\CTUUserMessageController::class, 'readAll']);
            Route::put('/destroy-read-all', [\App\Http\Controllers\Client\CTUUserMessageController::class, 'destroyReadAll']);
        });

        Route::prefix('application')->group(function () {
            Route::get('/create', [\App\Http\Controllers\Client\ApplicationController::class, 'create']);
            Route::get('/auth/info', [\App\Http\Controllers\Client\ApplicationController::class, 'getAuthInfo']);
        });

        Route::prefix('product-quote')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\ProductQuoteController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Client\ProductQuoteController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::get('/count', [\App\Http\Controllers\Client\ProductQuoteController::class, 'count']);
            Route::get('/product-list', [\App\Http\Controllers\Client\ProductQuoteController::class, 'getProductList']);
            Route::post('/{id}/save', [\App\Http\Controllers\Client\ProductQuoteController::class, 'saveQuote']);
            Route::post('/{id}/save-logistics-channel', [\App\Http\Controllers\Client\ProductQuoteController::class, 'saveLogisticsChannel']);
            Route::get('/sku-price', [\App\Http\Controllers\Client\ProductQuoteController::class, 'getSkuQuotePrice']);
            Route::post('/request-quote', [\App\Http\Controllers\Client\ProductQuoteController::class, 'requestQuote']);
            Route::post('/request-quote/batch', [\App\Http\Controllers\Client\ProductQuoteController::class, 'batchRequestQuote']);
            Route::post('/confirm-quote', [\App\Http\Controllers\Client\ProductQuoteController::class, 'confirmQuote']);
            Route::get('/quote-detail/{id}', [\App\Http\Controllers\Client\ProductQuoteController::class, 'quoteDetail'])->where(['id' => '[0-9]+']);
        });

        //客户地址管理
        Route::prefix('custom-address')->group(function () {
            Route::get('/', [App\Http\Controllers\Client\CustomAddressController::class, 'index']);
            Route::get('/{id}', [App\Http\Controllers\Client\CustomAddressController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::post('/', [App\Http\Controllers\Client\CustomAddressController::class, 'store']);
            Route::put('/{id}', [App\Http\Controllers\Client\CustomAddressController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::delete('/', [App\Http\Controllers\Client\CustomAddressController::class, 'deletes']);
            Route::post('set-default/{id}', [App\Http\Controllers\Client\CustomAddressController::class, 'setDefault'])->where(['id' => '[0-9]+']);
        });

        //售后工单相关
        Route::prefix('work-order')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\AfterSalesWorkOrderController::class, 'index']); //工单列表
            Route::post('/', [\App\Http\Controllers\Client\AfterSalesWorkOrderController::class, 'store']); //新增工单
            Route::get('/typeList', [\App\Http\Controllers\Client\AfterSalesWorkOrderController::class, 'getTypeList']); //获取工单类型
        });

        Route::prefix('invoice')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\InvoiceController::class, 'index']); //发票列表
            Route::get('/{id}', [\App\Http\Controllers\Client\InvoiceController::class, 'detail'])->where(['id' => '[0-9]+']); //发票详情
            Route::put('/{id}', [\App\Http\Controllers\Client\InvoiceController::class, 'update'])->where(['id' => '[0-9]+']); //更新发票信息
            Route::get('/source_type', [\App\Http\Controllers\Client\InvoiceController::class, 'getSourceTypeList']); //获取来源类型
            Route::post('/request', [\App\Http\Controllers\Client\InvoiceController::class, 'requestInvoice']); //申请发票
        });


        Route::prefix('custom')->group(function () {

            //发票地址相关
            Route::get('/invoice-address', [\App\Http\Controllers\Client\CustomInvoiceAddressController::class, 'getInvoiceAddress']); //获取发票地址
            Route::post('/save-invoice-address', [\App\Http\Controllers\Client\CustomInvoiceAddressController::class, 'saveInvoiceAddress']); //保存发票地址
        });
    });


    // 1688授权回调
    Route::prefix('1688')->group(function () {
        Route::get('global/auth', 'Y1688Controller@auth');
    });

    // 店铺授权回调
    /*Route::prefix('shopify')->group(function() {
        Route::post('/authenticate', [ShopController::class, 'authenticate']);
//        Route::get('/destroy', [ShopController::class, 'delShop']);
        Route::get('/auth/{uuid}', [ShopController::class, 'installAuth']);
        Route::get('/redirect/{uuid}', [ShopController::class, 'redirect']);
        Route::post('/carrier-service/callback', [ShopController::class, 'carrierServiceCallback']);  // shopify 平台的物流运费
        // shopify  Webhook
        Route::prefix('customer')->middleware('auth.shopify.webhook')->group(function () {
            Route::post('/clear', [ShopController::class, 'clearCustomerData']);
            Route::post('/destroy', [ShopController::class, 'destroyCustomer']);
            Route::post('/data', [ShopController::class, 'customerData']);
        });

        Route::prefix('webhook')->middleware('auth.shopify.webhook')->group(function () {
            Route::post('/{uuid}', [ShopController::class, 'shopifyWebhook']);
        });

        Route::prefix('callback')->group(function () {
            Route::post('fulfillment_order_notification', [ShopController::class, 'fulfillmentOrderNotification']);
        });

    });*/

    //tiktok相关接口
    Route::prefix('tiktok')->group(function () {
        Route::get('redirect/{uuid}', [ShopController::class, 'tiktokRedirect']);
        Route::post('authenticate', [ShopController::class, 'tiktokAuthenticate']);
    });

    //salla电商平台相关接口
    Route::prefix('salla')->group(function () {
        Route::get('/redirect', [ShopController::class, 'sallaRedirect']);
        Route::post('/authenticate', [ShopController::class, 'sallaAuthenticate']);
        Route::post('/webhook', [ShopController::class, 'sallaWebhook']);
    });

    //zid电商平台相关接口
    Route::prefix('zid')->group(function () {
        Route::get('/redirect', [ShopController::class, 'zidRedirect']);
        Route::post('/authenticate', [ShopController::class, 'zidAuthenticate']);
        Route::post('/webhook', [ShopController::class, 'zidWebhook']);
    });

    Route::get('/paypal/callback/{uuid}', 'BalanceController@paypalCallBack');

    // 获取系统配置
    Route::get('system-config', [ConfigureController::class, 'getSystemConfig']);

    // 开放api
    Route::middleware(['open_api_check', 'open_api'])->prefix('open-api')->group(function () {
        Route::prefix('test')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\TestController::class, 'index']);
        });
        // 订单管理
        Route::prefix('order')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
        });
        // 店铺管理
        Route::prefix('shop')->group(function () {
            Route::get('/', [ShopController::class, 'index']);
        });
        // 热销产品库
        Route::prefix('goods')->group(function () {
            Route::get('/list', 'AdminGoodsController@getGoodsList');
            Route::get('/detail/{id}', 'AdminGoodsController@getGoodsDetail');
        });
        //国家管理
        Route::prefix('countries')->group(function () {
            Route::get('/', [\App\Http\Controllers\Client\ExpressLineController::class, 'getCountriesList']);
            Route::get('/search', [\App\Http\Controllers\Admin\ExpressLineController::class, 'searchCountry']);
        });
    });
    // 开放api获取授权
    Route::middleware('access_token')->group(function () {
        Route::post('/oauth/token', [\Laravel\Passport\Http\Controllers\AccessTokenController::class, 'issueToken']);
    });


});

