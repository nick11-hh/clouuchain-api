<?php

namespace App\Services\ExpressCompanies\HaiOu;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\CompanyExpressModel;
use App\Models\OrderDeclarationModel;
use App\Models\OrderDockingRecordModel;
use App\Models\WarehouseAddress;
use App\Services\ExpressCompanies\Logistics;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

/**
 * 海鸥集运
 * @docs https://tongxiao.w.eolink.com/home/api-studio/inside/Hwkj5gX1f83ed5cbe838523c06536d0973886abee937af9/api/3135387/detail/55591130?spaceKey=tongxiao
 */
class HaiOuService extends Logistics
{
    public string $url;

    public string $appId;

    public string $appSecret;

    public string $sign;

    public int $timestamp;

    protected string $channel = CompanyExpressModel::CODE_HAIOU;

    public const ACTION_CREATE_ORDER        = '/api/open/v1/order';
    public const ACTION_GET_LABEL           = '/api/open/v1/labels';
    public const ACTION_GET_SHIPPING_METHOD = '/api/open/v1/channels';

    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    public function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()->where('type', OrderDockingRecordModel::TYPE_HAI_OU)->first();

        if (empty($config)) {
            throw new AccidentException("尚未配置 {$this->channel} 配置信息", Code::OPERATE_FAIL);
        }

        Log::channel($this->channel)->info('config-配置信息', [$config]);

        $this->url       = $config['info']['url'] ?? '';
        $this->appId     = $config['info']['app_id'] ?? '';
        $this->appSecret = $config['info']['app_secret'] ?? '';

        $this->setSign();
    }

    public function setSign()
    {
        $this->timestamp = time();
        $signStr         = $this->appId . $this->appSecret . $this->timestamp;
        $this->sign      = hash_hmac('sha1', $signStr, $this->appSecret);
    }

    public function query()
    {
        return [
            'app_id'    => $this->appId,
            'timestamp' => $this->timestamp,
            'sign'      => $this->sign,
        ];
    }

    public function place($package, $logisticsApply): bool
    {
        $sender = WarehouseAddress::query()->first();
        if (empty($sender)) {
            throw new AccidentException('发件人信息不存在，请设置仓库信息', Code::OPERATE_FAIL);
        }

        //海关申报信息
        $declares = [];
        $weight   = 0;
        $package->items->each(function ($sku) use ($package, &$declares, &$weight) {
            // 判断是否为手动报关
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if (empty($logistics)) {
                return false;
            }

            $declareWeight = $logistics->weight / 1000;
            $declares[]    = [
                'en_name'    => $logistics->en_name,   // 申报英文名称 Length <= 50
                'cn_name'    => $logistics->cn_name,   // 申报中文名称 Length <= 50
                'quantity'   => $sku->quantity, // 申报数量,必填
                'unit'       => '', //单位
                'currency'   => 'USD', // 海关申报币种
                'box_num'    => 1, // 箱数
                'weight'     => $declareWeight, // 重量
                'unit_value' => $logistics->unit_price, // 单价
            ];

            $weight += $declareWeight * $sku->quantity;

            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        //发件人信息
        $shipper = [
            'receiver_name' => $sender->receiver_name, // 发件人姓名 Y
            'timezone'      => '0086', //国家区号
            'phone'         => $sender->phone, // 发件人电话
            'country_code'  => 'CN', // 发件人国家二字代码 Y
            'province'      => $sender->province, // 发件人省
            'city'          => $sender->city, // 发件人城市
            'street'        => '', // 街道
            'door_no'       => '', // 收件人地址2
            'postcode'      => $sender->postcode, // 发件人邮编
            'address'       => $sender->address, // 发件人地址 Y
            'email'         => '', // 收件人邮箱
        ];

        //收件人信息
        $consignee = [
            'receiver_name' => $package->packageAddress->first_name . ' ' . $package->packageAddress->last_name, //是	收件人姓名
            'timezone'      => '0', //国家区号
            'phone'         => $package->packageAddress->phone, // 是	收件人电话
            'country_code'  => $package->packageAddress->country_code, // 收件人国家二字代码
            'province'      => $package->packageAddress->province, // 收件人省
            'city'          => $package->packageAddress->city, // 收件人城市
            'district'      => '', // 收件人县/区
            'street'        => $package->packageAddress->address1, // 是	街道
            'door_no'       => $package->packageAddress->address2, // 收件人地址2
            'postcode'      => $package->packageAddress->zip, // 收件人邮编
            'address'       => $package->packageAddress->address1 . ' ' . $package->packageAddress->address2, // 收件人门牌号
            'email'         => $package->packageAddress->email, // 收件人邮箱
        ];

        //打包箱子数据
        $box[] = [
            'weight' => $weight,//重量
            'length' => 0,//长
            'width'  => 0,//宽
            'height' => 0,//高
        ];

        $data = [
            'order_number'     => $logisticsApply->package_sn, // Y 订单号
            'express_code'     => $package->express_channel_code, // Y 渠道代码
            'vip_remark'       => '', // N vip_remark
            'is_insurance'     => 2, // 是否开启保险 0-否 1-是 2-根据系统规则判断
            'receiver_address' => $consignee,//收件地址
            'sender_address'   => $shipper,//发件人地址
            'box'              => $box,//打包箱子数据
            'declares'         => $declares,//申报信息
        ];

        Log::channel($this->channel)->info('申请物流单号-申报信息-3', $data);

        try {
            $resultData = $this->request('POST', self::ACTION_CREATE_ORDER, $data);
        } catch (Exception $e) {
            //申报失败
            return $this->applyLogisticFailure($package, $logisticsApply, $e->getMessage());
        }

        // 申报成功
        $record = [
            'way_bill_number' => $resultData['system_number'] ?? '',
            'tracking_number' => ''
        ];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    public function getLabel($sn, $logisticsApply)
    {
        $data['order_sn'] = $logisticsApply->way_bill_number ?? $sn;

        Log::channel($this->channel)->info('获取订单标签', $data);

        $resultData = $this->request('GET', self::ACTION_GET_LABEL, $data);

        return current($resultData['labels'] ?? []);
    }

    /**
     * @return array|false
     */
    public function channels()
    {
        $response = $this->getChannelCode(self::ACTION_GET_SHIPPING_METHOD);

        if ($response) {
            return collect($response)
                ->map(function ($value) {
                    return [
                        'code' => $value['code'],
                        'name' => $value['name'],
                    ];
                })
                ->values()->all();
        }

        return false;
    }

    protected function getChannelCode(string $action)
    {
        return $this->request('GET', $action);
    }

    /**
     * 物流轨迹查询
     * @param string $sn 服务商单号
     */
    public function tracking(string $sn)
    {
        // TODO: Implement getDsConsignment() method.
    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        // TODO: Implement getDsConsignment() method.
    }

    protected function request($method, $action, $data = [])
    {
        try {
            $result = $this->client->request($method, $this->url . $action, [
                'query' => $this->query(),
                'json'  => $data,
            ]);

            $result = json_decode($result->getBody()->getContents(), true);

            Log::channel($this->channel)->info('request-响应数据', $result);

            $response = new Response($result ?? []);

            if (!$response->isSuccessful()) {
                throw new AccidentException($result['msg'] ?? 'Api request failed, please check app');
            }

            return $response->result();
        } catch (GuzzleException $e) {
            Log::channel($this->channel)->info("request-接口请求失败", ['msg' => $e->getMessage()]);

            if ($e->hasResponse()) {
                $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
                $message = $responseBody['msg'] ?? 'Api request failed, please check app';
            } else {
                $message = $e->getMessage();
            }

            throw new AccidentException($message, Code::OPERATE_FAIL);
        }
    }

}
