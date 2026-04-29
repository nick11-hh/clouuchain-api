<?php

namespace App\Services\ExpressCompanies\UBI;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\DeclareOrderModel;
use App\Models\ExpressOrderModel;
use App\Models\HandMovementModel;
use App\Models\LogisticsApplyModel;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\Order;
use App\Models\OrderBoxesModel;
use App\Models\OrderDockingRecordModel;
use App\Models\OrderDeclarationModel;
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
 * UBI
 */
class UBIService extends Logistics
{
    protected string $url;

    protected const ACTION_FORECAST_ORDER = '/services/shipper/orders'; //运单申请/创建订单
    protected const ACTION_TRACK = '/services/shipper/trackingEvents'; //轨迹查询/获取跟踪信息
    protected const ACTION_OBTAIN_CHANNEL_CODE = '/services/shipper/service-catalog'; //获取运输方式/获取开通的服务
    protected const ACTION_GET_FACE = '/services/shipper/labels'; //打印标签

    protected Client $client;

    protected Order $order;

    protected DeclareOrderModel $declare;

    protected string $token;

    protected string $key;

    protected string $channel = 'ubi';

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    /**
     * 获取面单
     * @param string $sn 客户端的订单唯一标识
     * @param string $method API接口名称
     * @return false|mixed
     */
    public function getLabel(string $sn, $logisticsApply)
    {
        $labelSpec = $order->channel->spec ?? '10*15';
        $labelType = match ($labelSpec) {
            '10*10' => 4,
            '10*15' => 1,
            'A4' => 2,
            default => 0,
        };

        $data['orderIds'][] = $sn;
        $data['labelType'] = $labelType;#0表示默认值10cm * 15cm，1表示10cm * 15cm，2表示A4，4表示10cm * 10cm，目前API只支持这三种格式；

        $method = 'POST';
        $url = $this->url . self::ACTION_GET_FACE;
        $wallTechDate = $this->getWallTechDate();
        $authorization = $this->getAuthorization($method, $wallTechDate, $url);

        Log::channel('logistics')
            ->info('获取面单', [
                'method' => $method,
                'url' => $url,
                'wallTechDate' => $wallTechDate,
                'authorization' => $authorization
            ]);

        try {
            $result = $this->client->request(
                $method,
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8',
                        'X-WallTech-Date' => $wallTechDate,
                        'Authorization' => $authorization
                    ],
                    'json' => $data
                ]
            );

            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('获取面单响应数据', [
                    'response' => $response
                ]);
            if ($response['status'] == 'Success') {
                #保存面单
                $fileName = '/'. $response['data'][0]['trackingNo'] .'.pdf';
                // $path = '/storage/admin/ubi/'. Carbon::now()->format('Ymd');
                $label = base64_decode($response['data'][0]['labelContent']);

                Storage::disk()->put('admin'.$fileName, $label);

                $labelUrl = config('app.url') . '/storage/admin' . $fileName;
                $logisticsApply->update([
                    'label_url' => $labelUrl
                ]);

                return $labelUrl;

            }else{

                $logisticsApply->update([
                    'remark' => '获取面单失败：' . $response['errors']
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
     * @return array|boolean
     */
    public function place($package, $logisticsApply)
    {
        $data = $declares = [];

        Log::channel('logistics')->info('ubi-申请物流单号-订单状态-1', [$package->status]);

        $sender = WarehouseAddress::first();
        if(!$sender){
            throw new AccidentException('发件人信息不存在', Code::OPERATE_FAIL);
        }

        $invoiceValue = 0;
        $weight = 0;
        $package->items->each(function($sku) use ($package, $logisticsApply, &$declares, &$invoiceValue, &$weight) {
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if(!$logistics) {
                return false;
            }

            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'itemNo' => $sku->product_id,#Item编号
                'sku' => $sku->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
                'description' => $logistics->en_name,#英文品名
                'nativeDescription' => $logistics->cn_name,
                'originCountry' => 'CN',#原产国
                'itemCount' => $sku->quantity,
                'unitValue' => $logistics->unit_price,
                'weight' => $declareWeight,
            ];

            //货值
            $invoiceValue += $sku->quantity * $logistics->unit_price;

            $weight  += $declareWeight * $sku->quantity;

            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        #单票创建
        $data[] = [
            // 'referenceNo' => $order->order_id,#客户端的订单唯一标识
            'referenceNo' => $logisticsApply->package_sn,#客户端的订单唯一标识
            'serviceCode' =>  $package->express_channel_code,//服务代码
            'description' => $declares[0]['description'] ?? '',//英文品名
            'nativeDescription' => $declares[0]['nativeDescription'] ?? '',
            'weight' => sprintf("%.3f", $weight), //重量
            'weightUnit' => 'KG',
            'invoiceValue' => $invoiceValue,#货值(>=0.01)，与sum(itemCount * unitValue)的误差不能超过0.1
            // 'invoiceCurrency' => $order->currency,
            'invoiceCurrency' => "USD", //报关的价格是美元，这里不取订单的货币
            #收件人
            'recipientName' => $package->packageAddress->name,
            'recipientTaxId' => '',#收件人税号
            'addressLine1' => $package->packageAddress->address1,
            'addressLine2' => $package->packageAddress->address2,
            'city' => $package->packageAddress->city,
            'postcode' => $package->packageAddress->zip,
            'state' => $package->packageAddress->province,
            'country' => $package->packageAddress->country_code,
            'phone' => $package->packageAddress->phone,
            #发件人 需要转成拼音
            'shipperName' => $sender->receiver_name,
            'shipperPhone' => $sender->phone,
            'shipperAddressLine1' => $sender->address,
            'shipperCity' => $sender->city,
            'shipperState' => $sender->province,
            'shipperPostcode' => $sender->postcode,
            'shipperCountry' => 'CN',

            //Item信息
            'orderItems' => $declares
        ];

        Log::channel('logistics')->info('ubi-申请物流单号-申报信息-3', $data);

        $res = $this->post(self::ACTION_FORECAST_ORDER, $data);

        Log::channel('logistics')->info('ubi-申请物流单号-申报结果-4', [$res]);

        if (empty($res) || strtolower($res['status']) != 'success') {
            $error = '';
            if(isset($res['errors'])){

                foreach ($res['errors'] as $key => $value) {
                    if($key > 2) break;#只显示3条错误，不然字段长度不够
                    $error .= 'ErrorCode[' . $value['code'] . ']，' . $value['message'] . ';';
                }
            }
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }

        //
        $record = [
            'agent_number'    => $res['data'][0]['orderId'] ?? '',#UBI订单号
            'remark'          => '',
            'way_bill_number' => $res['data'][0]['trackingNo'] ?? '',#运单号
            'tracking_number' => $res['data'][0]['trackingNo'] ?? '',#跟踪号
        ];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);

        $this->getLabel($logisticsApply->package_sn, $logisticsApply);

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
        $httpMethod = 'POST';
        $url = $this->url . $method;
        $wallTechDate = $this->getWallTechDate();
        $authorization = $this->getAuthorization($httpMethod, $wallTechDate, $url);

        Log::channel('logistics')
            ->info('创建订单', [
                'method' => $method,
                'url' => $url,
                'wallTechDate' => $wallTechDate,
                'authorization' => $authorization
            ]);

        try {
            $response = $this->client->request(
                $httpMethod,
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => '*/*',
                        'charset' => 'UTF-8',
                        'X-WallTech-Date' => $wallTechDate,
                        'Authorization' => $authorization
                    ],
                    'json' => $data
                ]
            );
        } catch (ClientException $e) {
            Log::channel('logistics')->info('UBI对接数据没有通过校验', [
                'msg' => $e->getResponse()->getBody()
            ]);

            return ['msg' => $e->getResponse()->getBody()];
        } catch (ServerException $e) {
            Log::channel('logistics')->info('UBI系统内部发生错误', [
                'msg' => $e->getResponse()->getBody()
            ]);

            return ['msg' => $e->getResponse()->getBody()];
        } catch (TransferException $e) {
            Log::channel('logistics')->info('UBI网络请求超时，请重新对接', [
                'msg' => $e->getMessage()
            ]);

            return ['msg' => $e->getMessage()];
        } catch (GuzzleException $exception) {
            Log::channel('logistics')->info('UBI创建订单对接失败', [
                'msg' => $exception->getMessage()
            ]);

            return ['msg' => $exception->getMessage()];
        }

        $res = Response::from($response)->result();

        Log::channel('logistics')->info('创建订单返回数据', [$res]);

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
                        'code' => $value['serviceCode'],
                        'name' => $value['nativeName']??$value['serviceName'],
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
        $method = 'GET';
        $url = $this->url . $action;
        $wallTechDate = $this->getWallTechDate();
        $authorization = $this->getAuthorization($method, $wallTechDate, $url);

        Log::channel('logistics')
            ->info('getChannelCode', [
                'method' => $method,
                'url' => $url,
                'wallTechDate' => $wallTechDate,
                'authorization' => $authorization
            ]);

        try {
            $response = $this->client->request(
                $method,
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8',
                        'X-WallTech-Date' => $wallTechDate,
                        'Authorization' => $authorization
                    ]
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
     * 一个JSON格式的跟踪号(trackingNo)序列，一次最多30个。
     * @return array|null
     */
    public function tracking(string $nums)
    {
        $data[] = $nums;

        $method = 'POST';
        $url = $this->url . self::ACTION_TRACK;
        $wallTechDate = $this->getWallTechDate();
        $authorization = $this->getAuthorization($method, $wallTechDate, $url);

        Log::channel('logistics')
            ->info('物流轨迹查询', [
                'method' => $method,
                'url' => $url,
                'wallTechDate' => $wallTechDate,
                'authorization' => $authorization
            ]);

        try {
            $result = $this->client->request(
                $method,
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8',
                        'X-WallTech-Date' => $wallTechDate,
                        'Authorization' => $authorization
                    ],
                    'json' => $data
                ]
            );
            //处理响应结果
            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('物流轨迹查询响应数据', [
                    'response' => $response
                ]);

            if ($response['status'] == 'Success') {
                return $response['data'];
            }

        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('物流轨迹查询失败', [
                    'response' => $exception->getResponse()->getBody()
                ]);
        }

        return null;
    }

    protected function getWallTechDate()
    {
        return Carbon::now('UTC')->format(DATE_RFC7231,Carbon::now()->timestamp-60*60*8);
    }

    protected function getAuthorization($method, $wallTechDate, $url)
    {
        $auth = $method."\n". $wallTechDate ."\n".$url;
        $hash = base64_encode(hash_hmac('sha1', $auth, $this->key, true));

        return 'WallTech '. $this->token .':'.$hash;
    }

    /**
     * @throws Exception
     */
    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_UBI)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')
                ->info('============当前获取的 UBI 对接配置============', [
                    'info' => $info
                ]);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->token = $info['token'];
            $this->key = $info['key'];
        } else {
            Log::channel('logistics')->info('公司尚未配置UBI对接信息，对接失败');
            throw new AccidentException('尚未配置 UBI 配置信息', Code::OPERATE_FAIL);
        }
    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        // TODO: Implement getDsConsignment() method.
    }
}
