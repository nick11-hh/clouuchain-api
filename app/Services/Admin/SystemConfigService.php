<?php

namespace App\Services\Admin;

use App\Models\SystemConfig;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\Base\SystemConfigService as BaseService;
use App\Models\SystemConfigOperateLogs;
use Carbon\Carbon;

class SystemConfigService
{

    /**
     * @return array
     */
    public function getBaseConfig()
    {
        $keyList = SystemConfig::configList();

        return BaseService::getMultipleConfig($keyList);
    }

    /**
     * @param $params
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function saveBaseConfig($params)
    {
        // validator($params, $this->rules())->validate();
        return BaseService::batchSet($params);
    }

    /**
     * @param $params
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function getMultipleConfig($params): array
    {
        validator($params, [
            'config_keys' => 'required|array'
        ])->validate();

        return BaseService::getMultipleConfig($params['config_keys']);
    }

    public function rules()
    {
        return [
            'shopify_app_review_mode' => 'required|int'
        ];
    }

    /**
     * 操作日志列表
     * @param $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/13 10:58
     */
    public function operateLogList($params)
    {
        $page = $params['size'] ?? 20;
        $beginDate  = $params['begin_date'] ?? '';
        $endDate    = $params['end_date'] ?? '';
        $content    = $params['content'] ?? '';

        $query = SystemConfigOperateLogs::query()->with(['admin']);

        $query->when($beginDate && $endDate, function ($query) use ($beginDate, $endDate) {
            $beginTime  = Carbon::parse($beginDate)->startOfDay();
            $endTime    = Carbon::parse($endDate)->endOfDay();

            $query->whereBetween('created_at', [$beginTime, $endTime]);
        });

        $query->when($content, function ($query) use ($content) {
            $query->where('content', 'LIKE', "%{$content}%");
        });

        return $query->orderByDesc('id')->paginate($page);
    }


    public function fulfillmentConfig()
    {
        $config = ThirdPartyWarehouseConfig::getConfig();
        return [
            'platform' => $config->platform ?? ThirdPartyWarehouseConfig::PLATFORM_YUNLIANTIAO
        ];
    }

}
