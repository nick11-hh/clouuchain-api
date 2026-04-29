<?php

namespace App\Services\ThirdPartyWarehouse;

use App\Lib\Code;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\ThirdPartyWarehouse\Mabang\MabangService;
use Exception;
use App\Exceptions\AccidentException;

class Factory
{
    // 店铺平台对应service类
    const platformClassMap = [
        ThirdPartyWarehouseConfig::PLATFORM_MABANG => MabangService::class,
    ];

    public static function create($config)
    {
        if (!isset(self::platformClassMap[$config->platform])) throw new AccidentException('暂不支持该平台', Code::OPERATE_FAIL);
        $className = self::platformClassMap[$config->platform];
        return new $className($config);
    }
}
