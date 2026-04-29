<?php

/**
 * @Author: h9471
 * @Created: 2020/2/17 10:52
 */

namespace App\Services\ExpressCompanies;

use App\Models\ApiTrackingConfig;
use App\Models\KD100TrackingLog;
use App\Models\Package;
use App\Models\WarehouseAddress;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\HttpException;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\InvalidArgumentException;
use App\Services\ExpressCompanies\KuaiDi\Express100;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KD100Service
{
    /**
     * @var string
     */
    protected $appId;

    /**
     * @var string
     */
    protected $appKey;

    /**
     * @var Express100
     */
    protected $app;

    public $companyId;

    /**
     * KD100Service constructor.
     * @throws Exception
     */
    public function __construct(int $mode = 0, $companyId = null)
    {
        $this->companyId = $companyId;

        if ($mode) {
            [$this->appId, $this->appKey] = $this->getKD100SystemConfig();
        } else {
            [$this->appId, $this->appKey] = $this->getKD100Config();
        }

        $this->app = new Express100($this->appId, $this->appKey);
    }

    /**
     * 物流查询
     *
     * @param string $carrierCode
     * @param string $trackingNumber
     * @param string $phone
     * @return false|array
     * @throws Exception
     */
    public function track(string $carrierCode, string $trackingNumber, string $phone = '')
    {
        Log::debug('快递100查询，快递公司:' . $carrierCode . '单号为:' . $trackingNumber . '手机号：' . $phone);

        if ($carrierCode === 'shunfeng' && empty($phone)) {
            $address = WarehouseAddress::first();
            $phone = $address ? $address->phone : '123456789';
        }

        try {
            $data = $this->app->track($trackingNumber, $carrierCode, $phone);
        } catch (HttpException $e) {
            Log::error('快递100物流查询失败：', ['message' => $e->getMessage()]);
            throw new AccidentException('查询失败');
        } catch (InvalidArgumentException $e) {
            Log::error('快递100物流查询失败：', ['message' => $e->getMessage()]);
            throw new AccidentException('参数异常');
        }

        Log::info('快递100查询结果：' . $data);

        $data = \json_decode($data, true);

        return $data;
    }

    /**
     * 单号订阅
     * @param int $companyId
     * @param string $carrierCode
     * @param string $trackingNumber
     * @param string $phone
     * @return false|array
     * @throws AccidentException
     */
    public function subscribe(string $carrierCode, string $trackingNumber, string $phone = '')
    {
        Log::debug('快递100单号订阅，快递公司:' . $carrierCode . '单号为:' . $trackingNumber . '手机号：' . $phone);

        if ($carrierCode === 'shunfeng' && empty($phone)) {
            $address = WarehouseAddress::query()->where('company_id', $this->companyId)->first();
            $phone = $address ? $address->phone : '123456789';
        }

        try {
            $data = $this->app->subscribe($this->companyId, $trackingNumber, $carrierCode, $phone);
        } catch (HttpException $e) {
            Log::error('快递100物流订阅失败：', ['message' => $e->getMessage()]);
            throw new AccidentException('查询失败');
        } catch (InvalidArgumentException $e) {
            Log::error('快递100物流订阅失败：', ['message' => $e->getMessage()]);
            throw new AccidentException('参数异常');
        }

        Log::info("快递100,单号[{$trackingNumber}]订阅结果：" . $data);

        $data = \json_decode($data, true);
        if (!empty($data['returnCode']) && ($data['returnCode'] == 200)) {
            return true;
        }
        return false;
    }

    public function callback($data)
    {
        $param = json_decode($data['param'], true);
        if ($param['status'] !== 'polling') {
            info("订单[{$param['lastResult']['nu']}]状态为[{$param['status']}],订阅推送已停止");
            return true;
        }

        $result = $param['lastResult'];
        if ((int)($result['status']) !== 200) {
            info("订单订阅推送错误", $param);
            return true;
        }

        return $this->updatePackageTracking($result);
    }

    protected function updatePackageTracking($result)
    {
        $companyId = Package::getCompanyId();
        throw_if(
            !$companyId,
            new AccidentException('公司标识错误')
        );
        $package = Package::query()
            ->where('company_id', $companyId)
            ->where('express_num', $result['nu'])
            ->where('tracking_type', ApiTrackingConfig::TYPE_KUAIDI_100)
            ->first();

        if (!$package) return true;

        return DB::transaction(function () use ($result, $package, $companyId) {

            $package->update(['third_tracking_status' => $result['state']]);

            KD100TrackingLog::query()->create([
                'company_id' => $companyId,
                'type' => KD100TrackingLog::TYPE_PACKAGE,
                'express_num' => $result['nu'],
                'status' => $result['state']
            ]);

            return true;
        });
    }


    /**
     * @return array
     * @throws AccidentException
     */
    protected function getKD100Config()
    {
        if (!empty($this->companyId)) {
            $config = ApiTrackingConfig::query()->where('company_id', $this->companyId)->first();
        } else {
            $config = ApiTrackingConfig::first();
        }

        if ($config && $config->kd100_customer_id) {
            return [$config->kd100_customer_id, $config->kd100_key];
        }

        throw new AccidentException('快递100配置异常');
    }

    /**
     * @return array
     */
    protected function getKD100SystemConfig()
    {
        return [
            config('jiyun.tracking.kuaidi100_customer_id'),
            config('jiyun.tracking.kuaidi100_key')
        ];
    }
}
