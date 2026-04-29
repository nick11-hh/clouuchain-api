<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\ThirdPartySystemConfigModel;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use App\Exceptions\AccidentException;

class ThirdPartySystemConfigService extends BaseService
{
    public $filterRules = [];

    public function __construct()
    {
        $this->model = new ThirdPartySystemConfigModel();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $configList = $this->model::query()->get()->keyBy('platform');
        $data = [];
        foreach ($this->model::PLATFORM_LIST as $key => $value) {
            $config = $configList[$key] ?? null;
            $data[] = [
                'id' => $config->id ?? 0,
                'platform' => $key,
                'platform_name' => $value,
                'app_key' => $config->app_key ?? '',
                'app_secret' => $config->app_secret ?? '',
                'status' => $config->status ?? 0,
                'is_setting' => $config ? 1 : 0,
                'extend' => $config->extend ?? [],
            ];
        }
        return $data;
    }

    public function update($params)
    {
        validator($params, $this->rule())->validate();
        $config = $this->model::query()->where('platform', $params['platform'])->first();

        if (empty($config)) $config = $this->model;

        $config->platform = $params['platform'];
        $config->app_key = $params['app_key'];
        $config->app_secret = $params['app_secret'] ?? '';
        if ($params['platform'] === $this->model::PLATFORM_BREVO) {
            $config->extend = ['list' => $params['list']];
        }
        return $config->save();
    }

    public function statusUpdate($params)
    {
        validator($params, $this->statusRule())->validate();
        $config = $this->model::query()->where('platform', $params['platform'])->first();
        if (empty($config)) {
            throw new AccidentException('请先配置平台信息', Code::OPERATE_FAIL);
        }

        if ($params['status'] == 1 && empty($config->app_key)) {
            throw new AccidentException('当前平台未完成配置，无法启用', Code::OPERATE_FAIL);
        }

        $config->status = $params['status'];
        return $config->save();
    }

    protected function rule()
    {
        return [
            'platform' => 'required|string',
            'app_key' => 'required|string',
            'app_secret' => 'sometimes|nullable|string'
        ];
    }

    protected function statusRule()
    {
        return [
            'platform' => 'required|string',
            'status' => 'required|int',
        ];
    }


}
