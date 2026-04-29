<?php
/**
 * 物流对接抽象类
 * 主要实现以下四个方法：
 * 1、运单申请
 * 2、获取运输方式
 * 3、获取面单
 * 4、轨迹查询
 */
namespace App\Services\ExpressCompanies;

use App\Lib\Code;
use App\Models\ExpressOrderModel;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\OutboundOrder;
use App\Models\Package;
use App\Models\ShopOrderAbnormal;
use App\Services\Admin\OrderService;
use App\Services\Base\OrderBaseService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

abstract class Logistics
{
    protected Client $client;

    protected string $channel = '';

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * 运单申请
     *
     * @param $package
     * @param $logisticsApply // LogisticsApplyModel 物流申请结果
     */
    public abstract function place($package, $logisticsApply);

    // 获取运输方式
    public abstract function channels();

    // 获取面单
    public abstract function getLabel(string $sn, LogisticsApplyModel $logisticsApply);

    // 轨迹查询
    public abstract function tracking(string $sn);

    public abstract function getDsConsignment($way_bill_number, $logisticsApply);

    /**
     * @param $method
     * @param $url
     * @param array $header
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    protected function requestHttp($method, $url, array $header = [], array $data = [])
    {
        $method = strtoupper($method);

        try {
            $option = ['headers' => $header,];
            if ($method === 'GET') {
                $option['query'] = $data;
            } else {
                $option = [...$option, ...$data];
            }

            $response = $this->client->request($method, $url, $option);
            $content = $response->getBody()->getContents();

            return json_decode($content, true);
        } catch (GuzzleException $e) {
            info("{$this->channel} requestHttp 获取物流渠道失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage());
        }
    }


    /** 物流申报成功
     * @param $package
     * @param $logisticsApply
     * @param $logisticData
     * @return true
     */
    protected function applyLogisticSuccess($package, $logisticsApply, $logisticData)
    {
        $package->logistics_status = Order::LOGISTICS_APPLY_SUCCESS;
        $package->save();

        Log::channel($this->channel)->info("{$this->channel}-申请物流单号-更新订单状态-9");

        $logisticsApply->update($logisticData);

        Log::channel($this->channel)->info("{$this->channel}-申请物流单号-更新物流信息-10", $logisticData);

        $package->orders->each(function ($order) {
            (new OrderBaseService($order))->syncPackageLogisticApplyStatus();
        });

        return true;

    }

    /** 物流申报失败
     * @param $package
     * @param $logisticsApply
     * @param $errorMessage
     * @return false
     */
    protected function applyLogisticFailure($package, $logisticsApply, $errorMessage)
    {
        $logisticsApply->update(['remark' => $errorMessage]);
        Log::channel($this->channel)->info("{$this->channel}-申请物流单号-申报失败-7", [$errorMessage]);
        $package->logistics_status = Order::LOGISTICS_APPLY_FAILURE;
        $package->save();

        $package->orders->each(function ($order) {
            (new OrderBaseService($order))->syncPackageLogisticApplyStatus();
        });

        return false;
    }

    public function syncToWarehouse($package, $logisticsApply)
    {
        if (!empty($package->outboundOrder)) {
            if ($package->outboundOrder->status != OutboundOrder::STATUS_OUTBOUND) {
                $package->outboundOrder->logistics_provider = $package->express_companies_id;
                $package->outboundOrder->tracking_number = $logisticsApply->way_bill_number;
                $package->outboundOrder->shipment_pdf = $logisticsApply->label_url ?? '';
                $package->outboundOrder->save();
            }
        }
    }
}
