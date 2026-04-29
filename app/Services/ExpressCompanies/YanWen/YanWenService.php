<?php

namespace App\Services\ExpressCompanies\YanWen;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\Country;
use App\Models\DeclareOrderModel;
use App\Models\ExpressOrderModel;
use App\Models\HandMovementModel;
use App\Models\LogisticsApplyModel;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\Order;
use App\Models\OrderBoxesModel;
use App\Models\OrderDeclarationModel;
use App\Models\OrderDockingRecordModel;
use App\Models\OrderItemMapping;
use App\Models\ThirdPartyTrackingLogModel;
use App\Models\WarehouseAddress;
use App\Services\ExpressCompanies\Logistics;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Exceptions\AccidentException;

/**
 * 燕文
 */
class YanWenService extends Logistics
{
    protected string $url;

    protected const ACTION_FORECAST_ORDER = 'express.order.create'; //运单申请/创建运单
    protected const ACTION_GET = 'express.order.get'; //查询运单详情
    protected const ACTION_CANCEL = 'express.order.cancel'; //取消运单
    protected const ACTION_OBTAIN_CHANNEL_CODE = 'express.channel.getlist'; //获取运输方式/查询已开通的产品列表
    protected const ACTION_GET_FACE = 'express.order.label.get'; //获取面单

    protected Client $client;

    protected Order $order;

    protected DeclareOrderModel $declare;

    protected string $userId;

    protected string $apiToken;

    protected string $trackUrl;

    protected string $authorization;

    protected string $channel = 'yanwen';

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }


    /**
     * 取消运单
     * @param string $waybillNumber 运单号
     * @param string $orderId 订单号
     * @param string $cancelReason 取消原因
     * @return false|mixed
     */
    public function cancel(string $waybillNumber, $orderId, $cancelReason='')
    {

        $method = self::ACTION_CANCEL;
        $timestamp = Carbon::now()->valueOf();
        $params = json_encode(['waybillNumber' => $waybillNumber, 'note' => $cancelReason]);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?user_id=' . $this->userId . '&method=' . $method . '&format=json&timestamp=' . $timestamp . '&sign=' . $sign . '&version=V1.0';

        Log::channel('logistics')
            ->info('取消运单', [
                'timestamp' => $timestamp,
                'params' => $params,
                'url' => $url,
                'sign' => $sign,
            ]);

        try {
            $result = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );

            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('取消运单响应数据', [
                    'response' => $response
                ]);
            if ($response['success'] && $response['success'] == '0') {

                LogisticsApplyModel::where('order_id', $orderId)->update([
                    'remark' => '取消运单成功'
                ]);

            }else{

                LogisticsApplyModel::where('order_id', $order->order_id)->update([
                    'remark' => '取消运单失败：' . $response['message']
                ]);

            }

        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('取消运单失败', [
                    'exception' => $exception->getResponse()->getBody()
                ]);
            LogisticsApplyModel::where('order_id', $order->order_id)->update([
                'remark' => '取消运单失败：' . $exception->getResponse()->getBody()
            ]);
        } catch (\Throwable $ex) {

            Log::channel('logistics')
                ->info('取消运单失败', [
                    'ex' => $ex->getMessage()
                ]);

            LogisticsApplyModel::where('order_id', $order->order_id)->update([
                'remark' => '取消运单失败：' . $ex->getMessage()
            ]);
        }

        return true;
    }

    /**
     * 获取面单
     * @param string $waybillNumber 运单号
     * @param string $method API接口名称
     * @return false|mixed
     */
    public function getLabel(string $waybillNumber, $logisticsApply)
    {
        $method = self::ACTION_GET_FACE;
        $timestamp = Carbon::now()->valueOf();
        $params = json_encode(['waybillNumber' => $waybillNumber]);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?user_id=' . $this->userId . '&method=' . $method . '&format=json&timestamp=' . $timestamp . '&sign=' . $sign . '&version=V1.0';

        Log::channel('logistics')
            ->info('获取面单', [
                'timestamp' => $timestamp,
                'params' => $params,
                'url' => $url,
                'sign' => $sign,
            ]);

        try {
            $result = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );

            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('获取面单响应数据', [
                    'response' => $response
                ]);
            if ($response['success'] && $response['code'] == '0') {

                #保存面单

                $fileName = '/'. $response['data']['waybillNumber'] .'.pdf';
                // $path = 'admin/pdf/yanwen/'. Carbon::now()->format('Ymd');
                $label = base64_decode($response['data']['base64String']);

                Storage::disk()->put('admin'.$fileName, $label);

                $labelUrl = config('app.url') . '/storage/admin' . $fileName;
                $logisticsApply->update([
                    'label_url' => $labelUrl,
                    'remark' => '',
                ]);

                return $labelUrl;

            }else{

                $logisticsApply->update([
                    'remark' => '获取面单失败：' . $response['message']
                ]);
            }

        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('获取面单失败', [
                    'exception' => $exception->getResponse()->getBody()
                ]);

            $logisticsApply->update([
                'remark' => '获取面单失败：' . $exception->getResponse()->getBody()
            ]);
        } catch (\Throwable $ex) {

            Log::channel('logistics')
                ->info('获取面单失败', [
                    'ex' => $ex->getMessage()
                ]);

            $logisticsApply->update([
                'remark' => '获取面单失败：' . $ex->getMessage()
            ]);
        }

        return null;
    }

    /**
     * 查询运单详情
     * @param string $waybillNumber 运单号
     * @param $logisticsApply
     * @return false|mixed
     */
    public function getDsConsignment($waybillNumber, $logisticsApply)
    {

        $method = self::ACTION_GET;
        $timestamp = Carbon::now()->valueOf();
        $params = json_encode(['waybillNumber' => $waybillNumber]);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?user_id=' . $this->userId . '&method=' . $method . '&format=json&timestamp=' . $timestamp . '&sign=' . $sign . '&version=V1.0';

        Log::channel('logistics')
            ->info('查询运单详情', [
                'timestamp' => $timestamp,
                'params' => $params,
                'url' => $url,
                'sign' => $sign,
            ]);

        try {
            $result = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );

            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('查询运单详情响应数据', [
                    'response' => $response
                ]);
            if ($response['success'] && $response['code'] == '0') {
                return $response['data'];
            }

        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('查询运单详情失败', [
                    'exception' => $exception->getResponse()->getBody()
                ]);

        } catch (\Throwable $ex) {

            Log::channel('logistics')
                ->info('查询运单详情失败', [
                    'ex' => $ex->getMessage()
                ]);

        }

        return null;
    }


    /**
     * @return array|boolean
     */
    public function place($package, $logisticsApply)
    {
        Log::channel('logistics')->info('yanwen-申请物流单号-订单状态-1', [$package->status]);

        $sender = WarehouseAddress::first();
        if(!$sender){
            throw new AccidentException('发件人信息不存在', Code::OPERATE_FAIL);
        }

        $invoiceValue = 0;
        $weight = $skuQuantityCount = 0;
        $printRemark = ''; //拣货单信息
        $package->items->each(function($sku) use ($package, $logisticsApply, &$declares, &$invoiceValue, &$weight, &$skuQuantityCount, &$printRemark) {
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if(!$logistics) {
                return false;
            }

            $declareWeight = $logistics->weight;
            $declares[] = [
                'goodsNameCh' => $logistics->cn_name,
                'goodsNameEn' => $logistics->en_name,
                'price' => $logistics->unit_price,
                'quantity' => $sku->quantity,
                'weight' => $logistics->weight,//单品重量(单位:g)
                'hscode' => $logistics->code,#商品海关编码
                'sku' => $sku->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
            ];

            $skuQuantityCount += $sku->quantity;

            //货值
            $invoiceValue += $sku->quantity * $logistics->unit_price;

            $weight += $declareWeight * $sku->quantity;

            $printRemark .= ($sku->lineItem->mapping->goodsSku->sku_id ?? '') . '*' . $sku->quantity . ';';
            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $receiverInfo = [
            'name' => $package->packageAddress->name,
            'phone' => $package->packageAddress->phone,
            'email' => $package->packageAddress->email,
            'company' => $package->packageAddress->company,
            'country' => $package->packageAddress->country_code,
            'state' => $package->packageAddress->province,
            'city' => $package->packageAddress->city,
            'zipCode' => $package->packageAddress->zip,
            'houseNumber' => '',
            'address' => $package->packageAddress->address1 .' '. $package->packageAddress->address2,
            'taxNumber' => $package->packageAddress->tax,
        ];

        $senderInfo = [
            'name' => $sender->receiver_name,
            'phone' => $sender->phone,
            'email' => '',
            'company' => $sender->warehouse_name,
            'country' => 'CN',
            'state' => $sender->province,
            'city' => $sender->city,
            'zipCode' => $sender->postcode,
            'houseNumber' => '',
            'address' => $sender->address,
            'taxNumber' => '',
            'street' => $sender->address,
            'house_number' => '',
        ];

        $parcelInfo = [
            'hasBattery' => '0',#是否带电 1:是 0:否
            'currency' => 'USD',#币种
            'totalWeight' => $weight,#总重量(单位:g)
            'totalPrice' => $invoiceValue,#申报总价值
            'totalQuantity' => $skuQuantityCount,#申报总数量
            'height' => '',
            'width' => '',
            'length' => '',#包裹长(单位:cm)
            'ioss' => '',#IOSS号
            'productList' => $declares,
        ];

        //判断是否为欧盟国家 欧盟国家,收件人税号字段须为空.欧盟税改IOSS号应填写在寄件人税号字段.(639)
        if (in_array($package->packageAddress->country_code, Country::EuropeanUnionMemberStates())) {
            $receiverInfo['taxNumber'] = '';
            $senderInfo['taxNumber'] = $package->packageAddress->tax;
        }

        $data = [
            'channelId' => $package->express_channel_code,//产品编码(产品Id)
            'orderSource' => '',//订单来源
            // 'orderNumber' => $order->order_id, //订单号
            'orderNumber' => $logisticsApply->package_sn, //订单号
            'remark' => $printRemark, // 拣货单信息（打印标签选择“打印拣货单”显示此字段信息）
            'ioss' => $package->packageAddress->tax ?? '', //使用收件人税号

            #收件人信息
            'receiverInfo' => $receiverInfo,

            #发件人信息
            'senderInfo' => $senderInfo,

            #包裹信息
            'parcelInfo' => $parcelInfo,
        ];

        Log::channel('logistics')->info('yanwen-申请物流单号-申报信息-3', $data);

        $res = $this->post(self::ACTION_FORECAST_ORDER, $data);

        Log::channel('logistics')->info('yanwen-申请物流单号-申报结果-4', [$res]);

        if (empty($res) || empty($res['success']) || $res['code'] != '0') {  //申报失败
            $error = $res['message'] ?? '申请运单号失败';
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }

        // 申报成功
        $record = [
            'remark'          => '',
            'agent_number'    => $res['data']['yanwenNumber'] ?? '',#燕文参考单号(唯一)
            'way_bill_number' => $res['data']['waybillNumber'] ?? '',#运单号
            'tracking_number' => $res['data']['referenceNumber'] ?? '',#转单号
        ];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);

        $this->getLabel($res['data']['waybillNumber'], $logisticsApply);

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    /**
     * @param string $action
     * @param array $data
     * @return array|false
     */
    protected function post(string $action, array $data)
    {
        return $this->request($action, $data);
    }

    /**
     * @param string $method
     * @param array $data
     * @return array|false
     */
    protected function request(string $method, array $data)
    {

        $timestamp = Carbon::now()->valueOf();
        $params = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?user_id=' . $this->userId . '&method=' . $method . '&format=json&timestamp=' . $timestamp . '&sign=' . $sign . '&version=V1.0';

        Log::channel('logistics')
            ->info('创建运单', [
                'timestamp' => $timestamp,
                'params' => $params,
                'url' => $url,
                'sign' => $sign,
            ]);

        try {
            $response = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => '*/*',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );
        } catch (GuzzleException $exception) {
            Log::channel('logistics')->info('创建运单失败', [
                'msg' => $exception->getMessage()
            ]);

            return ['msg' => $exception->getMessage()];
        }

        $res = Response::from($response)->result();
        Log::channel('logistics')->info('创建运单返回数据', [$res]);

        return $res;
    }

    /**
     * @return array|false
     */
    public function channels()
    {

        $response = $this->getChannelCode(self::ACTION_OBTAIN_CHANNEL_CODE);

        if ($response) {
            return collect($response['data'])
                ->map(function ($value) {
                    return [
                        'code' => $value['id'],
                        'name' => $value['nameCh'],
                    ];
                })
                ->values()->all();
        }

        return false;
    }

    /**
     * @param string $action
     * @param array $data
     * @return array|false
     */
    protected function getChannelCode(string $action)
    {

        $timestamp = Carbon::now()->valueOf();
        $params = json_encode([]);
        $sign = $this->getSign($action, $timestamp, $params);
        $url = $this->url . '?user_id=' . $this->userId . '&method=' . $action . '&format=json&timestamp=' . $timestamp . '&sign=' . $sign . '&version=V1.0';

        Log::channel('logistics')
            ->info('getChannelCode', [
                'timestamp' => $timestamp,
                'params' => $params,
                'sign' => $sign,
                'url' => $url
            ]);

        try {
            $response = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8',
                    ],
                    'body' => $params
                ]
            );
        } catch (GuzzleException $e) {
            info("{$this->channel} 获取物流渠道失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage());
        }

        return Response::from($response)->result();
    }

    /**
     * 物流轨迹查询
     * @param string $nums 支持订单号、燕文单号、尾程单号
     * 支持一次最多请求 30 个单号，单号之间使用“,”连接；
     * @return array|null
     */
    public function tracking(string $nums)
    {
        $url = $this->trackUrl . '?nums=' . $nums;

        Log::channel('logistics')
            ->info('物流轨迹查询', [
                'url' => $url,
                'authorization' => $this->authorization
            ]);

        try {
            $result = $this->client->request(
                'GET',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8',
                        'Authorization' => $this->authorization
                    ],
                    'body' => $params
                ]
            );
            //处理响应结果
            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('物流轨迹查询响应数据', [
                    'response' => $response
                ]);

            if ($response['code'] == '0') {
                return $response['result'];
            }

        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('物流轨迹查询失败', [
                    'response' => $exception->getResponse()->getBody()
                ]);
        }

        return null;
    }

    /**
     * 生成签名
     */
    protected function getSign($method, $timestamp, $jsonParams)
    {

        $arr = [
            $this->userId,
            $jsonParams,
            'json',
            $method,
            $timestamp,
            'V1.0'
        ];

        ksort($arr);#将这个数组以参数名的字典升序排序

        $str = '';
        foreach ($arr as $key => $value) {
            $str .= $value;
        }

        $sign = md5($this->apiToken . $str . $this->apiToken);

        return $sign;
    }

    /**
     * @throws Exception
     */
    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_YANWEN)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')
                ->info('============当前获取的 燕文 对接配置============', [
                    'info' => $info
                ]);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->userId = $info['user_id'];
            $this->apiToken = $info['api_token'];
            $this->trackUrl = $info['track_url']??'';
            $this->authorization = $info['authorization']??'';
        } else {
            Log::channel('logistics')->info('公司尚未配置燕文对接信息，对接失败');
            throw new AccidentException('尚未配置 燕文 配置信息', Code::OPERATE_FAIL);
        }
    }
}
