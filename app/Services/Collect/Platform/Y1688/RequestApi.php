<?php

namespace App\Services\Collect\Platform\Y1688;

use App\Jobs\PurchaseToInboundOrderJob;
use App\Lib\Code;
use App\Models\PurchaseAccountModel;
use App\Models\PurchaseOrderLogs;
use App\Models\PurchaseOrdersItemsModel;
use App\Models\PurchaseOrdersModel;
use App\Models\SystemConfig;
use App\Services\Base\SystemConfigService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Redis;
use Nette\Utils\DateTime;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Exceptions\AccidentException;

class RequestApi
{
    protected string $baseUrl = 'https://gw.open.1688.com/openapi';

    protected string $baseUri = 'param2/1';

    protected string $appKey;

    protected string $appSecret;

    protected string $token;

    protected Client $client;

    protected array $header = [
        "Accept" =>  "application/json"
    ];

    public function __construct()
    {
        $option = [
            'base_uri' => $this->baseUrl,
            'timeout'  => 60,
            'verify'   => false,
        ];

        $this->appKey = config('alibaba.app_key');
        $this->appSecret = config('alibaba.app_secret');
        $this->token = config('alibaba.app_token');

        $this->client = new client($option);
    }


    /**
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function getProductDetail($params, $language)
    {
        $uri = 'com.alibaba.fenxiao.crossborder/product.search.queryProductDetail';
        $requestParams = [
            'offerDetailParam' => json_encode($params),
            'language' => $language
        ];
        return $this->request($uri, data: $requestParams);
    }

    /**
     * @param $params
     * @param $language
     * @return mixed
     * @throws Exception
     */
    public function getProductList($params, $language)
    {
        $uri = 'com.alibaba.fenxiao.crossborder/product.search.keywordQuery';
        $requestParams = [
            'offerQueryParam' => json_encode($params),
            'language' => $language
        ];
        $result = $this->request($uri, 'GET', $requestParams);
        if ($result['result']['code'] != 200 || empty($result['result']['result'])) {
            throw new AccidentException('获取产品列表失败', Code::OPERATE_FAIL);
        }
        return $result['result']['result'];
    }


    /**
     * @param $params
     * @param $language
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function productImageSearch($params, $language)
    {
        $uri = 'com.alibaba.fenxiao.crossborder/product.search.imageQuery';
        $requestParams = [
            'offerQueryParam' => json_encode($params),
            'language' => $language
        ];
        $result = $this->request($uri, 'GET', $requestParams);
        if ($result['result']['code'] != 200) {
            throw new AccidentException('获取产品列表失败', Code::OPERATE_FAIL);
        }
        return $result['result']['result'];
    }

    public function uploadImage($image)
    {
        $uri = 'com.alibaba.fenxiao.crossborder/product.image.upload';
        $imageContent = file_get_contents($image);
        $requestParams = [
            'uploadImageParam' => json_encode([
                'imageBase64' => base64_encode($imageContent)
            ]),
        ];
        return $this->request($uri, 'POST', $requestParams, 'form_params');
    }

    /**
     * 获取旺旺昵称
     * @param $openUid
     * @return mixed
     */
    public function getWangWangNick($openUid)
    {
        $uri = 'com.alibaba.account/wangwangnick.openuid.decrypt';
        $params = [
            'openUid' => $openUid
        ];

        $res = $this->request($uri, 'GET', $params);
        info($openUid . '转换解密为旺旺昵称接口', ['res' => $res]);

        return $res;
    }

    /**
     * @param bool $throw
     * @return void
     * @throws Exception
     */
    protected function setToken(bool $throw = true)
    {
        $token = PurchaseAccountModel::where(['platform'=> '1688', 'state' => 1, 'enable' => 1])->value('token');
        if(!$token && $throw) {
            throw new AccidentException('自动采购失败，请先添加采购账号并授权和启用', Code::OPERATE_FAIL);
        }
        $this->token = $token;
    }


    /**
     * @param $apiName
     * @param string $method
     * @param array $data
     * @param string $dataFormat
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    protected function request($apiName, string $method = 'GET', array $data = [], string $dataFormat = ''): mixed
    {
        $baseData = [
            'access_token' => $this->token,
        ];

        $data = array_merge($data, $baseData);
        $data['_aop_timestamp'] = (int)(microtime(true) * 1000);
        $uri = "{$this->baseUri}/{$apiName}/{$this->appKey}";
        $data['_aop_signature'] = $this->sign($uri, $data);
        $method = strtoupper($method);

        try {
            $option = ['headers' => $this->header];

            if (empty($dataFormat)) {
                if ($method === 'GET') {
                    $option['query'] = $data;
                } else {
                    $option['json'] = $data;
                }
            } else {
                $option[$dataFormat] = $data;
            }

            info('请求参数', $option);
            $response = $this->client->request($method, $this->baseUrl .'/'. $uri, $option);
            $content = $response->getBody()->getContents();
            // info('1688请求返回接口', [$content]);
            return json_decode($content, true);
        } catch (GuzzleException $e) {
            info('1688接口请求失败', [$e->getMessage()]);
            throw new AccidentException('1688接口请求失败', Code::OPERATE_FAIL);
        }
    }

    /***
     * @param $uri
     * @param $data
     * @return string
     */
    protected function sign($uri, $data): string
    {
        ksort($data);
        $signStr = '';
        foreach ($data as $key => $value) {
            $signStr .=  "{$key}{$value}";
        }
        $sign = hash_hmac("sha1", $uri . $signStr, $this->appSecret, true );
        return strtoupper(bin2hex($sign));
    }

    /**
     * 获取买家保存的收货地址信息列表
     * 返回默认收货地址id
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function getReceiveAddress()
    {
        $uri = 'com.alibaba.trade/alibaba.trade.receiveAddress.get';

        $res = $this->request($uri);
        info('买家获取保存的收货地址信息列表', $res);

        if(!$res['success']) {
            throw new AccidentException('收货地址获取失败', Code::OPERATE_FAIL);
        }
        if(empty($res['result']['receiveAddressItems'])) {
            throw new AccidentException('请在买家工作台先填写收货地址', Code::OPERATE_FAIL);
        }

        $addrs = array_filter($res['result']['receiveAddressItems'], function($item) {
            return $item['isDefault'];
        });

        if(empty($addrs)) {
            throw new AccidentException('自动采购请先在买家工作台设置默认收货地址', Code::OPERATE_FAIL);
        }

        sort($addrs);
        // return $addrs[0]['id'];
        return $addrs[0];
    }

    /**
     * 创建1688采购订单
     * @param array $goods
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function createCrossOrder($order_id, array $goods): bool
    {
        $this->setToken();
        $address = $this->getReceiveAddress();

        $order_sn = PurchaseOrdersModel::where('id', $order_id)->value('order_sn');
        $addressText = explode(' ', $address['addressCodeText']);
        $newAddress = [
            // 'addressId'    => $address['id'],
            'fullName'     => $address['fullName'],
            'mobile'       => $address['mobilePhone'],
            'phone'        => $address['phone'] ?? $address['mobilePhone'],
            'postCode'     => $address['post'],
            'cityText'     => $addressText[1],
            'provinceText' => $addressText[0],
            'areaText'     => $addressText[2],
            'townText'     => $address['townName'] ?? '',
            'address'      => $address['address'] . '-' . $order_sn,
            'districtCode' => $address['addressCode'],
        ];
        $params = [
            'flow' => 'general',
            'addressParam' => json_encode($newAddress),
            // 'addressParam' => json_encode([
            //     'addressId' => $address   // 收货地址id
            // ]),
            'cargoParamList' => json_encode($goods),
        ];

        try {
            $uri = 'com.alibaba.trade/alibaba.trade.createCrossOrder';
            $res = $this->request($uri, 'POST', $params, dataFormat: 'form_params');

            if ($res['success']) {
                // PurchaseOrdersItemsModel::where('id', $purchase_goods_id)->update(['purchase_order_id'=> $res['result']['orderId']]);

                if($res['result']['orderId']) {
                    $orderId = $res['result']['orderId'];
                } else {
                    $orderIds = array_column($res['result']['orderList'], 'orderId');
                    $orderId = $orderIds[0];
                }

                PurchaseOrdersModel::where('id', $order_id)->update(['platform_sn' => $orderId]);

                //添加日志
                $logData = [
                    'purchase_id' => $order_id,
                    'operator_type' => PurchaseOrderLogs::OPERATOR_TYPE_STATUS_CHANGE,
                    'content' => '1688批量下单成功',
                ];
                PurchaseOrderLogs::addLog($logData);

                // 发起免密支付，直接从绑定的支付宝中扣除订单金额
                // $this->preparePay($orderId);
            } else {
                throw new AccidentException('创建订单失败：'. $res['message'], Code::OPERATE_FAIL);
            }
        } catch (Exception $e) {
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }

        info('创建1688采购订单返回结果', $res);
        return true;
    }

    /**
     * 取消1688采购订单
     * @param $orderId 1688平台采购订单id
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function cancelOrder($orderId): bool
    {
        $uri = 'com.alibaba.trade/alibaba.trade.cancel';
        $params = [
            'webSite' => 1688,
            'tradeID' => $orderId,
            'cancelReason' => 'buyerCancel'
        ];

        try {
            $res = $this->request($uri, 'POST', $params);
        } catch (Exception $e) {
            info('取消1688采购单失败', [$e->getMessage()]);
            throw new AccidentException('取消1688采购单失败: '.$e->getMessage(), Code::OPERATE_FAIL);
        }

        return $res['success'];
    }

    /**
     * 1688订单发起免密支付
     * @param $orderId
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function preparePay($orderId)
    {
        $uri = 'com.alibaba.trade/alibaba.trade.pay.protocolPay.preparePay';
        $params = [
            'tradeWithholdPreparePayParam' => json_encode(['orderId' => (int)$orderId])
        ];

        $res = $this->request($uri, 'POST', $params);

        return $res['success'];
    }

    /**
     * 获取授权链接
     * @return string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function alibabaAuthorize($id): string
    {
        $redirect_uri = config('app.url').'/api/admin/1688/global/auth';
        // $redirect_uri = 'https://dev-api.haiouoms.com/api/alibaba/oauth';
        // $state = env('APP_URL');
        // $state = str_replace('http://','',$state);
        // $state = str_replace('https://','',$state);

        $uuid = request()->get('uuid');
        // return "https://auth.1688.com/oauth/authorize?client_id={$this->appKey}&site=1688&redirect_uri={$redirect_uri}&uuid={$uuid}&auth_id={$id}&state={$state}";
        return "https://auth.1688.com/oauth/authorize?client_id={$this->appKey}&site=1688&redirect_uri={$redirect_uri}&uuid={$uuid}&auth_id={$id}";
    }

    /**
     * 获取授权token并保存
     * @throws Exception
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws GuzzleException
     */
    public function generateAccessToken(): bool
    {
        $code = request()->get('code');
        $uuid = request()->get('uuid');
        $auth_id = request()->get('auth_id');
        $query = $_SERVER["QUERY_STRING"];
        info('url参数数据', [$query]);

        if (!$code) {
            info('code 不能为空');
            return false;
            // throw new AccidentException('code 不能为空', Code::OPERATE_FAIL);
        }

        $params = [
            'grant_type' => 'authorization_code',
            'need_refresh_token' => true,
            'client_id' => $this->appKey,
            'client_secret' => $this->appSecret,
            'code' => $code,
        ];

        info('1688授权请求参数', $params);
        $res = $this->getPlatformToken($params);

        if (!empty($res)) {

            PurchaseAccountModel::where('id', $auth_id)->update([
                'state' => 1,
                'token' => $res['access_token'],
                'auth_time' => now()
            ]);

            Redis::client()->set('1688_access_token_'.$uuid, $res['access_token']);
            return true;
        }

        return false;
    }

    /**
     * 获取token
     * @throws Exception
     */
    public function getToken($uuid)
    {
        $token = Redis::client()->get('1688_access_token_'.$uuid);
        if (!$token) {
            throw new AccidentException('操作失败，请先授权1688账号', Code::OPERATE_FAIL);
        }

        return $token;
    }

    /**
     * 获取平台授权token
     * @param $params
     * @return array|mixed
     * @throws Exception
     * @throws GuzzleException
     */
    public function getPlatformToken($params): mixed
    {
        try {
            $requestUrl = 'https://gw.open.1688.com/openapi/http/1/system.oauth2/getToken/'.$this->appKey;

            $result = $this->client->request('POST', $requestUrl,
                [
                    'verify'            => false,
                    "headers"           => [
                        "Accept"        =>  "application/json"
                    ],
                    "query"             => $params,
                ]
            );

            $res = json_decode((string)$result->getBody(), true);
            info('1688授权结果', $res);
            if (isset($res['access_token'])) {
                return $res;
            }
            return [];
        } catch (Exception $e) {
            logger($e->getMessage());
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    /**
     * 同步1688订单物流信息
     * @param $orderItem
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function syncOrdersStatus($orderItem): bool
    {
        $this->setToken();

        return $this->syncOrdersInfo($orderItem);

        // 获取交易订单的物流信息(买家视角)
        $url = 'com.alibaba.logistics/alibaba.trade.getLogisticsInfos.buyerView';

        $params = [
            'webSite' => 1688,
            'orderId' => $orderItem->platform_sn,
        ];

        try {
            $res = $this->request($url, 'GET', $params);
            info('1688采购单的物流信息(买家视角)', ['$res' => $res]);

            if ($res['success']) {
                // 成功获取1688物流信息逻辑
                // 更新物流单号
                $result = $res['result'][0];
                $orderItem->update(['shipment_number' => $result['logisticsBillNo'], 'status' => PurchaseOrdersModel::STATUS_WAIT_STORAGE]);

                //关闭快进快出 生成入库单
                $fast = SystemConfigService::getConfigValue(SystemConfig::FAST_IN_FAST_OUT);
                if (empty($fast)) {
                    dispatch(new PurchaseToInboundOrderJob([$orderItem->id]));
                }
            } else {
                info($orderItem->platform_sn.'当前无物流信息', $res);
            }
            return true;
        } catch (Exception $e) {
            info('获取1688采购单的物流信息(买家视角)失败', [$e->getMessage()]);
            return false;
        }
    }

    /**
     * 同步1688订单物流信息
     * @param $orderItem
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function syncOrdersInfo($orderItem): bool
    {
        // 获取交易订单的物流信息(买家视角)
        $url = 'com.alibaba.trade/alibaba.trade.get.buyerView';

        $params = [
            'webSite' => 1688,
            'orderId' => $orderItem->platform_sn,
        ];

        try {
            $res = $this->request($url, 'GET', $params);
            info('1688采购单的订单详情(买家视角)', ['$res' => $res]);

            if ($res['success']) {
                $content = "同步1688信息-成功：";

                // 成功获取1688订单详情
                $updateData = [];

                //基础信息
                $baseInfo = $res['result']['baseInfo'] ?? [];
                $payTime = $baseInfo['payTime'] ?? ''; //支付时间
                $allDeliveredTime = $baseInfo['allDeliveredTime'] ?? ''; //完全发货时间

                $content .= "订单状态：{$baseInfo['status']}";

                if ($payTime) {
                    $updateData['pay_time'] = $this->toDate($payTime); //支付时间

                    $content .= "，支付时间：{$updateData['pay_time']}";
                }

                if ($allDeliveredTime) {
                    $updateData['delivered_time'] = $this->toDate($allDeliveredTime); //完全发货时间

                    $content .= "，发货时间：{$updateData['delivered_time']}";
                }

                //物流信息 取第一个物流信息
                $logistics = $baseInfo['nativeLogistics']['logisticsItems'][0] ?? [];
                if (!empty($logistics)) {
                    $updateData['logistics_company_name'] = $logistics['logisticsCompanyName'] ?? ''; //物流公司名称
                    $updateData['shipment_number'] = $logistics['logisticsBillNo'] ?? '';//物流公司运单号

                    $updateData['status'] = PurchaseOrdersModel::STATUS_WAIT_STORAGE;//待入库
                    $content .= "，物流单号：{$updateData['shipment_number']}";
                    $content .= "，订单状态调整为 待入库";

                    //关闭快进快出 生成入库单
                    $fast = SystemConfigService::getConfigValue(SystemConfig::FAST_IN_FAST_OUT);
                    if (empty($fast)) {
                        dispatch(new PurchaseToInboundOrderJob([$orderItem->id]));
                    }
                }

                // 更新采购订单
                $orderItem->update($updateData);

                //添加日志
                $logData = [
                    'purchase_id' => $orderItem->id ?? 0,
                    'operator_type' => PurchaseOrderLogs::OPERATOR_TYPE_1688_ORDER,
                    'content' => $content,
                ];
                PurchaseOrderLogs::addLog($logData);
            } else {
                info($orderItem->platform_sn.'1688采购单的订单详情(买家视角)失败', $res);

                $content = "同步1688信息-失败：". json_encode($res);
                //添加日志
                $logData = [
                    'purchase_id' => $orderItem->id ?? 0,
                    'operator_type' => PurchaseOrderLogs::OPERATOR_TYPE_1688_ORDER,
                    'content' => $content,
                ];
                PurchaseOrderLogs::addLog($logData);
            }
            return true;
        } catch (Exception $e) {
            info('1688采购单的订单详情(买家视角)异常', [$e->getMessage()]);
            return false;
        }
    }

    /**
     * @param $time 20240421184611000+0800
     * @return string
     */
    public function toDate($time): string
    {
        if (empty($time)) {
            return $time;
        }

        // 移除末尾的'+0800'时区信息，因为DateTime类不支持解析Zulu时间格式
        $timeStrNoTimezone = strstr($time, '+', TRUE);

        //去除最后三位毫秒
        $timeStrNoTimezone = substr($timeStrNoTimezone,0,-3);

        // 创建DateTime对象
        $dateTime = DateTime::createFromFormat('YmdHis', $timeStrNoTimezone);

        // 检查是否解析成功
        if (false !== $dateTime) {
            // 输出格式化后的时间
            return $dateTime->format('Y-m-d H:i:s'); // 输出为：2023-01-01 12:34:56
        }

        return '';
    }

}
