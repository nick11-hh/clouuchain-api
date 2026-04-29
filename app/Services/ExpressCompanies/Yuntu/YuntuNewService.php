<?php
namespace App\Services\ExpressCompanies\Yuntu;


use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
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
use App\Models\Package;
use App\Models\ShopTax;
use App\Models\ThirdPartyTrackingLogModel;
use App\Models\WarehouseAddress;
use App\Services\ExpressCompanies\ExpressCompanies;
use App\Services\ExpressCompanies\Logistics;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

class YuntuNewService extends Logistics
{
    protected string $url;

    protected const ACTION_FORECAST_ORDER = '/api/WayBill/CreateOrder'; //运单申请
    protected const ACTION_OBTAIN_CHANNEL_CODE = '/api/Common/GetShippingMethods'; //获取运输方式
    protected const ACTION_GET_FACE = '/api/Label/Print'; //获取面单
    protected const ACTION_TRACK = '/api/Tracking/GetTrackInfo'; //轨迹查询

    protected Client $client;

    protected Order $order;

    protected DeclareOrderModel $declare;

    protected string $username;

    protected string $apiSecret;

    protected string $channel = 'yuntu_new';


    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    /**
     * @param DeclareOrderModel $declare
     * @return $this
     */
    public function setDeclare(DeclareOrderModel $declare): self
    {
        $this->declare = $declare;

        $this->order = $declare->order;

        return $this;
    }

    /**
     * @return bool
     */
    public function placeByDeclare()
    {
        $data = (new Request())
            ->setUsername($this->username)
            ->setDeclare($this->declare)
            ->transformFromDeclare();

        $result = $this->place([$data]);

        if ($result) {
            if ($result['Code'] == '0000') {
                // 面单
                $url = $this->getLabel($result['Item'][0]['WayBillNumber']);

                $this->updateOrderData($result, $url);

                return true;
            }

            if ($result['Code'] > 1000) {
                $this->recordLog($result['Item'][0]['Remark'] ?? '');
                return false;
            }
            $this->recordLog($result['Message'] ?? '');
        }

        return false;
    }

    /**
     * @return bool
     */
    public function placeByBoxes()
    {
        $boxes = $this->declare->boxes;

        $request = (new Request())
            ->setUsername($this->username)
            ->setDeclare($this->declare);

        foreach ($boxes as $box) {
            $data = $request->setDeclareBox($box)
                ->transformFromBox();

            $result = $this->place([$data]);

            if ($result) {
                if ($result['Code'] == '0000') {
                    // 面单url  PDF文件地址 （无接口）
                    $url = $this->getLabel($result['Item'][0]['WayBillNumber']);

                    // 返回有面单
                    $this->updateBoxData($box->box, $result, $url);
                } else {
                    if ($result['Code'] > 1000) {
                        $this->recordLog($result['Item'][0]['Remark'] ?? '');
                        return false;
                    }
                    $this->recordLog($result['Message'] ?? '');
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param OrderBoxesModel $box
     * @param array $result
     * @param $url
     * @return void
     */
    public function updateBoxData(OrderBoxesModel $box, array $result, $url)
    {
        $box->update([
            'logistics_sn' => $result['Item'][0]['WayBillNumber'],
            'logistics_company' => 'YunTuNewLogistics',
        ]);

        $this->order->dockingRecords()->create(
            [
                'type' => 17,
                'data' => ['url' => $url],
                'company_id' => $this->declare['company_id']
            ]
        );
    }

    /**
     * 获取面单
     * @param string $sn
     * @return false|mixed
     */
    public function getLabel(string $sn, LogisticsApplyModel $logisticsApply)
    {
        try {
            $result = $this->client->request(
                'POST',
                $this->url . self::ACTION_GET_FACE,
                [
                    'headers' => [
                        'Authorization' => $this->getToken(),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'json' => [$sn]
                ]
            );

            $response = Response::from($result)->result();
            info('云途物流 响应数据', $response);
            if ($response['Code'] == '0000') {
                $logisticsApply->update([
                    'label_url' => $response['Item'][0]['Url'] ?? '',
                    'remark' => '',
                ]);

                return $response['Item'][0]['Url'] ?? false;
            }
            if ($response['Code'] > 1) {
                return false;
            }
        } catch (GuzzleException|\Throwable $ex) {
            info('云途物流 获取面单失败', ['exception' => $ex->getMessage()]);
            return false;
        }

        return false;
    }

    /**
     * @return array|boolean
     */
    public function place($package, $logisticsApply)
    {
        Log::channel('logistics')->info('yuntu_new-申请物流单号-1');

        $sender = WarehouseAddress::first();
        if (!$sender) {
            throw new AccidentException('发件人信息不存在', Code::OPERATE_FAIL);
        }


        $declares = [];
        $weight = $skuQuantityCount = 0;
        $package->items->each(function ($item) use ($logisticsApply, &$declares, &$weight, &$skuQuantityCount) {
            $logistics = OrderDeclarationModel::query()->where('order_item_id', $item->lineItem->id)->first();
            if(!$logistics) {
                return true;
            }
            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'EName'               => $logistics->en_name,   // 包裹申报名称(英文)必填
                'CName'               => $logistics->cn_name,   // 包裹申报名称(中文)非必填
                'HSCode'              => $logistics->code, // 商品海关编码
                'Quantity'            => $item->quantity, // 申报数量,必填
                'UnitPrice'           => $logistics->unit_price, // 申报价格(单价) ,必填
                'UnitWeight'          => sprintf("%.3f", $declareWeight), // 申报重量(单重)，单位 kg
                'CurrencyCode'        => 'USD', // 申报币种，默认USD，英国支持GBP/EUR，欧盟国家支持EUR
                'Remark'              => ($item->lineItem->mapping->goodsSku->sku_id ?? '') .'*'. $item->quantity,//订单备注
                'SKU'                 => $item->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
                'InvoicePart'         => $logistics->material,
                'InvoiceUsage'        => $logistics->use_to,
            ];

            //货物件数
            $skuQuantityCount += $item->quantity;

            $weight  += $declareWeight * $item->quantity;

            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $receiver = [];
        if(!empty($package->packageAddress)) {
            $receiver = [ // 收件人
                'CountryCode'         => $package->packageAddress->country_code, // 收件人所在国家，填写国际通用标准 2 位简码，可通过国家查询服务查询
                'FirstName'           => $package->packageAddress->first_name, // 收件人姓
                'LastName'            => $package->packageAddress->last_name, // 收件人姓
                'Street'              => $package->packageAddress->address1,  // 收件人详细地址
                'StreetAddress1'      => $package->packageAddress->address2 ?? '',
                'StreetAddress2'      => '',
                'State'               => $package->packageAddress->province, // 省/州
                'City'                => $package->packageAddress->city, // 收件人所在城市
                'Phone'               => $package->packageAddress->phone,    // 收件人手机号
                'Zip'                 => $package->packageAddress->zip,      // 邮编
                'Company'             => $package->packageAddress->company, //企业
                'CertificateCode'     => $package->packageAddress->tax, //收件人ID，中东专线-约旦国家必填 数字类型
            ];
        }

        $senderAddress = [
            'CountryCode' => 'CN',
            'FirstName' => $sender->name,
            'LastName' => '',
            'Company' => $sender->receiver_name,
            'Street' => '',
            'City' => $sender->city,
            'State' => $sender->province,
            'Zip' => $sender->postcode,
            'Phone' => $sender->phone,
        ];


        $applyData = [
            'CustomerOrderNumber' => $logisticsApply->package_sn, // 客户订单号,不能重复
            'ShippingMethodCode'  => $package->express_channel_code, // 运输方式代码
            // 'PackageCount'        => $skuQuantityCount, // 运单包裹的件数，必须大于 0 的整数
            'PackageCount'        => 1, // 运单包裹的件数，必须大于 0 的整数
            'Weight'              => sprintf("%.3f", $weight), // 预估包裹总重量，单位 kg,最多 3 位小数
            'Receiver'            => $receiver,
            'Parcels'             => $declares,
            'Sender'              => $senderAddress,
            'IossCode'            => ''
        ];

        // 设置税号
        $tax = ExpressCompanies::getShopTaxV2($package->orders[0]);
        if ($tax) {
            if ($tax->tax_region === ShopTax::REGION_OTHER) {
                $applyData['tax_number'] = $tax->tax_number;
            } else {
                match ($tax->tax_type) {
                    ShopTax::TYPE_EORI =>  $applyData['EoriNumber'] = $tax->tax_number,
                    ShopTax::TYPE_IOSS => $applyData['IossCode'] = $tax->tax_number,
                    ShopTax::TYPE_PAID_BY_AGENT => $applyData['OrderExtra'] = [[
                        'ExtraCode' => 'V1',
                        'ExtraName' => '云途预缴'
                    ]],
                    ShopTax::TYPE_ENGLAND => $applyData['tax_number'] = $tax->tax_number
                };
            }
        }

        Log::channel('logistics')->info('yuntu_new-申请物流单号-申报信息-3', [$applyData]);


        info('apply_data', $applyData);

        $res = $this->post(self::ACTION_FORECAST_ORDER, [$applyData]);

        Log::channel('logistics')->info('yuntu_new-申请物流单号-申报结果-4', [$res]);

        if ($res) {
            if(!empty($res['Item'])) {
                foreach ($res['Item'] as $item) {
                    if (!$item['Success']) {  // 申报失败
                        $error = $item['Remark'] ?? '申请运单号失败';
                        return $this->applyLogisticFailure($package, $logisticsApply, $error);
                    }

                    // 申报成功
                    $item['CustomerOrderNumber'] = $logisticsApply->package_sn;
                    $record = [
                        'track_type'      => $item['TrackType'] ?? 2,
                        'remark'          => $item['Remark'] ?? '',
                        'sender_address'  => $item['RequireSenderAddress'] ?? '',
                        'agent_number'    => $item['AgentNumber'] ?? '',
                        'way_bill_number' => $item['WayBillNumber'] ?? '',
                        'tracking_number' => $item['TrackingNumber'] ?? '',
                        'shipper_boxs'    => $item['ShipperBoxs'] ?? [],
                    ];
                    $this->applyLogisticSuccess($package, $logisticsApply, $record);

                    $this->getLabel($record['way_bill_number'], $logisticsApply);

                    // 同步到仓库
                    $this->syncToWarehouse($package, $logisticsApply);
                }
            } else {
                $error = $res['Message'] ?? '申请运单号失败';
                return $this->applyLogisticFailure($package, $logisticsApply, $error);
            }
            return true;
        }

        return false;
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
        Log::channel('logistics')->info('yuntu_new-request', [
            'data' => $data,
            'query' => $this->url . $method,
            'header' => [
                'Authorization' => $this->getToken(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'charset' => 'UTF-8'
            ]
        ]);

        try {
            $response = $this->client->request(
                'POST',
                $this->url . $method,
                [
                    'headers' => [
                        'Authorization' => $this->getToken(),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'json' => $data
                ]
            );
        } catch (GuzzleException $exception) {
            Log::channel('logistics')->info('yuntu_new-订单云途物流对接失败', [
                $exception->getMessage(),
                $exception->getLine(),
                $exception->getFile(),
            ]);

            return ['msg' => $exception->getMessage()];
        }

        $res = Response::from($response)->result();

        Log::channel('logistics')->info('yuntu_new-request-response', [$res]);

        return $res;
    }

    /**
     * @return array|false
     */
    public function channels(): bool|array
    {
        $response = $this->getChannelCode(self::ACTION_OBTAIN_CHANNEL_CODE);

        if ($response) {
            return collect($response['Items'])
                ->map(function ($value) {
                    return [
                        'code' => $value['Code'],
                        'name' => $value['CName'],
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
        info('请求路由', ['query' => $this->url . $action]);
        info('Authorization', ['username' => $this->username, 'apiSecret' => $this->apiSecret, 'Authorization' => $this->getToken()]);
        try {
            $response = $this->client->request(
                'GET',
                $this->url . $action,
                [
                    'headers' => [
                        'Authorization' => $this->getToken(),
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                ]
            );
        } catch (GuzzleException $e) {
            info("{$this->channel} 获取物流渠道失败", ['msg' => $e->getMessage()]);

            $message = $e->getMessage();

            if ($e->hasResponse()) {
                // 响应体
                $body = $e->getResponse()->getBody()->getContents();
                $body = json_decode($body, true);

                $message = $body['ResultDesc'] ?? '拒绝访问,身份验证失败';
            }

            throw new AccidentException($message);
        }

        return Response::from($response)->result();
    }

    /**
     * @param array $result
     * @param $url
     * @return void
     */
    public function updateOrderData(array $result, $url)
    {
        Order::query()
            ->whereKey($this->order->getKey())
            ->update([
                'logistics_sn' => $result['Item'][0]['WayBillNumber'],
                'logistics_company' => 'YunTuNewLogistics',
            ]);

        $this->order->dockingRecords()->delete();

        $this->order->dockingRecords()->create(
            [
                'type' => 17,
                'data' => ['url' => $url],
                'company_id' => $this->declare['company_id']
            ]
        );
    }

    public function tracking(string $sn)
    {
        info('物流轨迹数据',['sn' => $sn]);

        try {
            $response = $this->client->get(
                $this->url . self::ACTION_TRACK,
                [
                    'headers' => [
                        'Authorization' => $this->getToken(),
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'json' => [
                        'OrderNumber' => $sn
                    ]
                ]
            );
            //处理响应结果
            $result = json_decode($response->getBody()->getContents(), true);

            if ($result['Code'] == '0000') {
                return $result;
            }

            return null;
        } catch (GuzzleException $exception) {
            info('订单云途物流对接失败', ['msg' => $exception->getMessage()]);

            return null;
        }

    }

    /**
     * @param $result
     * @return void
     */
    public function recordLog($result)
    {
        ThirdPartyTrackingLogModel::query()->create([
            'declare_id' => $this->declare->id,
            'content' => $result ?? '',
        ]);
    }

    /**
     * 获取token
     * @return string
     */
    public function getToken()
    {
        return 'Basic '.base64_encode($this->username.'&'.$this->apiSecret);
    }

    /**
     * @throws Exception
     */
    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_YUN_TU_NEW_LOGISTICS)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')
                ->info('============当前获取的 云途物流 对接配置============', [
                    'info' => $info
                ]);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->username = $info['username'];
            $this->apiSecret = $info['apiSecret'];
        } else {
            throw new AccidentException('尚未配置 云途物流 配置信息', Code::OPERATE_FAIL);
        }
    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        // TODO: Implement getDsConsignment() method.
    }
}
