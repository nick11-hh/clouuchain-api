<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\Admin\BalanceRecordController;
use App\Http\Controllers\Admin\CreditCardController;
use App\Http\Controllers\Admin\CreditCardRechargeRecordController;
use App\Http\Controllers\Admin\CustomController;
use App\Http\Controllers\Admin\ExchangeRateController;
use App\Http\Controllers\Admin\GoodsSupplierController;
use App\Http\Controllers\Admin\OutboundOrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PickingOrderController;
use App\Http\Controllers\Admin\StockUpController;
use App\Http\Controllers\Admin\SystemConfigController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\InboundOrderController;

Route::namespace('Admin')->middleware('tenant')->group(function () {
    Route::post('login', [\App\Http\Controllers\Admin\AuthController::class, 'login']);
    Route::get('captcha', [\App\Http\Controllers\Admin\AuthController::class, 'getCaptcha']);
    Route::get('language-list', [\App\Http\Controllers\Admin\AuthController::class, 'getLanguageList']);
    Route::get('phone-area-code-list', [\App\Http\Controllers\Admin\AuthController::class, 'getPhoneAreaCodeList']);
    Route::get('/clear-opcache/dfa16c36dbe6470417f0c8c8d674bcdc', [\App\Http\Controllers\Admin\AuthController::class, 'clearOpcache']);

    Route::middleware(['auth:admin', 'request_params_log'])->group(function () {

        Route::get('/menu-tree', [\App\Http\Controllers\Admin\AuthController::class, 'getMenuTree']);

        Route::get('fulfillment-config', [SystemConfigController::class, 'fulfillmentConfig']);

        //图片上传
        // Route::prefix('upload')->group(function () {
        //     Route::post('/images', 'UploadController@uploadImages');
        // });

        //用户管理
        Route::prefix('users')->group(function () {
            Route::get('/', 'UserController@index');
        });

        // 客户管理
        Route::prefix('custom')->group(function () {
            Route::get('/', 'CustomController@index');
            Route::get('/simple', 'CustomController@simple');
            Route::get('/{id}', 'CustomController@show')->where(['id' => '[0-9]+']);
            Route::post('/', 'CustomController@store');
            Route::put('/{id}', 'CustomController@update');
            Route::post('/update-status', 'CustomController@updateStatus');
            Route::delete('/', 'CustomController@deletes');
            // 调整信用额度
            Route::post('/update-credit-line', [CustomController::class, 'updateCreditLine']);
            // 调整冻结额度
            Route::post('/update-frozen-limit', 'CustomController@updateFrozenLimit');
            Route::post('/update-auto-payment', 'CustomController@updateAutoPayment');
            // 客户钱包信息
            Route::get('/wallet/{id}', 'CustomController@getWalletShow');
            // 钱包统计
            Route::get('/wallet-count', 'CustomController@getWalletCount');
            Route::post('/assign-staff', [CustomController::class, 'assignStaff']);//分配员工
            Route::post('/export', [CustomController::class, 'export']);//导出员工
            Route::get('/get-invite-url', [CustomController::class, 'getInviteUrl']); //获取邀请码
            Route::get('/get-list-with-shop-list', [CustomController::class, 'getCustomWithShopList']); //获取客户列表携带店铺列表数据
            Route::post('/login-client', [CustomController::class, 'loginClient']); //登录客户端
            Route::get('/quote-config/{id}', [CustomController::class, 'getQuoteConfig']); //客户报价配置


            Route::get('/logs', [\App\Http\Controllers\Admin\AdminOperationLogController::class, 'index']); //日志
            Route::get('/logs/opt-type', [\App\Http\Controllers\Admin\AdminOperationLogController::class, 'optTypeList']); //操作类型列表

            Route::get('/open-platform-auth/{id}', [\App\Http\Controllers\Admin\CustomController::class, 'OpenPlatformAuth']); //开放平台授权
        });

        // 客户分组管理
        Route::prefix('custom-group')->group(function () {
            Route::get('/', 'CustomGroupController@index');
            Route::get('/{id}', 'CustomGroupController@show');
            Route::post('/', 'CustomGroupController@store');
            Route::put('/{id}', 'CustomGroupController@update');
            Route::delete('/', 'CustomGroupController@deletes');
        });

        Route::prefix('custom-promotion')->group(function () {
            Route::get('/', 'MarketingPromotionController@index');
            Route::put('/record', 'MarketingPromotionController@record');
            Route::get('/type-list', 'MarketingPromotionController@withdrawTypeList');
            Route::post('/{customId}/withdraw', 'MarketingPromotionController@withdraw');
        });

        // 订单管理
        Route::prefix('order')->group(function () {
            Route::post('/', [\App\Http\Controllers\Admin\OrderController::class, 'index']);
            Route::post('/batch-matching-logistics', [\App\Http\Controllers\Admin\OrderController::class, 'batchMatchingLogistics']);
            Route::get('/scan', [\App\Http\Controllers\Admin\OrderController::class, 'scanOrder']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::get('/order-quote-info/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'orderQuoteInfo'])->where(['id' => '[0-9]+']);
            Route::put('/{id}/remark', [\App\Http\Controllers\Admin\OrderController::class, 'addOrderRemark']);
            Route::put('/quote', [\App\Http\Controllers\Admin\OrderController::class, 'quote']);
            Route::put('/set/paymented', [\App\Http\Controllers\Admin\OrderController::class, 'setPaymented']);
            Route::put('/set/logistics', [\App\Http\Controllers\Admin\OrderController::class, 'setLogistics']);
            Route::put('/remove/print', [\App\Http\Controllers\Admin\OrderController::class, 'removePrint']);
            Route::get('/print/lable/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'printLable']);
            Route::get('/lable/merge', [\App\Http\Controllers\Admin\OrderController::class, 'mergePdf']);
            Route::post('/fulfillment/create/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'createFulfillment']);
            Route::post('/fulfillment/request/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'requestFulfillment']);
            Route::post('/handle-send', [\App\Http\Controllers\Admin\OrderController::class, 'handleSend']);
            Route::post('/count/status', [\App\Http\Controllers\Admin\OrderController::class, 'statusCount']);
            Route::get('/count/sub-status/{type}', [\App\Http\Controllers\Admin\OrderController::class, 'subStatusCount']); //子状态统计数量
            Route::get('/change/status/count', [\App\Http\Controllers\Admin\OrderController::class, 'changeStatusCount']);
            Route::put('/change/logistics', [\App\Http\Controllers\Admin\OrderController::class, 'changeLogistics']);
            Route::put('/set/quote', [\App\Http\Controllers\Admin\OrderController::class, 'setQuote']);
            Route::put('/update/tracking', [\App\Http\Controllers\Admin\OrderController::class, 'updateTracking']);
            Route::post('/fulfillment/open/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'openFulfillment']);
            Route::post('/fulfillment/cancel/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'cancelFulfillment']);
            Route::post('/platform/pull', [\App\Http\Controllers\Admin\OrderController::class, 'pullOrders']);
            Route::post('/mapping-quote/{orderId}', [\App\Http\Controllers\Admin\OrderController::class, 'orderMappingQuote']);
            Route::put('/move/in/stock', [\App\Http\Controllers\Admin\OrderController::class, 'moveToInStock']);
            Route::put('/move/out/stock', [\App\Http\Controllers\Admin\OrderController::class, 'moveToOutStock']);
            Route::get('/channel/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'getChannelByOrderId']);
            Route::put('/shipping/addr/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'updateShippingAddr']);
            Route::put('/declaration/{order_item_id}', [\App\Http\Controllers\Admin\OrderController::class, 'updateDeclaration']);
            Route::get('/export', [\App\Http\Controllers\Admin\OrderController::class, 'export']);
            Route::post('/import', [\App\Http\Controllers\Admin\OrderController::class, 'import']);
            Route::post('/hand/movement/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'handMovement']);
            Route::put('/hand/movement/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'updateHandCustoms']);
            Route::post('/print/logistics/lable', [\App\Http\Controllers\Admin\OrderController::class, 'printLogisticsLable']);
            Route::post('/print/send/lable', [\App\Http\Controllers\Admin\OrderController::class, 'printSendLable']);
            Route::post('/print/logistics/send/lable', [\App\Http\Controllers\Admin\OrderController::class, 'printLogisticsSendLable']);
            Route::post('/vendor-change-price', [\App\Http\Controllers\Admin\OrderController::class, 'vendorChangePrice']);
            Route::post('/get-payment-info', [\App\Http\Controllers\Admin\OrderController::class, 'getPaymentInfo']);
            Route::get('/get-exchange-rate-price', [\App\Http\Controllers\Admin\OrderController::class, 'getExchangeRatePrice']);
            Route::get('/get-exchange-rate', [\App\Http\Controllers\Admin\OrderController::class, 'getExchangeRate']);
            Route::delete('/delete-items', [\App\Http\Controllers\Admin\OrderController::class, 'deleteItems']);//删除订单产品
            Route::post('/restore-items', [\App\Http\Controllers\Admin\OrderController::class, 'restoreItems']);//恢复订单产品
            Route::get('/get-sku-quotation', [\App\Http\Controllers\Admin\OrderController::class, 'getSkuQuotation']);//根据订单ID跟skuID获取一客一价的产品报价
            // 批量匹配物流渠道
            Route::post('/getLogisticsChannels', [\App\Http\Controllers\Admin\OrderController::class, 'getLogisticsChannels']);
            // 批量匹配渠道报价金额
            Route::post('/getQuotedAmount', [\App\Http\Controllers\Admin\OrderController::class, 'getQuotedAmount']);
            // 批量保存
            Route::post('/batchSaveQuotes', [\App\Http\Controllers\Admin\OrderController::class, 'batchSaveQuotes']);

            Route::get('/stock-order-list', [\App\Http\Controllers\Admin\OrderController::class, 'getStockOrderList']);//获取备货订单列表
            Route::get('/stock-order-status-count', [\App\Http\Controllers\Admin\OrderController::class, 'stockOrderStatusCount']);//获取备货订单列表
            Route::post('/stock-order-to-inbound', [\App\Http\Controllers\Admin\OrderController::class, 'stockOrderToInbound']);//备货订单生成入库单

            Route::post('/batch-update-declaration', [\App\Http\Controllers\Admin\OrderController::class, 'batchUpdateDeclaration']);//批量更新报关信息
            Route::post('/set-shelve', [\App\Http\Controllers\Admin\OrderController::class, 'setShelve']);//设为搁置
            Route::post('/cancel-shelve', [\App\Http\Controllers\Admin\OrderController::class, 'cancelShelve']);//取消搁置
            Route::post('/set-not-shipping', [\App\Http\Controllers\Admin\OrderController::class, 'setNotShipping']);//设置不发货
            Route::post('/cancel-not-shipping', [\App\Http\Controllers\Admin\OrderController::class, 'cancelNotShipping']);//取消不发货
            Route::post('/batch-update-shipping-address', [\App\Http\Controllers\Admin\OrderController::class, 'batchUpdateShippingAddress']);//批量更新报关信息
            Route::post('/assign-staff', [\App\Http\Controllers\Admin\OrderController::class, 'assignStaff']);//分配员工

            Route::post('/sync-third-party-warehouse', [\App\Http\Controllers\Admin\OrderController::class, 'syncThirdPartyWarehouse']);
            Route::post('/push-third-party-warehouse', [\App\Http\Controllers\Admin\OrderController::class, 'pushThirdPartyWarehouse']); // 推送订单到第三方ERP（马帮）
            Route::get('/push-third-party-log', [\App\Http\Controllers\Admin\OrderController::class, 'getFulfillmentPushLogs']);  // 查看第三方ERP推送日志

            Route::get('/export-dianxiaomi-order', [\App\Http\Controllers\Admin\OrderController::class, 'exportDianxiaomiOrder']);
            Route::put('/batch-update-fulfillment-platform', [\App\Http\Controllers\Admin\OrderController::class, 'batchUpdateFulfillmentPlatform']);
            Route::put('/batch-update-remark', [\App\Http\Controllers\Admin\OrderController::class, 'batchUpdateRemark']);
            Route::post('/import-order-logistics', [\App\Http\Controllers\Admin\OrderController::class, 'importOrderLogistics']);//更新订单信息（店小秘）
            Route::post('/auto-quotation', [\App\Http\Controllers\Admin\OrderController::class, 'autoQuotation']);
            Route::post('/rollback-quote', [\App\Http\Controllers\Admin\OrderController::class, 'orderRollbackQuote']);//打回报价中
            Route::post('/refund', [\App\Http\Controllers\Admin\OrderController::class, 'orderRefund']);//订单退款
            Route::post('/set-disable-status', [\App\Http\Controllers\Admin\OrderController::class, 'setDisableStatus']);//禁止/恢复处理

            Route::post('/import-new', [\App\Http\Controllers\Admin\OrderController::class, 'importNew']); //导入订单新版
            Route::post('/supplement-fee', [\App\Http\Controllers\Admin\OrderController::class, 'supplementFee']); //补收费用
            Route::post('/sync-platform-fulfillment', [\App\Http\Controllers\Admin\OrderController::class, 'syncPlatformFulfillment']);//同步平台发货
            Route::post('/change-quote-price', [\App\Http\Controllers\Admin\OrderController::class, 'changeQuotePrice']); //调整报价
            Route::post('/restore', [\App\Http\Controllers\Admin\OrderController::class, 'restore']); //恢复订单

            Route::post('/get-ids', [\App\Http\Controllers\Admin\OrderController::class, 'getIds']); //获取订单ID合集

            Route::post('/move-to-quote', [\App\Http\Controllers\Admin\OrderController::class, 'abnormalMoveToQuote']); //订单异常处理
            Route::get('/data-json', [\App\Http\Controllers\Admin\OrderController::class, 'dataJson']); //订单状态列表
            Route::post('/cancel', [\App\Http\Controllers\Admin\OrderController::class, 'orderCancel']);//订单取消
            Route::post('/cancel-refund', [\App\Http\Controllers\Admin\OrderController::class, 'orderCancelAndRefund']); //订单取消并退款
            Route::post('/cancel-withdraw', [\App\Http\Controllers\Admin\OrderController::class, 'orderCancelWithdraw']); //订单取消撤回
            Route::post('/ignore-abnormal', [\App\Http\Controllers\Admin\OrderController::class, 'ignoreAbnormal']); //忽略异常订单

            // 获取当前报价信息
            Route::post('/{id}/generation-quote-info', [\App\Http\Controllers\Admin\OrderController::class, 'generationQuoteInfo']);
            // 订单运费预估
            Route::post('/shipping-cost-estimate', [\App\Http\Controllers\Admin\OrderController::class, 'shippingCostEstimate']);
            Route::post('/{id}/order-add-line-item', [\App\Http\Controllers\Admin\OrderController::class, 'orderAddLineItem']);
            // 更新订单商品数量
            Route::put('/update-line-item', [\App\Http\Controllers\Admin\OrderController::class, 'updateLineItem']);
            // 订单归档和取消归档
            Route::post('/archive', [\App\Http\Controllers\Admin\OrderController::class, 'archive']);
            Route::post('/rollback-archive', [\App\Http\Controllers\Admin\OrderController::class, 'rollbackArchive']);
            Route::post('/set-virtual/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'setVirtual']);

            Route::get('/test', [\App\Http\Controllers\Admin\OrderController::class, 'test']);
            // 订单导入运单号
            Route::post('import-logistics', [\App\Http\Controllers\Admin\OrderController::class, 'importLogistics']);
        });

        // 店铺管理
        Route::prefix('shop')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ShopController::class, 'index']);
            Route::get('/get-groups', [\App\Http\Controllers\Admin\ShopController::class, 'getGroups']);
            Route::post('/add-group', [\App\Http\Controllers\Admin\ShopController::class, 'addGroup']);
            Route::post('/update-group', [\App\Http\Controllers\Admin\ShopController::class, 'updateGroup']);
            Route::post('/batch-edit-shop-tax', [\App\Http\Controllers\Admin\ShopController::class, 'batchEditShopTax']);
            Route::delete('/groups', [\App\Http\Controllers\Admin\ShopController::class, 'deletesGroup']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\ShopController::class, 'update']);
            Route::get('/platform-list', [\App\Http\Controllers\Admin\ShopController::class, 'getPlatformList']);
            Route::post('/{id}/sync-order', [\App\Http\Controllers\Admin\ShopController::class, 'syncOrder']);
            Route::post('/{id}/manualSendEmail', [\App\Http\Controllers\Admin\ShopController::class, 'manualSendEmail']);
            Route::get('/{id}/setting', [\App\Http\Controllers\Admin\ShopController::class, 'getShopSetting']);
            Route::put('/{id}/setting', [\App\Http\Controllers\Admin\ShopController::class, 'saveShopSetting']);
        });

        Route::prefix('purchase-plan')->group(function() {
            Route::get('/', [\App\Http\Controllers\Admin\PurchasePlanController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\PurchasePlanController::class, 'store']);
            Route::put('/cancel', [\App\Http\Controllers\Admin\PurchasePlanController::class, 'cancel']);
            Route::get('/status', [\App\Http\Controllers\Admin\PurchasePlanController::class, 'statusCount']);
            Route::put('/status', [\App\Http\Controllers\Admin\PurchasePlanController::class, 'updateStatus']);
            Route::get('/skus', [\App\Http\Controllers\Admin\PurchasePlanController::class, 'getPlanSku']);
            Route::get('/suppliers/{sku_id}', [\App\Http\Controllers\Admin\PurchasePlanController::class, 'getSuppliersByGoodsSkuId']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\PurchasePlanController::class, 'show'])->where(['id' => '[0-9]+']);
        });

        // 采购管理
        Route::prefix('purchase')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::get('/scan', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'getScanData']);
            Route::get('/status', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'statusCount']);
            Route::post('/', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'store']);
            Route::put('/mark', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'markOrder']);
            Route::put('/set/url', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'setUrl']);
            Route::put('/set/shipment/info', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'setShipmentInfo']);
            Route::put('/confirm', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'confirm']);
            Route::post('/auto/create/order/{id}', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'autoPurchase']);
            Route::put('/sync/orders/status', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'syncPurchaseOrdersStatus']);
            Route::post('/stock/in-storage/{id}', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'purchaseStockInStorage']);
            Route::get('/match-order/{id}', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'getMatchOrderList']);
            Route::post('/order/deliver', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'orderDeliverByPurchase']);
            Route::put('/status', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'updateStatus']);
            Route::get('/logs/', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'getPurchaseOrderLogs']);

        });

        Route::prefix('purchase-account')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PurchaseAccountController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\PurchaseAccountController::class, 'store']);
            Route::get('/enable', [\App\Http\Controllers\Admin\PurchaseAccountController::class, 'getEnableAll']);
            Route::put('/enable/{id}', [\App\Http\Controllers\Admin\PurchaseAccountController::class, 'enable']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\PurchaseAccountController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\PurchaseAccountController::class, 'del']);
            Route::put('/cancel/{id}', [\App\Http\Controllers\Admin\PurchaseAccountController::class, 'cancel']);
        });

        // 员工组
        Route::prefix('group')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminGroupController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\AdminGroupController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\AdminGroupController::class, 'update']);
            Route::delete('/', [\App\Http\Controllers\Admin\AdminGroupController::class, 'deletes']);
            Route::get('/permissions/{id}', [\App\Http\Controllers\Admin\AdminGroupController::class, 'getPermissions']);
            Route::put('/permissions/{id}', [\App\Http\Controllers\Admin\AdminGroupController::class, 'updatePermissions']);
        });

        // 员工管理
        Route::prefix('admins')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\AdminController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'update']);
            Route::delete('/', [\App\Http\Controllers\Admin\AdminController::class, 'deletes']);
            Route::put('/', [\App\Http\Controllers\Admin\AdminController::class, 'enable']);
            Route::put('/modify/password/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'modifyPass']);
        });

        // 商品管理
        Route::prefix('goods')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\GoodsController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\GoodsController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::get('/spu-quotation-list', [\App\Http\Controllers\Admin\GoodsController::class, 'spuQuotationList']);
            Route::post('/', [\App\Http\Controllers\Admin\GoodsController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\GoodsController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::put('/update-status', [\App\Http\Controllers\Admin\GoodsController::class, 'updateStatus']);
            Route::put('/update-status-1688', [\App\Http\Controllers\Admin\GoodsController::class, 'update1688Status']);
            Route::put('/update-hot', [\App\Http\Controllers\Admin\GoodsController::class, 'updateHot']);
            Route::put('/update-1688', [\App\Http\Controllers\Admin\GoodsController::class, 'update1688']);
            Route::put('/mark-as-self-operated', [\App\Http\Controllers\Admin\GoodsController::class, 'markAsSelfOperated']);
            Route::delete('/', [\App\Http\Controllers\Admin\GoodsController::class, 'deletes']);

            Route::post('/batch-update-declaration', [\App\Http\Controllers\Admin\GoodsController::class, 'batchUpdateDeclaration']);//批量更新报关信息
            Route::post('/audit', [\App\Http\Controllers\Admin\GoodsController::class, 'auditGoods']);
            Route::post('/commit-audit', [\App\Http\Controllers\Admin\GoodsController::class, 'commitAuditGoods']);
            Route::post('/import', [\App\Http\Controllers\Admin\GoodsController::class, 'import']); //批量导入商品
            Route::put('/push-to-mabang/{id}', [\App\Http\Controllers\Admin\GoodsController::class, 'pushToMabang']); //推送到马帮
            Route::put('/sku-push-to-mabang/{id}', [\App\Http\Controllers\Admin\GoodsController::class, 'skuPushToMabang']); //推送到马帮
            Route::post('/calculate-quotation', [\App\Http\Controllers\Admin\GoodsController::class, 'calculateQuotation']);//计算产品报价

            Route::get('/sku-list', [\App\Http\Controllers\Admin\GoodsController::class, 'skuList']);
            Route::get('/sku/{id}', [\App\Http\Controllers\Admin\GoodsController::class, 'skuShow'])->where(['id' => '[0-9]+']);
            Route::put('/sku/{id}', [\App\Http\Controllers\Admin\GoodsController::class, 'skuUpdate'])->where(['id' => '[0-9]+']);
            Route::get('/sku-quotation-list', [\App\Http\Controllers\Admin\GoodsController::class, 'skuQuotationList']);
        });

        // 商品分类
        Route::prefix('goods-category')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\GoodsCategoryController::class, 'index']);
            Route::get('/tree', [\App\Http\Controllers\Admin\GoodsCategoryController::class, 'tree']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\GoodsCategoryController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Admin\GoodsCategoryController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\GoodsCategoryController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::put('/update-status', [\App\Http\Controllers\Admin\GoodsCategoryController::class, 'updateStatus']);
            Route::delete('/', [\App\Http\Controllers\Admin\GoodsCategoryController::class, 'deletes']);
        });

        // 供应商拜访记录
        Route::prefix('supplier-visits')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\SupplierVisitController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\SupplierVisitController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Admin\SupplierVisitController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\SupplierVisitController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\SupplierVisitController::class, 'destroy']);
            Route::post('/batch-delete', [\App\Http\Controllers\Admin\SupplierVisitController::class, 'batchDestroy']);
        });

        // 物流公司管理
        Route::prefix('express-companies')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'store']);
            Route::post('/enable', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'updateEnable']); //更新启用状态
            Route::get('/channels', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'channels']);
            Route::get('/channels/{express_companies_id}', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'channelsByCompanes']);
            // 获取指定物流商启用的渠道
            Route::get('/enabled-channels/{express_companies_id}', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'enabledChannelsList']);
            Route::post('/place', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'place']);
            Route::get('/lable/{id}', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'getLabel']);
            Route::get('/tracking/{id}', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'tracking']);
            Route::put('/channel/enable', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'enableChannel']);
            Route::get('/consignment/{order_id}', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'getDsConsignment']);
            Route::post('/refresh-channels', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'refreshChannels']);
            Route::get('/get-authorization', [\App\Http\Controllers\Admin\ExpressCompaniesController::class, 'getAuthorization']);
        });

        // 充值申请
        Route::prefix('recharge-apply')->group(function () {
            Route::get('/', 'RechargeApplyController@index');
            Route::get('/{id}', 'RechargeApplyController@show')->where(['id' => '[0-9]+']);
            Route::post('/', 'RechargeApplyController@store');
            Route::put('/audit/{id}', 'RechargeApplyController@audit');
            Route::post('/quick-recharge', 'RechargeApplyController@quickRecharge');
            Route::post('/revocation', 'RechargeApplyController@revocation');//撤销充值
            Route::get('/online-recharge', [App\Http\Controllers\Admin\RechargeApplyController::class, 'onlineRecharge']); //线上充值记录
            Route::post('/submit-check', [App\Http\Controllers\Admin\RechargeApplyController::class, 'submitCheck']); //提交核验
            Route::post('/export', [App\Http\Controllers\Admin\RechargeApplyController::class, 'export']);

            Route::get('/pay-method', [App\Http\Controllers\Admin\RechargeApplyController::class, 'chargePayMethodList']);
            Route::post('/pay-method', [App\Http\Controllers\Admin\RechargeApplyController::class, 'addChargePayMethod']);
            Route::put('/pay-method/{id}', [App\Http\Controllers\Admin\RechargeApplyController::class, 'updateChargePayMethod']);
            Route::put('/pay-method-status/{id}', [App\Http\Controllers\Admin\RechargeApplyController::class, 'updatePayMethodStatus']);
            Route::delete('/pay-method/{id}', [App\Http\Controllers\Admin\RechargeApplyController::class, 'deleteChargePayMethod']);
        });

        // 信用卡充值记录
        Route::prefix('credit-card-recharge-record')->group(function () {
            Route::get('/', [CreditCardRechargeRecordController::class, 'list']);
            Route::post('/submit-check', [CreditCardRechargeRecordController::class, 'submitCheck']);
        });

        //备货
        Route::prefix('stock-up')->group(function () {
            Route::post('/save-stock-up', [StockUpController::class,'saveStockUp']);
            Route::post('/save-stock-up-purchase', [StockUpController::class,'saveStockUpPurchase']);
            Route::post('/delete-stock-up', [StockUpController::class,'deleteStockUp']);
            Route::post('/accounting-reconciliation', [StockUpController::class,'accountingReconciliation']);
            Route::get('/list', [StockUpController::class, 'list']);
            Route::get('/process', [StockUpController::class, 'process']);
            Route::get('/export', [StockUpController::class, 'export']);
        });

        // 余额流水记录
        Route::prefix('balance-record')->group(function () {
            Route::get('/', [BalanceRecordController::class,'index']);
            Route::get('/{id}', 'BalanceRecordController@show')->where(['id' => '[0-9]+']);
            Route::post('/export', [App\Http\Controllers\Admin\BalanceRecordController::class, 'export']);
            Route::post('/manually-operate-balance', [App\Http\Controllers\Admin\BalanceRecordController::class, 'manuallyOperateBalance']);
            // 批量添加明细
            Route::put('/batch-add-breakdown', [App\Http\Controllers\Admin\BalanceRecordController::class, 'batchAddBreakdown']);
            Route::get('/source-type-list', [BalanceRecordController::class,'sourceTypeList']);//支付类型列表
        });

        // 支付配置
        Route::prefix('payments')->group(function () {
            // 在线支付
            Route::get('/payment/status', [PaymentController::class, 'getPaymentStatus']);
            Route::prefix('paypal')->group(function () {
                Route::get('/', 'PaymentController@getPaypalConfiguration');
                Route::put('/', 'PaymentController@updatePaypalConfiguration');
                Route::get('/status', 'PaymentController@getPaypalPaymentStatus');
                Route::put('/status/{status}', 'PaymentController@setPaypalPaymentStatus');
            });

            Route::prefix('/credit-card')->group(function () {
                Route::get('/all', [CreditCardController::class, 'all']);
                Route::post('/status', [CreditCardController::class, 'status']);
                Route::post('/update', [CreditCardController::class, 'update']);
            });

            // 转账支付
            Route::get('/', 'PaymentController@index');
            Route::get('/{id}', 'PaymentController@show');
            Route::post('/', 'PaymentController@store');
            Route::put('/{id}', 'PaymentController@update');
            Route::put('/{id}/status/{status}', 'PaymentController@setStatus');
            Route::put('/{id}/translate-data', 'PaymentController@updateTranslateData');
            Route::delete('/{id}', 'PaymentController@destroy');

            Route::prefix('account')->group(function () {
                Route::get('/{id}', 'PaymentController@showAccount');
                Route::get('/index/{id}', 'PaymentController@indexAccount');
                Route::post('/', 'PaymentController@storeAccount');
                Route::put('/{id}', 'PaymentController@updateAccount');
                Route::put('/{id}/translate-data', 'PaymentController@updateAccountTranslateData');
                Route::delete('/{id}', 'PaymentController@destroyAccount');
            });

            // 预设充值金额
            Route::get('/payment/default-amount', 'PaymentController@getDefaultAmounts');
            Route::post('/payment/default-amount', 'PaymentController@addDefaultAmount');
            Route::delete('/payment/default-amount/{id}', 'PaymentController@removeDefaultAmount');
        });

        // 支付方式配置
        Route::prefix('payment-setting')->group(function () {
            Route::get('/', 'PaymentSettingController@index');
            Route::get('/{id}', 'PaymentSettingController@show');
            Route::post('/', 'PaymentSettingController@store');
            Route::put('/{id}', 'PaymentSettingController@update')->where(['id' => '[0-9]+']);
            Route::put('/update-status', 'PaymentSettingController@updateStatus');
            Route::delete('/', 'PaymentSettingController@deletes');
        });

        // 提现申请
        Route::prefix('commission-withdraw')->group(function () {
            Route::get('/', 'CommissionWithdrawController@index');
            Route::get('/{id}', 'CommissionWithdrawController@show');
            Route::put('/{id}', 'CommissionWithdrawController@audit');
        });

        Route::prefix('collect-goods')->group(function () {
            Route::get('/', 'CollectGoodsController@index');
            Route::get('/{id}', 'CollectGoodsController@show');
            Route::put('/{id}', 'CollectGoodsController@update')->where(['id' => '[0-9]+']);
            Route::post('/collect', 'CollectGoodsController@collect');
            Route::post('/claim', 'CollectGoodsController@claim');
            Route::delete('/', 'CollectGoodsController@deletes');
            Route::post('/getDetail', 'CollectGoodsController@getDetail');
            Route::post('/collect-claim', 'CollectGoodsController@collectAndClaim');//采集并认领到产品库
        });

        // 首页数据
        Route::prefix('home')->group(function () {
            Route::get('todo-data', 'HomeController@todoData');
            Route::get('statistics-data', 'HomeController@statisticsData');
            Route::get('custom-statistics', 'HomeController@customStatistics');
            Route::get('recharge-statistics', 'HomeController@rechargeStatistics');
            Route::get('purchase-statistics', 'HomeController@purchaseStatistics');
            Route::get('hot-sale-goods', 'HomeController@hotSaleGoods');
            Route::get('apply-express-fail', 'HomeController@applyExpressFail');
            Route::get('admin-info', 'HomeController@getAdminUserInfo');
            Route::post('update-admin', 'HomeController@updateAdminUser');
            Route::post('update-password', 'HomeController@updatePassword');
        });

        //仓库地址管理
        Route::prefix('warehouse-address')->group(function () {
            Route::get('/', 'WarehouseAddressController@index');
            Route::get('/enable/all', [\App\Http\Controllers\Admin\WarehouseAddressController::class, 'getAllEnable']);
            Route::get('/simple', 'WarehouseAddressController@simple');
            Route::get('/{id}', 'WarehouseAddressController@show')->where(['id' => '[0-9]+']);
            Route::post('/', 'WarehouseAddressController@store');
            Route::put('/{id}', 'WarehouseAddressController@update')->where(['id' => '[0-9]+']);
            Route::delete('/{id}', 'WarehouseAddressController@delete');
            Route::put('/sort', 'WarehouseAddressController@sort');
            Route::get('/all', 'WarehouseAddressController@getAll');
            Route::put('/{id}/status/{status}', 'WarehouseAddressController@setStatus');
            Route::put('/{warehouseId}/custom-locations/{status}', 'GoodsAllocationController@updateCustomLocationConfig');  // 开启自定义修改货位

            //仓库货区
            Route::get('/{warehouseId}/goods-allocation-areas', 'GoodsAllocationController@index');
            Route::get('/{warehouseId}/goods-allocation-areas/{id}', 'GoodsAllocationController@show');
            Route::get('/{warehouseId}/goods-allocation-areas/{id}/details', 'GoodsAllocationController@details');
            Route::post('/{warehouseId}/goods-allocation-areas', 'GoodsAllocationController@store');
            Route::put('/{warehouseId}/goods-allocation-areas/{id}', 'GoodsAllocationController@update')->where(['id' => '[0-9]+']);
            Route::delete('/{warehouseId}/goods-allocation-areas/{id}', 'GoodsAllocationController@destroy');
            Route::put('/{warehouseId}/goods-allocation-areas/sort-index', 'GoodsAllocationController@updateIndex');
            Route::put('/{warehouseId}/goods-allocation-areas/reset-index', 'GoodsAllocationController@resetIndex');
            Route::put('/area-Unlock/{id}/status/{status}', 'GoodsAllocationController@areaUnlock');

            // 仓库货位
            Route::put('/{warehouseId}/goods-allocation-areas/location/{id}/status/{status}', 'GoodsAllocationController@setLockForWarehouse');
            Route::post('/goods-allocation-areas/{areaId}/locations', 'GoodsAllocationController@addLocations');
            Route::delete('/locations/{id}', 'GoodsAllocationController@destroyCustomLocation');
            Route::get('/locations', 'GoodsAllocationController@locationList');
            Route::get('/locations/areas', 'GoodsAllocationController@getNumberInWarehouse');
            Route::put('/locations/{id}/status/{status}', 'GoodsAllocationController@setLock');
            Route::get('/locations/areas/tree/{id}', 'GoodsAllocationController@getLocationAreaTree');
        });

        // 库存
        Route::prefix('warehouse-stock')->group(function () {
            Route::get('/', 'WarehouseStockController@index');
            Route::get('/{id}', 'WarehouseStockController@show')->where(['id' => '[0-9]+']);
            Route::get('/stock-items', 'WarehouseStockController@stockItems');
            Route::get('/records', 'WarehouseStockController@records');
            Route::get('/get-customer-stock/{id}', 'WarehouseStockController@getCustomerStock');//获取用户库存
        });

        // 国家
        Route::prefix('countries')->group(function () {
            Route::get('/', 'ExpressLineController@getCountriesList');
            Route::get('/enable', 'CountriesController@getEnableCountries');
        });

        // 盘点
        Route::prefix('inventory-stock')->group(function () {
            Route::get('/', 'InventoryStockController@index');
            Route::get('/{id}', 'InventoryStockController@show')->where(['id' => '[0-9]+']);
            Route::get('/map-data', 'InventoryStockController@mapData');
            Route::post('/', 'InventoryStockController@store');
            Route::put('/{id}/save', 'InventoryStockController@saveInventory');
            Route::put('/{id}/submit', 'InventoryStockController@submitInventory');
            Route::delete('/{id}/cancel', 'InventoryStockController@cancel');
        });

        // 1688
        Route::prefix('1688')->group(function () {
            Route::get('global/auth/url/{id}', [\App\Http\Controllers\Admin\AlibabaController::class, 'alibabaAuthorize']);
        });

        // 报价模板
        Route::prefix('quote-template')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\QuotationTemplateController::class, 'index']);
            Route::get('/list', [\App\Http\Controllers\Admin\QuotationTemplateController::class, 'list']);
            Route::post('/create', [\App\Http\Controllers\Admin\QuotationTemplateController::class, 'createTemplate']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\QuotationTemplateController::class, 'updateTemplate']);
            Route::put('/{id}/status/{status}', [\App\Http\Controllers\Admin\QuotationTemplateController::class, 'setStatus']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\QuotationTemplateController::class, 'deleteTemplate']);
        });

        // 渠道路线
        Route::prefix('express-lines')->group(function () {
            Route::prefix('third-party-multi-channel')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\ThirdPartyMultiChannelController::class, 'index']);
                Route::get('/{id}', [\App\Http\Controllers\Admin\ThirdPartyMultiChannelController::class, 'show']);
                Route::post('/', [\App\Http\Controllers\Admin\ThirdPartyMultiChannelController::class, 'store']);
                Route::put('/{id}', [\App\Http\Controllers\Admin\ThirdPartyMultiChannelController::class, 'update']);
                Route::delete('/{id}', [\App\Http\Controllers\Admin\ThirdPartyMultiChannelController::class, 'destroy']);
            });
            // 分区模板
            Route::prefix('region-templates')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'groupIndex']);
                Route::get('/{id}', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'groupShow']);
                Route::post('/', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'groupStore']);
                Route::put('/{id}', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'groupUpdate']);
                Route::delete('/{id}', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'groupDestroy']);

                Route::prefix('/{id}/regions')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'index']);
                    Route::get('/{tplId}', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'show']);
                    Route::post('/', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'store']);
                    Route::put('/{tplId}', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'update']);
                    Route::put('/{tplId}/status/{status}', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'setStatus']);
                    Route::delete('/{tplId}', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'destroy']);
                    Route::post('/parse', [\App\Http\Controllers\Admin\ExpressLineRegionTemplateController::class, 'parse']);
                });
            });
            // 销售价格
            Route::prefix('sale-prices')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\SalePriceController::class, 'index']);
                Route::get('/{id}', [\App\Http\Controllers\Admin\SalePriceController::class, 'show']);
                Route::post('/', [\App\Http\Controllers\Admin\SalePriceController::class, 'store']);
                Route::put('/{id}', [\App\Http\Controllers\Admin\SalePriceController::class, 'update']);
                Route::put('/{id}/status/{status}', [\App\Http\Controllers\Admin\SalePriceController::class, 'setStatus']);
                Route::delete('/{id}', [\App\Http\Controllers\Admin\SalePriceController::class, 'destroy']);
                Route::post('/{id}/copy', [\App\Http\Controllers\Admin\SalePriceController::class, 'copy']);
            });
            Route::put('/{id}/update-auth', [\App\Http\Controllers\Admin\ExpressLineController::class, 'updateAuth']);
            Route::get('/{id}/show-auth', [\App\Http\Controllers\Admin\ExpressLineController::class, 'showAuth']);
            Route::get('/groups', [\App\Http\Controllers\Admin\ExpressLineGroupController::class, 'index']);
            Route::get('/group-with-line-list', [\App\Http\Controllers\Admin\SalePriceController::class, 'expressGroupList']);
            Route::get('/warehouses', [\App\Http\Controllers\Admin\WarehouseAddressController::class, 'getAll']);
            Route::get('/simple-icon-list', [\App\Http\Controllers\Admin\ExpressLineIconController::class, 'getExpressLineIconList']);
            Route::put('/{id}/docking-config', [\App\Http\Controllers\Admin\ExpressLineController::class, 'updateDockingSetting']);
            Route::get('docking-types', [\App\Http\Controllers\Admin\ExpressLineController::class, 'getDockingTypes']);

            Route::get('/', [\App\Http\Controllers\Admin\ExpressLineController::class, 'index']);
            // 获取模板所有列表
            Route::get('/list', [\App\Http\Controllers\Admin\ExpressLineController::class, 'list']);
            // 导入
            Route::post('/import', [\App\Http\Controllers\Admin\ExpressLineController::class, 'importTemplate']);
            // 导出
            Route::post('/export', [\App\Http\Controllers\Admin\ExpressLineController::class, 'exportTemplate']);
            Route::get('/simple', [\App\Http\Controllers\Admin\ExpressLineController::class, 'simple']);
            Route::get('/reserved-number-express', [\App\Http\Controllers\Admin\ReservedOrderNumberController::class, 'getExpressCompanies']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\ExpressLineController::class, 'show']);
            Route::post('{id}/copy', [\App\Http\Controllers\Admin\ExpressLineController::class, 'copy']);
            Route::put('/{id}/status/{status}', [\App\Http\Controllers\Admin\ExpressLineController::class, 'setStatus']);
//            Route::post('status', [\App\Http\Controllers\Admin\ExpressLineController::class, 'setStatus']);
            Route::put('/{id}/recommend/{status}', [\App\Http\Controllers\Admin\ExpressLineController::class, 'setRecommend']);
            Route::delete('{id}', [\App\Http\Controllers\Admin\ExpressLineController::class, 'destroy']);
            Route::post('/groups', [\App\Http\Controllers\Admin\ExpressLineGroupController::class, 'store']);
            Route::put('/groups/{id}/status/{status}', [\App\Http\Controllers\Admin\ExpressLineGroupController::class, 'groupsStatus']);
            Route::put('/groups/{id}', [\App\Http\Controllers\Admin\ExpressLineGroupController::class, 'update']);
            Route::post('/groups/{id}/copy', [\App\Http\Controllers\Admin\ExpressLineGroupController::class, 'groupsCopy']);
            Route::delete('/groups/{id}', [\App\Http\Controllers\Admin\ExpressLineGroupController::class, 'destroy']);
            Route::post('/basic-config', [\App\Http\Controllers\Admin\ExpressLineConfigController::class, 'createBasicConfig']);
            Route::put('{id}/basic-config', [\App\Http\Controllers\Admin\ExpressLineConfigController::class, 'updateBasicConfig']);
            Route::get('{id}/basic-config', [\App\Http\Controllers\Admin\ExpressLineConfigController::class, 'getBasicConfig']);
            Route::get('/channel-code-list/{docking_type}', [\App\Http\Controllers\Admin\ExpressLineController::class, 'getChannelCodeList']);
            Route::post('/update-sort', [\App\Http\Controllers\Admin\ExpressLineController::class, 'updateSort']);
            // 获取已启用物流模板列表
            Route::get('/enabled-templates', [\App\Http\Controllers\Admin\ExpressLineController::class, 'getEnabledTemplatesList']);


            //计费配置
            Route::get('{id}/billing-config', [\App\Http\Controllers\Admin\ExpressLineConfigController::class, 'getBillingConfig']);
            Route::put('{id}/billing-config', [\App\Http\Controllers\Admin\ExpressLineConfigController::class, 'updateBillingConfig']);
            //渠道 分区管理
            Route::get('{id}/regions', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'index']);
            Route::get('{id}/regions/all', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'all']);
            Route::get('{id}/regions/{regionId}', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'show']);
            Route::post('{id}/regions', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'store']);
            Route::put('{id}/regions/{regionId}', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'update']);
            Route::put('{id}/regions/{regionId}/status/{status}', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'setStatus']);
//            Route::delete('{id}/regions/{regionId}', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'destroy']);
            Route::delete('{id}/regions/batch-delete', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'batchDestroy']);
            Route::put('{id}/regions/{regionId}/translate-data', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'updateTrans']);
            Route::put('{id}/regions/copy/{tmpId}', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'copy']);
            Route::put('{id}/regions/index', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'updateIndex']);
            Route::post('{id}/regions/import', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'importRegionsTable']);
            // 导出
            Route::post('{id}/regions/export', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'exportRegionsTable']);
            //渠道 分区价格管理
            Route::get('{id}/prices', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'priceTable']);
            Route::put('{id}/prices', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'updatePriceTable']);
            Route::get('{id}/prices/export', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'exportPriceTable']);
            Route::post('{id}/prices/import', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'importPriceTable']);
            Route::post('/price-test', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'priceTest']);
            //渠道 分区增值服务管理
            Route::get('{id}/services', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'serviceIndex']);
            Route::get('{id}/services/export', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'servicePriceExport']);
            Route::post('{id}/services/import', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'servicePriceImport']);
            Route::get('{id}/services/{serviceId}', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'showService']);
            Route::post('{id}/services', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'storeService']);
            Route::put('{id}/services/{serviceId}', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'updateService']);
            Route::delete('{id}/services/{serviceId}', [\App\Http\Controllers\Admin\ExpressLineRegionController::class, 'destroyService']);
            //渠道 规则管理
            Route::get('{id}/rules', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'index']);
            Route::get('{id}/rules/conditions', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'conditions']);
            Route::put('{id}/rules/base-config', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'updateBaseConfig']);
            Route::get('{id}/rules/{ruleId}', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'show']);
            Route::post('{id}/rules', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'store']);
            Route::put('{id}/rules/{ruleId}', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'update']);
            Route::delete('{id}/rules/{ruleId}', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'destroy']);
            Route::get('{id}/rule-remark', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'getRemark']);
            Route::put('{id}/rule-remark', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'updateRemark']);
            Route::get('user-address-tags', [\App\Http\Controllers\Admin\UserAddressTagController::class, 'index']);
            Route::get('remote-types', [\App\Http\Controllers\Admin\RemoteTypeController::class, 'all']);
            Route::get('/{id}/advance-conditions', [\App\Http\Controllers\Admin\ExpressLineRuleController::class, 'getAdvanceConditions']);


            Route::get('{id}/self-pickup-stations', [\App\Http\Controllers\Admin\ExpressLineController::class, 'usableSelfPickupStation']);

            Route::prefix('icons')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\ExpressLineIconController::class, 'index']);
                Route::get('/{id}', [\App\Http\Controllers\Admin\ExpressLineIconController::class, 'show']);
                Route::post('/', [\App\Http\Controllers\Admin\ExpressLineIconController::class, 'store']);
                Route::put('/{id}', [\App\Http\Controllers\Admin\ExpressLineIconController::class, 'update']);
                Route::put('/{id}/as-default', [\App\Http\Controllers\Admin\ExpressLineIconController::class, 'setDefault']);
                Route::delete('/{id}', [\App\Http\Controllers\Admin\ExpressLineIconController::class, 'destroy']);
            });


        });

        //物品属性 管理
        Route::prefix('package-props')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PackagePropController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\PackagePropController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Admin\PackagePropController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\PackagePropController::class, 'update']);
            Route::put('/sort', [\App\Http\Controllers\Admin\PackagePropController::class, 'sort']);
            Route::put('/batch-delete', [\App\Http\Controllers\Admin\PackagePropController::class, 'delete']);
            Route::put('/{id}/translate-data', [\App\Http\Controllers\Admin\PackagePropController::class, 'updateTrans']);
        });

        //图片上传
        Route::prefix('upload')->group(function () {
            Route::post('/images', [\App\Http\Controllers\Admin\UploadController::class, 'uploadImages']);
            Route::post('/files', [\App\Http\Controllers\Admin\UploadController::class, 'uploadFiles']);
            Route::post('/certs', [\App\Http\Controllers\Admin\UploadController::class, 'uploadCerts']);
            Route::get('/temp-keys', [\App\Http\Controllers\Admin\UploadController::class, 'getCosUploadConfig']);
            Route::get('/public-temp-key', [\App\Http\Controllers\Admin\UploadController::class, 'getAdminCosUploadConfig']);
            Route::post('/base64', [\App\Http\Controllers\Admin\UploadController::class, 'uploadBase64']);
        });

        //语言列表
        Route::prefix('languages')->group(function () {
            Route::put('/{id}/set-default', [\App\Http\Controllers\Admin\LanguageController::class, 'setDefault']);
            Route::put('/{id}/set-status/{status}', [\App\Http\Controllers\Admin\LanguageController::class, 'setStatus']);
            Route::get('/language-can-add', [\App\Http\Controllers\Admin\LanguageController::class, 'languageCanAdd']);
            Route::get('/refresh-translate', [\App\Http\Controllers\Admin\LanguageController::class, 'refreshTranslate']);
            Route::get('/', [\App\Http\Controllers\Admin\LanguageController::class, 'index']);
            Route::get('/enabled', [\App\Http\Controllers\Admin\LanguageController::class, 'getAvailableLanguages']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\LanguageController::class, 'show']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\LanguageController::class, 'destroy']);
            Route::put('/batch-delete', [\App\Http\Controllers\Admin\LanguageController::class, 'batchDelete']);
            Route::post('/', [\App\Http\Controllers\Admin\LanguageController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\LanguageController::class, 'update']);
        });

        //国家管理
        Route::prefix('countries')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ExpressLineController::class, 'getCountriesList']);
            Route::get('/list', [\App\Http\Controllers\Admin\CountryController::class, 'index']);
            Route::get('import-template-types', [\App\Http\Controllers\Admin\CountryController::class, 'getTemplateTypeList']);
            Route::get('/search', [\App\Http\Controllers\Admin\ExpressLineController::class, 'searchCountry']);
            Route::put('/sort-indexes', [\App\Http\Controllers\Admin\CountryController::class, 'updateIndex']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\CountryController::class, 'show']);
            Route::get('/express-lines/{id}', [\App\Http\Controllers\Admin\ExpressLineController::class, 'getExpressLineCountryList']);
            Route::get('/enabled', [\App\Http\Controllers\Admin\ExpressLineController::class, 'getEnabledCountryList']);
            Route::get('/with-areas', [\App\Http\Controllers\Admin\ExpressLineController::class, 'getCountriesListWithArea']);
            Route::post('/', [\App\Http\Controllers\Admin\ExpressLineController::class, 'addCountry']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\CountryController::class, 'destroy']);
            Route::put('/{id}/status/{status}', [\App\Http\Controllers\Admin\CountryController::class, 'setStatus']);
            Route::put('/{id}/hot/{status}', [\App\Http\Controllers\Admin\CountryController::class, 'setHot']);
            Route::post('import', [\App\Http\Controllers\Admin\CountryController::class, 'excelImport']);
            Route::get('import-template', [\App\Http\Controllers\Admin\CountryController::class, 'downloadExcelTemplate']);
            Route::put('/{id}/rgb-color', [\App\Http\Controllers\Admin\CountryController::class, 'updateRGBColor']);
            Route::put('/{id}/translation', [\App\Http\Controllers\Admin\CountryController::class, 'updateTrans']);
            //区域管理
            Route::get('/{id}/areas', [\App\Http\Controllers\Admin\CountryController::class, 'areas']);
            Route::get('/areas/{id}', [\App\Http\Controllers\Admin\CountryController::class, 'areaInfo']);
            Route::put('/areas/batch-delete', [\App\Http\Controllers\Admin\CountryController::class, 'deleteAreas']);
            Route::put('/areas/{id}/status/{status}', [\App\Http\Controllers\Admin\CountryController::class, 'setAreaStatus']);
            Route::post('/areas', [\App\Http\Controllers\Admin\CountryController::class, 'createArea']);
            Route::put('/areas/{id}', [\App\Http\Controllers\Admin\CountryController::class, 'updateArea']);
            //区域通知
            Route::get('area-notifications', [\App\Http\Controllers\Admin\CountryController::class, 'getNotificationList']);
            Route::get('area-notifications/{id}', [\App\Http\Controllers\Admin\CountryController::class, 'getNotificationInfo']);
            Route::delete('area-notifications/{id}', [\App\Http\Controllers\Admin\CountryController::class, 'deleteNotification']);
            Route::post('area-notifications', [\App\Http\Controllers\Admin\CountryController::class, 'createNotification']);
            Route::put('area-notifications/{id}', [\App\Http\Controllers\Admin\CountryController::class, 'updateNotification']);
        });

        //优惠券配置
        Route::prefix('coupons')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\CouponController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\CouponController::class, 'show']);
            Route::get('/express-line-list', [\App\Http\Controllers\Admin\CouponController::class, 'getEnabledLines']);
            Route::get('/country-list', [\App\Http\Controllers\Admin\ExpressLineController::class, 'getEnabledSimpleCountryList']);
            Route::get('/{id}/user-coupons', [\App\Http\Controllers\Admin\CouponController::class, 'indexOfUserCoupons']);
            Route::post('/', [\App\Http\Controllers\Admin\CouponController::class, 'store']);
            Route::patch('/{id}/disable', [\App\Http\Controllers\Admin\CouponController::class, 'disable']);
            Route::put('/{id}/launch', [\App\Http\Controllers\Admin\CouponController::class, 'launch']);
            Route::get('/user-relations', [\App\Http\Controllers\Admin\CouponController::class, 'getUserRelations']);
            Route::put('{id}/translate-data', [\App\Http\Controllers\Admin\CouponController::class, 'updateTrans']);
            Route::post('export', [\App\Http\Controllers\Admin\CouponController::class, 'export']);
            Route::get('/{id}/codes', [\App\Http\Controllers\Admin\CouponController::class, 'couponCodeIndex']);
            Route::put('/codes/{id}/disabled', [\App\Http\Controllers\Admin\CouponController::class, 'setCodeDisable']);
            Route::post('/{id}/codes', [\App\Http\Controllers\Admin\CouponController::class, 'createCode']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\CouponController::class, 'destroy']);
        });

        //快递线路费用查询
        Route::prefix('express-fee-query')->group(function () {
            Route::post('/', [\App\Http\Controllers\Client\ExpressPriceController::class, 'adminQuery']);
            Route::get('/express-line/{id}', [\App\Http\Controllers\Admin\ExpressLineController::class, 'show']);
            Route::get('/warehouses', [\App\Http\Controllers\Admin\WarehouseAddressController::class, 'filterList']);
        });

        //字符串翻译
        Route::prefix('string-translations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\StringTranslationController::class, 'index']);
            Route::get('/enabled-languages', [\App\Http\Controllers\Admin\StringTranslationController::class, 'getEnabledLanguage']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\StringTranslationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Admin\StringTranslationController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\StringTranslationController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\StringTranslationController::class, 'destroy']);
            Route::get('/export', [\App\Http\Controllers\Admin\StringTranslationController::class, 'export']);
            Route::post('/import', [\App\Http\Controllers\Admin\StringTranslationController::class, 'import']);
        });

        //偏远类型
        Route::prefix('remote-types')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\RemoteTypeController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\RemoteTypeController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Admin\RemoteTypeController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\RemoteTypeController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::put('/batch-delete', [\App\Http\Controllers\Admin\RemoteTypeController::class, 'destroy']);
        });

        //偏远明细
        Route::prefix('remote-destinations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\RemoteDestinationController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\RemoteDestinationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Admin\RemoteDestinationController::class, 'store']);
            Route::post('/batch-store', [\App\Http\Controllers\Admin\RemoteDestinationController::class, 'batchStore']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\RemoteDestinationController::class, 'update']);
            Route::put('/batch-delete', [\App\Http\Controllers\Admin\RemoteDestinationController::class, 'destroy']);
        });

        //用户地址管理
        Route::prefix('user-addresses')->group(function () {
            // 地址标签
            Route::prefix('tags')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\UserAddressTagController::class, 'index']);
            });
        });

        //本地化配置
        Route::prefix('localization')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\LocalizationController::class, 'getInfo']);
            Route::put('/', [\App\Http\Controllers\Admin\LocalizationController::class, 'update']);
            Route::get('/configs', [\App\Http\Controllers\Admin\LocalizationController::class, 'getLocalizationConfiguration']);
            Route::get('/currency-list', [\App\Http\Controllers\Admin\LocalizationController::class, 'currencyList']);
        });

        // 供应商
        Route::prefix('supplier')->group(function () {
            Route::get('/', [SupplierController::class, 'index']);
            Route::get('/enable/all', [SupplierController::class, 'getAllEnable']);
            Route::get('/{id}', [SupplierController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::post('/', [SupplierController::class, 'store']);
            Route::put('/{id}', [SupplierController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::put('/update-status', [SupplierController::class, 'updateStatus']);
            Route::delete('/', [SupplierController::class, 'deletes']);
            Route::post('/import', [SupplierController::class, 'import']);
        });

        // 供货关系
        Route::prefix('goods-supplier')->group(function () {
            Route::get('/', [GoodsSupplierController::class, 'index']);
            Route::get('/{id}', [GoodsSupplierController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::post('/', [GoodsSupplierController::class, 'store']);
            Route::put('/{id}', [GoodsSupplierController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::put('/update-status', [GoodsSupplierController::class, 'updateStatus']);
            Route::delete('/', [GoodsSupplierController::class, 'deletes']);
            Route::get('/get-detail-by-sku', [GoodsSupplierController::class, 'getDetailBySku']);
        });

        Route::prefix('inbound-order')->group(function () {
            Route::get('/', [InboundOrderController::class, 'index']);
            Route::get('/{id}', [InboundOrderController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::get('/status-count', [InboundOrderController::class, 'statusCount']);
            Route::post('/', [InboundOrderController::class, 'store']);
            Route::put('/{id}', [InboundOrderController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::put('/{id}/cancel', [InboundOrderController::class, 'cancel']);
            Route::delete('/', [InboundOrderController::class, 'deletes']);
            Route::get('/scan-data', [InboundOrderController::class, 'scanData']);
            Route::post('/{id}/sign', [InboundOrderController::class, 'sign']);
            Route::post('/{id}/inbound', [InboundOrderController::class, 'inbound']);
            Route::post('/print-inbound-items-label', [InboundOrderController::class, 'printInboundItemsLabel']);
        });


        # 出库单模块
        Route::prefix('outbound-order')->group(function () {
            Route::get('/', [OutboundOrderController::class, 'index']);
            Route::get('/{id}', [OutboundOrderController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::get('/scan-data', [OutboundOrderController::class, 'scanData']);
            Route::get('/status-count', [OutboundOrderController::class, 'statusCount']);
            Route::post('/', [OutboundOrderController::class, 'store']);
            Route::put('/{id}', [OutboundOrderController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::put('/{id}/update-package-info', [OutboundOrderController::class, 'updatePackageInfo']);
            Route::put('/{id}/outbound', [OutboundOrderController::class, 'outbound']);
            Route::put('/weighing-completed', [OutboundOrderController::class, 'weighingCompleted']);//称重完成
        });

        # 拣货单模块
        Route::prefix('picking-order')->group(function () {
            Route::get('/', [PickingOrderController::class, 'index']);
            Route::get('/{id}', [PickingOrderController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::get('/scan-data', [PickingOrderController::class, 'scanData']);
            Route::get('/status-count', [PickingOrderController::class, 'statusCount']);
            Route::get('/{id}/print-picking', [PickingOrderController::class, 'printPicking']);
            Route::post('/create-by-outbound-ids', [PickingOrderController::class, 'createByOutboundIds']);
            Route::put('/{id}/cancel', [PickingOrderController::class, 'cancel'])->where(['id' => '[0-9]+']);
            Route::put('/assign-staff', [PickingOrderController::class, 'assignStaff']);
            Route::put('/{id}/second-sort', [PickingOrderController::class, 'secondSort']);
            Route::put('/{id}/complete', [PickingOrderController::class, 'completePicking']);
        });

        Route::prefix('system-config')->group(function () {
            Route::get('/base-config', [SystemConfigController::class, 'getBaseConfig']);
            Route::post('/save-base-config', [SystemConfigController::class, 'saveBaseConfig']);
            Route::post('/get-multiple-config', [SystemConfigController::class, 'getMultipleConfig']);
            Route::get('/operate-log-list', [SystemConfigController::class, 'operateLogList']);
        });
        // 寻源报价
        Route::prefix('resources')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'store']);
            Route::delete('/', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'del']);
            Route::get('/status/count', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'statusCount']);
            Route::put('/claim', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'claim']);
            Route::put('/allocation', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'allocation']);
            Route::put('/quotation', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'quotation']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'update']);
            Route::put('/mark/status', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'markStatus']);
            Route::put('/submit/quotation', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'submitQuotation']);
            Route::put('/submit/{id}', [\App\Http\Controllers\Admin\OrderResourcesController::class, 'submit']);
        });

        // 寻源报价沟通
        Route::prefix('consult')->group(function () {
            Route::post('/', [\App\Http\Controllers\Admin\ConsultController::class, 'store']);
            Route::get('/', [\App\Http\Controllers\Admin\ConsultController::class, 'index']);
            Route::get('/{order_id}', [\App\Http\Controllers\Admin\ConsultController::class, 'show']);
            Route::put('/mark/{id}', [\App\Http\Controllers\Admin\ConsultController::class, 'mark']);
        });

        // 物流报关信息管理
        Route::prefix('logistics')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\LogisticsCustomsController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\LogisticsCustomsController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\LogisticsCustomsController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\LogisticsCustomsController::class, 'del']);
        });

        Route::prefix('exchange-rate')->group(function () {
            Route::get('/query', [ExchangeRateController::class, 'queryExchangeRate']);
            Route::post('/', [ExchangeRateController::class, 'store']);
            Route::get('/', [ExchangeRateController::class, 'index']);
            Route::put('/{id}', [ExchangeRateController::class, 'update']);
            Route::get('/sync', [ExchangeRateController::class, 'syncExchangeRate']);
            Route::get('/support-currency', [ExchangeRateController::class, 'getSupportCurrencyList']);
            Route::get('/get-rates', [ExchangeRateController::class, 'getRates']);
        });

        // 一客一价
        Route::prefix('sku-quotation')->group(function () {
            Route::post('/save-sku-quotation', [\App\Http\Controllers\Admin\SkuQuotationController::class, 'saveSkuQuotation']);
            Route::get('/', [\App\Http\Controllers\Admin\SkuQuotationController::class, 'index']);
            Route::post('/get-new-quotation', [\App\Http\Controllers\Admin\SkuQuotationController::class, 'getNewQuotation']);
            Route::post('/get-history-quotation', [\App\Http\Controllers\Admin\SkuQuotationController::class, 'getHistoryQuotation']);
            Route::post('/delete-quotation', [\App\Http\Controllers\Admin\SkuQuotationController::class, 'deleteQuotation']);
            Route::post('/get-custom-quotation', [\App\Http\Controllers\Admin\SkuQuotationController::class, 'getCustomQuotation']);//获取自定义报价
            Route::post('/save-custom-quotation', [\App\Http\Controllers\Admin\SkuQuotationController::class, 'saveCustomQuotation']);//保存自定义报价

        });

        //邮件配置
        Route::prefix('email')->group(function () {
            Route::get('/get-email-smtp-config', [\App\Http\Controllers\Admin\EmailController::class, 'getEmailSmtpConfig']);
            Route::post('/update-smtp-config', [\App\Http\Controllers\Admin\EmailController::class, 'updateSmtpConfig']);
            Route::post('/verify-smtp-config', [\App\Http\Controllers\Admin\EmailController::class, 'verifySmtpConfig']);
        });
        //邮件模板
        Route::prefix('email-template')->group(function () {
            Route::get('/index', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'index']);
            Route::get('/get-email-template-type-list', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'getEmailTemplateTypeList']);
            Route::post('/save-email-template', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'saveEmailTemplate']);
            Route::delete('/delete/{id}', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'delete']);
            Route::post('/set-email-template-status', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'setEmailTemplateStatus']);
        });

        //系统消息
        Route::prefix('ctu-message')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\CTUMessageController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\CTUMessageController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Admin\CTUMessageController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\CTUMessageController::class, 'update']);
            Route::put('/{id}/translate-data', [\App\Http\Controllers\Admin\CTUMessageController::class, 'updateTrans']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\CTUMessageController::class, 'destroy']);
            Route::put('/{id}/push', [\App\Http\Controllers\Admin\CTUMessageController::class, 'push']);
        });

        // 产品报价
        Route::prefix('product-quote')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::get('/count', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'count']);
            Route::get('/product-list', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'getProductList']);
            Route::post('/{id}/save', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'saveQuote']);
            Route::post('/{id}/review-success', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'reviewSuccess']);
            Route::post('/review-reject', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'reviewReject']);
            Route::post('/{id}/save-logistics-channel', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'saveLogisticsChannel']);
            Route::get('/sku-price', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'getSkuQuotePrice']);
            Route::get('/quote-detail/{id}', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'quoteDetail'])->where(['id' => '[0-9]+']);
            Route::post('/association-goods-sku', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'associationGoodsSku']);
            Route::post('/submit-quote', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'submitQuote']);
            Route::get('/customer-quote/{id}', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'customerQuote']);
            Route::post('/customer-quote/{id}/save', [\App\Http\Controllers\Admin\ProductQuoteController::class, 'saveCustomerQuote']);
        });

        Route::prefix('third-party-warehouse-config')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ThirdPartyWarehouseConfigController::class, 'index']);
            Route::put('/update', [\App\Http\Controllers\Admin\ThirdPartyWarehouseConfigController::class, 'update']);
            Route::put('/status-update', [\App\Http\Controllers\Admin\ThirdPartyWarehouseConfigController::class, 'statusUpdate']);
            Route::get('/enable', [\App\Http\Controllers\Admin\ThirdPartyWarehouseConfigController::class, 'enableConfig']);
            Route::post('/test', [\App\Http\Controllers\Admin\ThirdPartyWarehouseConfigController::class, 'testApi']);
        });

        Route::prefix('third-party-system-config')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ThirdPartySystemConfigController::class, 'index']);
            Route::put('/update', [\App\Http\Controllers\Admin\ThirdPartySystemConfigController::class, 'update']);
            Route::put('/status-update', [\App\Http\Controllers\Admin\ThirdPartySystemConfigController::class, 'statusUpdate']);
        });

        Route::prefix('menu')->group(function () {
            Route::get('/get-client-menu-tree', [\App\Http\Controllers\Admin\MenuController::class, 'getClientMenuTree']);
            Route::post('/update-client-menu', [\App\Http\Controllers\Admin\MenuController::class, 'updateClientMenuTree']);
        });

        //物流轨迹API服务
        Route::prefix('tracking')->group(function () {
            Route::get('/get-tracking-config', [\App\Http\Controllers\Admin\ApiServiceController::class, 'getTrackingConfig']);
            Route::put('/update-tracking-config', [\App\Http\Controllers\Admin\ApiServiceController::class, 'updateTrackingConfig']);
            Route::put('/update-tracking-status', [\App\Http\Controllers\Admin\ApiServiceController::class, 'updateTrackingStatus']);
        });

        //物流快递订单
        Route::prefix('express-order')->group(function () {
            Route::post('/query-tracking', [\App\Http\Controllers\Admin\ExpressOrderController::class, 'queryTracking']);//查询物流轨迹
        });

        //售后工单相关
        Route::prefix('work-order')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AfterSalesWorkOrderController::class, 'index']); //工单列表
            Route::put('/{id}', [\App\Http\Controllers\Admin\AfterSalesWorkOrderController::class, 'update'])->where(['id' => '[0-9]+']); //修改工单
            Route::get('/typeList', [\App\Http\Controllers\Admin\AfterSalesWorkOrderController::class, 'getTypeList']); //获取工单类型
        });

        Route::prefix('invoice')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\InvoiceController::class, 'index']); //发票列表
            Route::get('/{id}', [\App\Http\Controllers\Admin\InvoiceController::class, 'detail'])->where(['id' => '[0-9]+']); //发票详情
            Route::put('/{id}', [\App\Http\Controllers\Admin\InvoiceController::class, 'update'])->where(['id' => '[0-9]+']); //更新发票信息
            Route::get('/source_type', [\App\Http\Controllers\Admin\InvoiceController::class, 'getSourceTypeList']); //获取来源类型
            Route::post('/request', [\App\Http\Controllers\Admin\InvoiceController::class, 'requestInvoice']); //申请发票
            Route::post('/invoiceTemplate', [\App\Http\Controllers\Admin\InvoiceController::class, 'invoiceTemplate']); //更新发票模板
            Route::get('/invoiceTemplateGet/{orderMode}', [\App\Http\Controllers\Admin\InvoiceController::class, 'invoiceTemplateGet']); //查看发票模板
            Route::get('/invoiceTemplateGetChecked/{orderMode}/{customerId}', [\App\Http\Controllers\Admin\InvoiceController::class, 'invoiceTemplateGetChecked']); //查看被选中的发票模板
        });

        Route::prefix('charge-type')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ChargeTypesController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\ChargeTypesController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::post('/', [\App\Http\Controllers\Admin\ChargeTypesController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\ChargeTypesController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::delete('/', [\App\Http\Controllers\Admin\ChargeTypesController::class, 'deletes']);
        });

        // 商品组合优惠
        Route::prefix('goods-discount-rule')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\GoodsDiscountRuleController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\GoodsDiscountRuleController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::post('/', [\App\Http\Controllers\Admin\GoodsDiscountRuleController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\GoodsDiscountRuleController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::delete('/', [\App\Http\Controllers\Admin\GoodsDiscountRuleController::class, 'deletes']);
        });

        //订单标签
        Route::prefix('order-tag')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\OrderTagsController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\OrderTagsController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::post('/', [\App\Http\Controllers\Admin\OrderTagsController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\OrderTagsController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::delete('/', [\App\Http\Controllers\Admin\OrderTagsController::class, 'deletes']);
            Route::put('/sort', [\App\Http\Controllers\Admin\OrderTagsController::class, 'sort']);
            Route::put('/{id}/translate-data', [\App\Http\Controllers\Admin\OrderTagsController::class, 'updateTranslate']);
        });

        // 包裹管理
        Route::prefix('package')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PackageController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\PackageController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::get('/count', [\App\Http\Controllers\Admin\PackageController::class, 'count']);
            Route::post('/apply-logistics', [\App\Http\Controllers\Admin\PackageController::class, 'applyLogistics']);
            Route::post('/move-to-stock', [\App\Http\Controllers\Admin\PackageController::class, 'moveToStock']);
            Route::post('/split', [\App\Http\Controllers\Admin\PackageController::class, 'split']);
            Route::post('/merge', [\App\Http\Controllers\Admin\PackageController::class, 'merge']);
            Route::get('/data-json', [\App\Http\Controllers\Admin\PackageController::class, 'dataJson']);
            Route::get('/count/sub-status/{type}', [\App\Http\Controllers\Admin\PackageController::class, 'subStatusCount']);
            Route::get('/merge-able-list', [\App\Http\Controllers\Admin\PackageController::class, 'mergeAbleList']);
            Route::get('/split-list', [\App\Http\Controllers\Admin\PackageController::class, 'splitList']);
            Route::post('/split-rollback', [\App\Http\Controllers\Admin\PackageController::class, 'splitRollback']);
            Route::get('/merged-list', [\App\Http\Controllers\Admin\PackageController::class, 'mergedList']);
            Route::post('/merge-rollback', [\App\Http\Controllers\Admin\PackageController::class, 'mergeRollback']);
        });


        Route::prefix('rest-api-config')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\RestApiConfigController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\RestApiConfigController::class, 'store']);//添加Key
            Route::delete('/{id}', [\App\Http\Controllers\Admin\RestApiConfigController::class, 'destroy']);//撤销
            Route::put('/{id}', [\App\Http\Controllers\Admin\RestApiConfigController::class, 'update']);//修改
        });

        // 虚拟sku管理
        Route::prefix('platform-virtual-sku')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PlatformVirtualSkuController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\PlatformVirtualSkuController::class, 'store']);
            Route::post('/store-buy-order-item/{id}', [\App\Http\Controllers\Admin\PlatformVirtualSkuController::class, 'storeByOrderItem']);
            Route::delete('/', [\App\Http\Controllers\Admin\PlatformVirtualSkuController::class, 'deletes']);
        });

        // 部门管理
        Route::prefix('departments')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\DepartmentController::class, 'index']);
            Route::get('/tree', [\App\Http\Controllers\Admin\DepartmentController::class, 'tree']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\DepartmentController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Admin\DepartmentController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\DepartmentController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::put('/update-status', [\App\Http\Controllers\Admin\DepartmentController::class, 'updateStatus']);
            Route::delete('/', [\App\Http\Controllers\Admin\DepartmentController::class, 'deletes']);
            Route::get('/{id}/staff-list', [\App\Http\Controllers\Admin\DepartmentController::class, 'getStaffList']);
            Route::put('/{id}/assign-staff', [\App\Http\Controllers\Admin\DepartmentController::class, 'assignStaff']);
            Route::put('/{id}/remove-staff', [\App\Http\Controllers\Admin\DepartmentController::class, 'removeStaff']);
            Route::put('/{id}/update-staff-main', [\App\Http\Controllers\Admin\DepartmentController::class, 'updateStaffMain']);
        });

        Route::prefix('php-permission')->group(function () {
            Route::get('/staff', [\App\Http\Controllers\Admin\PermissionController::class, 'staff']);
            Route::get('/customer', [\App\Http\Controllers\Admin\PermissionController::class, 'customer']);
            Route::put('/assign', [\App\Http\Controllers\Admin\PermissionController::class, 'assignDataPermissions']);
            Route::put('/remove', [\App\Http\Controllers\Admin\PermissionController::class, 'removeDataPermissions']);
        });


        // 权限范围组
        Route::prefix('data-range-group')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\DataRangeGroupController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\DataRangeGroupController::class, 'show'])->where(['id' => '[0-9]+']);
            Route::post('/', [\App\Http\Controllers\Admin\DataRangeGroupController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\DataRangeGroupController::class, 'update'])->where(['id' => '[0-9]+']);
            Route::delete('/', [\App\Http\Controllers\Admin\DataRangeGroupController::class, 'deletes']);

            Route::get('/get-enable-assign-staff-list', [\App\Http\Controllers\Admin\DataRangeGroupController::class, 'getEnableAssignStaffList']);
            Route::get('/{id}/staff-list', [\App\Http\Controllers\Admin\DataRangeGroupController::class, 'getStaffList'])->where(['id' => '[0-9]+']);
            Route::put('/{id}/assign-staff', [\App\Http\Controllers\Admin\DataRangeGroupController::class, 'assignStaff'])->where(['id' => '[0-9]+']);
            Route::put('/{id}/remove-staff', [\App\Http\Controllers\Admin\DataRangeGroupController::class, 'removeStaff'])->where(['id' => '[0-9]+']);
        });

    });

    // 1688授权回调
    Route::prefix('1688')->group(function () {
        Route::get('global/auth', [\App\Http\Controllers\Admin\AlibabaController::class, 'auth']);
    });

    // 下载管理
    Route::prefix('export-downloads')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ExcelExportController::class, 'index']);
    });

    // 获取系统配置
    Route::post('system-config', [SystemConfigController::class, 'getMultipleConfig']);

});
