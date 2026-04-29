<?php

use App\Helper\CosUtil;
use App\Jobs\MabangCheckOrderPushStatusJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 生成更新翻译文件
Artisan::command('generateTranslate', function () {
    function getFiles($path): array
    {
        $files = [];
        $list = scandir($path);
        foreach ($list as $value) {
            if ($value == '.' || $value == '..') continue;
            $newPath = $path . '/' . $value;
            if (is_dir($newPath)) {
                $files = array_merge($files, getFiles($newPath));
            } else {
                $files[] = $newPath;
            }
        }
        return $files;
    }

    $path = base_path('app');
    $files = getFiles($path);
    $whiteFiles = [base_path('app/helpers.php')];
    $data = [];
    foreach ($files as $file) {
        if (in_array($file, $whiteFiles)) continue;
        $str = file_get_contents($file);
        $reg1 = "/(?<=Exception\()(.+?)(?=')/";
        $reg2 = "/(?<= __\()(.+?)(?=\))/";
        $reg3 = "/(?<=errorMessage\(')(.+?)(?=')/";
        $reg4 = "/(?<=successMessage\(')(.+?)(?=')/";
        $matches1 = [];
        $matches2 = [];
        $matches3 = [];
        $matches4 = [];
        preg_match_all($reg1, $str, $matches1);
        preg_match_all($reg2, $str, $matches2);
        preg_match_all($reg3, $str, $matches3);
        preg_match_all($reg4, $str, $matches4);
        $data = array_merge($data, $matches1[0] ?? [], $matches2[0] ?? [], $matches3[0] ?? [], $matches4[0] ?? []);
    }
    $data = array_map(function ($value) {
        return trim($value, '\'');
    }, $data);
    $data = array_unique($data);
    $originData = file_get_contents(base_path('resources/lang/en.json'));
    $originData = json_decode($originData, true);
    $whiteList = ['$e->getMessage(', "Unauthenticated.", 'Unable to verify signature.', '$message'];
    $translate = '';
    foreach ($data as $key => $value) {
        if (in_array($value, $whiteList)) continue;
        $translateStr = $originData[$value] ?? '';
        if (isset($originData[$value])) unset($originData[$value]);
        $translate .= ('  "' . $value . '": "' . $translateStr . '",' . PHP_EOL);
        if (array_key_last($data) === $key && empty($originData)) {
            $translate = trim($translate);
            $translate = trim($translate, ',');
        }
    }
    foreach ($originData as $key => $value) {
        $translate .= ('  "' . $key . '": "' . $value . '",' . PHP_EOL);
        if (array_key_last($originData) === $key) {
            $translate = trim($translate);
            $translate = trim($translate, ',');
        }
    }
    $content = <<<content
{
  $translate
}

content;


    file_put_contents(base_path('resources/lang/en.json'), $content);
});


Artisan::command('test54545', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(1);;
    $tenant->makeCurrent();
    $order = \App\Models\Order::query()->where('order_id', '576587683325644866')->first();
    $config = \App\Models\ThirdPartyWarehouseConfig::getConfig();
//    $shop = \App\Models\ShopModel::query()->where('id', 19)->first();
    $service = new \App\Services\ThirdPartyWarehouse\Mabang\MabangService($config);
//    $service->createShop($shop);
    $service->pushOrderToWarehouse($order);
//    $service->checkOrderCreate($order);
//    $service->syncLogisticChannel();
//    $service->updateProduct(1);

//    $service->syncOrderSendStatus($order);
});

Artisan::command('test4545456453', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(2);;
    $tenant->makeCurrent();
    $order = \App\Models\Order::query()->where('order_id', 6031245049959)->first();
    $logs = \App\Models\OrderThirdPartyFulfillmentLogs::query()->where('id', 3)->first();
    dispatch(new MabangCheckOrderPushStatusJob($order, $logs))->onQueue('third-party-warehouse');
});


Artisan::command('test2389890', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(7);;
    $tenant->makeCurrent();
    $tenant->makeCurrent();
    // 创建超级管理员账号
    $admin = new \App\Models\Admin();
    $admin->name = '卓米';
    $admin->username = 'admin';
    $admin->password = bcrypt('12345678');
    $admin->group_id = 1;
    $admin->super_admin = 1;
    $admin->save();

    $service = new \App\Services\Base\TenantService();
    $service->initAdmin($tenant);
});


Artisan::command('syncPlatformOrder', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(1);
    $tenant->makeCurrent();

    /*$shop = \App\Models\ShopModel::query()->first();
    $service = new \App\Services\PlatformShop\PlatformShopService($shop);
    $service->syncOrderList();*/
//    $service->syncOrderDetail("5990316507364");

    $service = new \App\Services\Admin\OrderService((new \App\Models\Order()));
    $service->pullOrders([]);
});

Artisan::command('syncShopifyWebhook', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(1);
    $tenant->makeCurrent();

    $shops = \App\Models\ShopModel::query()
        ->where('platform', 'shopify')
        ->where('status', 1)
        ->get();

    foreach ($shops as $shop) {
        dispatch(new \App\Jobs\CreateShopifyWebhooks($shop));
    }

    return true;
});

Artisan::command('removeAbnormalStatus', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(1);
    $tenant->makeCurrent();

    $service = new \App\Services\Admin\OrderService(new \App\Models\Order());
    $service->ignoreAbnormal(['ids' => []]);
    return true;
});

Artisan::command('downloadImage', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(1);
    $tenant->makeCurrent();

    $logoFilename = '20240808-CSCrVyVQRxZ6UKGD.png';
    if (!Storage::disk('admin_public')->fileExists($logoFilename)) {
        $clientLogo = CosUtil::download($logoFilename);
    } else {
        $clientLogo = Storage::disk('admin_public')->path($logoFilename);
    }
    dd($clientLogo);

    return true;
});


/**
 * 修复订单使用客户库存发货，产品库存未锁定的bug
 */
Artisan::command('useCustomerStockFix', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(0);
    $tenant->makeCurrent();

    $ids = [0];
    $orders = \App\Models\Order::with('lineItems')->whereIn('id', $ids)->get();

    //锁定客户库存
    $orders->each(function ($order) {
        if ($order->use_customer_stock) {
            $adminOrderService = new \App\Services\Admin\OrderService(new \App\Models\Order());
            $order->lineItems->each(function ($item) use ($order, $adminOrderService) {
                if ($item->stock->id ?? 0) {
                    info('库存已锁定', [$order->id, $item, $item->stock]);
                    return true;
                }

                $mapping = \App\Models\OrderItemMapping::query()->with('goodsSku')->where('platform_variant_id', $item['variant_id'])->first();
                if (empty($mapping)) {
                    info('订单未映射本地商品', [$order->id, $item]);
                    return true;
                }

                //查询客户库存
                $stock = \App\Models\Stock::query()->where([
                    'sku_id' => $mapping->goods_sku_id,
                    'custom_id' => $order->customer_id,
                ])->first();

                if (empty($stock)) {
                    info('客户库存不足', [$order->id, $item]);
                    return true;
                }

                if ($stock->quantity < $item->quantity) {
                    info('客户库存不足'. ':' . $stock->spec_name, [$order->id, $item]);
                    return true;
                }

                //锁定库存
                $adminOrderService->orderItemUseStock($order, $item, $stock);

                return true;
            });
        }
    });

    return true;
});

Artisan::command('test17845789w', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(2);
    $tenant->makeCurrent();
    $shop = \App\Models\ShopModel::query()->findOrFail(1);
    $service = new \App\Services\PlatformShop\PlatformShopService($shop);
    $package = \App\Models\Package::query()->where('package_sn', 'PKG1744787513')->first();
    $service->packageShipment($package);
});

Artisan::command('test1784e454wq', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(2);
    $tenant->makeCurrent();
    $shop = \App\Models\ShopModel::query()->findOrFail(3);
    $service = new \App\Services\PlatformShop\PlatformShopService($shop);
    $service->syncOrderDetail(6997533819146);
});

Artisan::command('test46574647', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(1);
    $tenant->makeCurrent();
    $shop = \App\Models\ShopModel::query()->findOrFail(180);
    $lineItem = \App\Models\OrderLineItem::query()->where('variant_id', 44724607418558)->first();
    $shopifyService = new \App\Services\PlatformShop\Platform\Shopify\ShopifyService($shop);
    $images = $shopifyService->getOrderImages($lineItem->toArray());
    $lineItems = \App\Models\OrderLineItem::query()->where('variant_id', 44724607418558)->get();
    foreach ($lineItems as $item) {
        $item->imgs = $images;
        $item->save();
    }
});


Artisan::command('test56456415', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(2);
    $tenant->makeCurrent();
    $service = new \App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseService();
    $order = \App\Models\Order::query()->where('order_id', 6997533819146)->first();
    $service->syncOrderSendStatus($order);
});

Artisan::command('get-alibaba-token', function () {
    $tenant = \App\Models\Landlord\Tenant::query()->find(1);
    $tenant->makeCurrent();
    $params = [
        'grant_type' => 'authorization_code',
        'need_refresh_token' => true,
        'client_id' => '2792313',
        'client_secret' => 'qIEjOqlu11c6',
        'code' => '5bd4cb88-71cd-47f5-8f40-ed4930cab2c8',
    ];
    $service = new \App\Services\Collect\Platform\Y1688\RequestApi();
    $res = $service->getPlatformToken($params);
    dd($res);
});

Artisan::command('test-translation', function () {
    $service = new \App\Services\Translation\TranslationService('en-US');
    $res = $service->translation([
        '紫色/L',
        '秋冬咖色小熊翻毛皮适用苹果17手机壳iphone15/14保护套13/12挂绳'
    ]);
    dd($res);
});
