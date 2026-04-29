<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseService;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

class ThirdPartyWarehouseConfigService extends BaseService
{
    public $filterRules = [];

    public function __construct()
    {
        $this->model = new ThirdPartyWarehouseConfig();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $configList = $this->model::query()->get()->keyBy('platform');
        $data = [];
        foreach ($this->model::WAREHOUSE_PLATFORM_LIST as $key => $value) {
            if ($key === $this->model::PLATFORM_YUNLIANTIAO) continue;
            $config = $configList[$key] ?? null;
            $data[] = [
                'id' => $config->id ?? 0,
                'platform' => $key,
                'platform_name' => $value,
                'app_key' => $config->app_key ?? '',
                'app_secret' => $config->app_secret ?? '',
                'mark_in_distribution_after_push_order' => $config->mark_in_distribution_after_push_order ?? 0,
                'status' => $config->status ?? 0,
                'is_setting' => $config ? 1 : 0,
                'push_product' => $config->push_product ?? 0,
                'product_update_sync' => $config->product_update_sync ?? 0,
                'relation_warehouse_id' => $config->relation_warehouse_id ?? [],
            ];
        }
        return $data;
    }

    public function update($params)
    {
        validator($params, $this->rule())->validate();
        $config = $this->model::query()->where('platform', $params['platform'])->first();
        if (empty($config)) {
            $config = $this->model;
            $config->platform = $params['platform'];
            $config->status = 0;
        }
        $config->app_key = $params['app_key'];
        $config->app_secret = $params['app_secret'];
        $config->mark_in_distribution_after_push_order = $params['mark_in_distribution_after_push_order'] ?? 0;
        $config->push_product = $params['push_product'] ?? 0;
        $config->product_update_sync = $params['product_update_sync'] ?? 0;
        $config->relation_warehouse_id = $params['relation_warehouse_id'] ?? null;
        return $config->save();
    }

    public function statusUpdate($params)
    {
        validator($params, $this->statusRule())->validate();
        $config = $this->model::query()->where('platform', $params['platform'])->first();
        if ($params['status'] == 1 && (empty($config->app_key) || empty($config->app_secret))) {
            if ($params['platform'] === $this->model::PLATFORM_MABANG) {
                throw new AccidentException('当前平台未完成配置，无法启用', Code::OPERATE_FAIL);
            } else {
                if (empty($config)) {
                    $config = $this->model::query()->create([
                        'platform' => $params['platform'],
                        'app_key' => '',
                        'app_secret' => '',
                        'mark_in_distribution_after_push_order' => 0,
                        'status' => 0,
                        'push_product' => 0,
                        'product_update_sync' => 0,
                        'relation_warehouse_id' => null,
                    ]);
                }
            }
        }
        $existEnable = $this->model::query()->where('platform', '!=', $params['platform'])->where('status', 1)->first();
        if (!empty($existEnable)) throw new AccidentException('已启用其他平台，请先禁用', Code::OPERATE_FAIL);
        $config->status = $params['status'];
        return $config->save();
    }

    public function enableConfig()
    {
        return ThirdPartyWarehouseConfig::getConfig();
    }

    protected function rule()
    {
        return [
            'platform' => 'required|string',
            'app_key' => 'required|string',
            'app_secret' => 'required|string',
            'mark_in_distribution_after_push_order' => 'sometimes|nullable|integer',
            'push_product' => 'sometimes|nullable|integer',
            'product_update_sync' => 'sometimes|nullable|integer',
            'relation_warehouse_id' => 'sometimes|nullable|array',
            'relation_warehouse_id.*' => 'sometimes|nullable|integer|exists:dsp_warehouse_address,id',
        ];
    }

    protected function statusRule()
    {
        return [
            'platform' => 'required|string',
            'status' => 'required|int',
        ];
    }

    //验证接口
    public function testApi($params)
    {
        $type = $params['type'] ?? 1;
        if($type == 1){
            try {

                $service = new ThirdPartyWarehouseService();
                $result = $service->addMabangStock([]);

                Log::channel('mabang')->info('验证接口', ['result' => $result]);

                if($result['code'] == 200){
                    return true;
                }else{

                    if($result['message'] == '应用不存在'){
                        throw new AccidentException('appKey或appToken错误，请检查。', Code::OPERATE_FAIL);
                    }

                    if($result['message'] == '此接口权限没有购买，通知贵公司商务联系马帮商务进行购买。'){

                        throw new AccidentException('马帮商品API未购买', Code::OPERATE_FAIL);
                    }

                    return true;
                }

            } catch (\Exception $e) {

                throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
            }
        }
    }
}
