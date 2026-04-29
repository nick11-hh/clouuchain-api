<?php

namespace App\Services\PlatformShop;

use App\Lib\Code;
use App\Lib\Platform;
use App\Models\ShopModel;
use App\Services\PlatformShop\Platform\Salla\SallaService;
use App\Services\PlatformShop\Platform\Shopify\ShopifyService;
use App\Services\PlatformShop\Platform\Tiktok\TiktokService;
use App\Services\PlatformShop\Platform\Woocommerce\WoocommerceService;
use App\Services\PlatformShop\Platform\Zid\ZidService;
use Exception;
use App\Exceptions\AccidentException;

class Factory
{
    // 店铺平台对应service类
    public const platformClassMap = [
        Platform::SHOPIFY => ShopifyService::class,
        Platform::TIKTOK => TiktokService::class,
        Platform::WOOCOMMERCE => WoocommerceService::class,
        Platform::SALLA => SallaService::class,
        Platform::ZID => ZidService::class,
    ];

    public static function create($platform, $shop = null)
    {
        if (!isset(self::platformClassMap[$platform])) throw new AccidentException('暂不支持该平台', Code::OPERATE_FAIL);
        $className = self::platformClassMap[$platform];
        return new $className($shop);
    }
}
