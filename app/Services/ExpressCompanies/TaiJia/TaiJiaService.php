<?php

namespace App\Services\ExpressCompanies\TaiJia;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\CompanyExpressModel;
use App\Models\DeclareOrder;
use App\Models\ExpressOrderModel;
use App\Models\HandMovementModel;
use App\Models\Order;
use App\Models\OrderDeclarationModel;
use App\Models\OrderDockingRecordModel;
use App\Models\WarehouseAddress;
use App\Services\Admin\ExpressCompaniesService;
use App\Services\ExpressCompanies\Logistics;
use App\Services\ExpressCompanies\XML;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

class TaiJiaService extends Logistics
{
    public string $url;

    public string $clientId;

    public string $token;

    protected string $channel = CompanyExpressModel::CODE_TAIJIA;

    public const ACTION_CREATE_ORDER        = 'addYBCorder';
    public const ACTION_GET_LABEL           = 'getOrderPrintLabel';
    public const ACTION_GET_SHIPPING_METHOD = 'getChannel';

    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    public function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()->where('type', OrderDockingRecordModel::TYPE_K5)->first();

        if (empty($config)) {
            throw new AccidentException("尚未配置 {$this->channel} 授权信息", Code::OPERATE_FAIL);
        }

        Log::channel($this->channel)->info('config-配置信息', [$config]);

        $this->url      = $config['info']['url'] ?? '';
        $this->clientId = $config['info']['client_id'] ?? '';
        $this->token    = $config['info']['token'] ?? '';
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
                'itemcont'        => $logistics->cn_name,   // 申报中文名称 Length <= 50
                'itemcustoms'     => $logistics->en_name,   // 申报英文名称 Length <= 50
                'itemnum'         => $sku->quantity, // 申报数量,必填
                'itemunit'        => 'USD', // 币种单位
                'itemweight'      => $declareWeight, // 重量
                'itemvalue'       => number_format($logistics->unit_price * $sku->quantity, 2), // 申报价值
                'itemprodno'      => $logistics->code, // 商品海关编码
                'itemsbprice'     => $logistics->unit_price, // 单价
                'itemsku'         => $sku->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
                'texture'         => $logistics->material, // 材质
                'itemapplication' => $logistics->use_to, // 用途
            ];

            $weight += $declareWeight * $sku->quantity;

            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $data = [
            'CreateAndPreAlertOrderService' => [
                'clientid'                           => $this->clientId,
                'authtoken'                          => $this->token,
                'CreateAndPreAlertOrderRequestArray' => [
                    'CreateAndPreAlertOrderRequest' => [
                        'refernumb'           => $logisticsApply->package_sn, // Y 客户订单号
                        'channelid'           => $package->express_channel_code, // Y 运输方式代码
                        //收件人信息
                        'recname'             => $package->packageAddress->first_name . ' ' . $package->packageAddress->last_name, //	收件人姓名
                        'recaddr1'            => $package->packageAddress->address1 ?: '',
                        'recaddr2'            => $package->packageAddress->address2 ?: '',
                        'rectel'              => $package->packageAddress->phone ?: '', // 是	收件人电话
                        'recmobile'           => $package->packageAddress->phone ?: '', // 是	收件人电话
                        'reccity'             => $package->packageAddress->city ?: '', // 收件人城市
                        'recprovince'         => $package->packageAddress->province ?: '', // 收件人省
                        'recpost'             => $package->packageAddress->zip ?: '', // 收件人邮编
                        'reccorp'             => $package->packageAddress->company ?: '', // 收件人公司
                        'country'             => $package->packageAddress->country_code ?: '', // 收件人国家二字代码
                        'recemail'            => $package->packageAddress->email ?: '', // 收件人邮箱
                        'buyeremail'          => $package->packageAddress->email ?: '', // 收件人邮箱

                        //寄件人想信息
                        'sendercorp'          => $sender->receiver_name, // 寄件人公司名
                        'sendername'          => $sender->receiver_name, // 寄件人名
                        'senderaddr'          => $sender->address, // 寄件人地址
                        'senderpost'          => $sender->postcode, // 寄件人邮编
                        'sendercity'          => $sender->city, // 寄件人城市
                        'senderprovince'      => $sender->province, // 寄件人州省
                        'sendercountry'       => 'CN', // 寄件人国家
                        'senderemail'         => '', // 寄件人邮箱
                        'sendertel'           => $sender->phone, // 寄件人电话
                        'taxnumber'           => $package->packageAddress->tax, // 税号
                        // 'vatnumber' => $package->packageAddress->tax, // 税号
                        // 'ioss'      => $package->packageAddress->tax, // 税号
                        'weight'              => $weight, // 包裹总重量
                        'DeclareInvoiceArray' => [
                            'DeclareInvoice' => $declares,
                        ],
                    ],
                ],
            ],
        ];

        Log::channel($this->channel)->info('申请物流单号-申报信息-3', $data);

        try {
            $data       = XML::fromArray($data);
            $resultData = $this->request('POST', self::ACTION_CREATE_ORDER, $data);
        } catch (Exception $e) {
            //申报失败
            return $this->applyLogisticFailure($package, $logisticsApply, $e->getMessage());
        }

        // 申报成功
        $record = [
            'way_bill_number' => $resultData['CreateOrderService']['CreateOrderServiceResponseArray']['CreateOrderServiceResponse']['OrderItem']['corpbillid'] ?? '',
            'tracking_number' => $resultData['CreateOrderService']['CreateOrderServiceResponseArray']['CreateOrderServiceResponse']['OrderItem']['billid'] ?? '',
        ];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);

        //获取物流面单
        $this->getLabel($record['way_bill_number'], $logisticsApply);

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    public function getLabel($sn, $logisticsApply)
    {
        try {
            //获取域名
            $url = $this->removeUrlPath($this->url);

            $body = Request::build()
                           ->setClientId($this->clientId)
                           ->setAuthToken($this->token)
                           ->setUrl($url)
                           ->setPaper('100-100')
                           ->setBillId($sn)
                           ->setContent('1')
                           ->labelToXml();

            $resultData = $this->request('POST', self::ACTION_GET_LABEL, $body);

            $label = $resultData['printlabel']['printurl'] ?? '';
            if ($label) {
                $logisticsApply->update([
                    'remark' => '',
                    'label_url' => $label
                ]);
            }

            return $label;
        } catch (Exception $e) {
            $logisticsApply->update(['remark' => $e->getMessage()]);
            //获取失败
            return '';
        }
    }

    /**
     * @return array|false
     */
    public function channels()
    {
        $response = $this->getChannelCode(self::ACTION_GET_SHIPPING_METHOD);

        if ($response && isset($response['Channel'])) {
            return collect($response['Channel']['channelid'])
                ->combine($response['Channel']['channelname'])
                ->map(function ($name, $code) {
                    return [
                        'code' => $code,
                        'name' => $name,
                    ];
                })
                ->values()->all();
        }

        return false;
    }

    protected function getChannelCode(string $action)
    {
        $body = Request::build()
                       ->setClientId($this->clientId)
                       ->setAuthToken($this->token)
                       ->channelsToXml();

        return $this->request('POST', $action, $body);
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

    /**
     * @param string $method
     * @param string $action
     * @param string $xml
     * @return array|false
     */
    protected function request(string $method, string $action, string $xml): array|false
    {
        Log::channel($this->channel)->info('对接数据', [$xml]);

        try {
            $response = $this->client->request(
                $method,
                $this->url,
                [
                    'form_params' => [
                        'xml'    => $xml,
                        'action' => $action,
                    ],
                ]
            );

            $response = Response::from($response);

            $result = $response->result();

            Log::channel($this->channel)->info('request-响应数据', $result);

            if ($response->isFailed($action)) {
                throw new AccidentException($response->message() ?? 'Api request failed, please check app');
            }

            return $result;
        } catch (Exception $e) {
            Log::channel($this->channel)->info("request-接口请求失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    protected function removeUrlPath($url): string
    {
        $parsed = parse_url($url);

        // 构建新URL（协议 + 域名 + 端口）
        $newUrl = '';

        // 添加协议（默认为http）
        $newUrl .= isset($parsed['scheme']) ? $parsed['scheme'] . '://' : 'http://';

        // 添加主机
        $newUrl .= $parsed['host'] ?? '';

        // 添加端口（如果有）
        if (isset($parsed['port'])) {
            $newUrl .= ':' . $parsed['port'];
        }

        return $newUrl;
    }

}
