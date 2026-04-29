<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\ApiTrackingConfig;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use App\Exceptions\AccidentException;

class ThirdApiService extends BaseService
{
    public $filterRules = [];

    public function __construct()
    {
        $this->model = new ApiTrackingConfig();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function getTrackingConfig()
    {
        $config = $this->model::query()->first();

        $data[] = [
            'id' => $config->id ?? 0,
            'platform' => '17Track',
            'platform_name' => '17Track',
            'app_key' => $config['17track_app_key'] ?? '',
            'app_secret' => '',
            'status' => $config->status ?? 0,
            'is_setting' => $config ? 1 : 0,
        ];

        return $data;
    }

    public function updateTrackingConfig($params)
    {
        validator($params, [
            'app_key' => 'required|string',
        ])->validate();

        $config = $this->model::query()->first();

        if (empty($config)) {
            $config = $this->model;
            $config->status = 0;
        }
        $config['17track_app_key'] = $params['app_key'];
        return $config->save();
    }

    public function updateTrackingStatus($params)
    {
        validator($params, [
            'id' => 'required|int',
            'status' => 'required|int',
        ])->validate();
        $config = $this->model::query()->where('id', $params['id'])->value('17track_app_key');
        if (empty($config)) {
            throw new AccidentException('操作失败，当前平台未完成配置', Code::OPERATE_FAIL);
        }

        return $this->model::query()->where('id', $params['id'])->update(['status' => $params['status']]);
    }

}
