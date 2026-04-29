<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Services\ApiResponseService;
use App\Services\Base\SystemConfigService;

class ConfigureController extends Controller
{
    public function getSystemConfig()
    {
        $getKeys = SystemConfig::clientConfigList();

        $data = SystemConfigService::getMultipleConfig($getKeys);
        return ApiResponseService::success($data);
    }
}
