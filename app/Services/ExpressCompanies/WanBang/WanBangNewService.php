<?php
namespace App\Services\ExpressCompanies\WanBang;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\DeclareOrderModel;
use App\Models\Order;
use App\Models\OrderDeclarationModel;
use App\Models\OrderDockingRecordModel;
use App\Models\ShopTax;
use App\Models\WarehouseAddress;
use App\Services\ExpressCompanies\ExpressCompanies;
use App\Services\ExpressCompanies\Logistics;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Exceptions\AccidentException;

/**
 * 万邦速达（新）
 */
class WanBangNewService extends Logistics
{
    protected string $url;

    protected string $nonce;

    protected string $token;

    protected string $account;

    protected string $warehouseCode;

    protected Client $client;

    protected Order $order;

    protected DeclareOrderModel $declare;

    protected string $channel = 'wanbang_new';

    public const ACTION_AUTHORIZE           = '/api/whoami';
    public const ACTION_CREATE_ORDER        = '/api/parcels';
    public const ACTION_GET_ORDER           = '/api/parcels/%s';
    public const ACTION_GET_LABEL           = '/api/parcels/%s/label';
    public const ACTION_GET_SHIPPING_METHOD = '/api/services';
    public const ACTION_GET_WAREHOUSE       = '/api/warehouses';

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    /**
     * @return void
     */
    public function setNonce()
    {
        $this->nonce = uniqid();
    }

    /**
     * @param $package
     * @param $logisticsApply
     * @return bool
     * @throws AccidentException
     * @throws GuzzleException
     * @throws \Throwable
     */
    public function place($package, $logisticsApply)
    {
        Log::channel('logistics')->info('wanBangNew-申请物流单号-1');

        $sender = WarehouseAddress::first();
        if (!$sender) {
            throw new AccidentException('发件人信息不存在', Code::OPERATE_FAIL);
        }

        //海关申报信息
        $declares = [];
        $weight = $totalPrice = 0;
        $package->items->each(function ($sku) use ($package, &$declares, &$weight, &$totalPrice) {
            // 判断是否为手动报关
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if (empty($logistics)) return false;

            $declareWeight = $logistics->weight / 1000;

            $declares[]    = [
                'GoodsId'        => $sku->lineItem->quoteGoodsSku->sku_id ?? $sku->lineItem->mapping->goodsSku->sku_id, //货物编号	如SKU,库存编号
                'GoodsTitle'     => $sku->lineItem->quoteGoodsSku->spec_name, //货物描述
                'DeclaredNameEn' => $logistics->en_name, //必须	英文申报名称
                'DeclaredNameCn' => $logistics->cn_name, //必须	中文申报名称
                'DeclaredValue'  => [
                    'Code'  => 'USD',
                    'Value' => $logistics->unit_price,
                ], //必须	单件申报价值	{ "Code": "USD", "Value": 15 }
                'WeightInKg'     => $logistics->weight, //必须	单件重量(KG)	1.5
                'Quantity'       => $sku->quantity, //必须	件数
                'HSCode'         => $logistics->code, //海关编码
                'CaseCode'       => '', //箱号(一票多件)	Box123
                'SalesUrl'       => '', //销售平台链接	http://www.amazon.co.uk/gp/product/B00FEDIPQ4
                'IsSensitive'    => false, //是否为敏感货物/带电/带磁等
                'Brand'          => '', //品牌
                'Model'          => '', //型号
                'MaterialCn'     => $logistics->material, //材质（中文）
                'MaterialEn'     => $logistics->material, //材质（英文）
                'UsageCn'        => $logistics->use_to, //用途（中文）
                'UsageEn'        => $logistics->use_to, //用途（英文）
                'Manufacturer'   => null, //在目的国海关备案的制造商
            ];

            $weight     += $declareWeight * $sku->quantity;

            $totalPrice += $logistics->unit_price * $sku->quantity;

            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        //发件人
        $senderData = [
            'CountryCode' => 'CN', //发件人国家代码
            'Province'    => $sender->province, //发件人省份
            'City'        => $sender->city, //发件人城市
            'Postcode'    => $sender->postcode, //发件人邮编
            'Name'        => $sender->receiver_name, //发件人名称/公司名称
            'Address'     => $sender->address, //发件人地址
            'Email'       => '', //发件人邮箱
            'Tel'         => $sender->phone, //发件人电话
            /**
             * 发件人税号信息，可传多个
             * VatNo: 增值税号，默认值。英国脱欧后，进入英国货物需要提供此值
             * EORI: B2B类型以及B2C的高价值包裹必须提供EORI
             * IOSS: 欧盟2021税改后，进入欧盟货物需要提供 IOSS
             */
            'Taxations'   => [
                [
                    'TaxType' => '',
                    'Number'  => '',
                ]
            ],
        ];

        // 设置收件人税号
        $tax = ExpressCompanies::getShopTaxV2($package->orders[0]);
        if ($tax) {
            if ($tax->tax_region === ShopTax::REGION_OTHER) {
                $applyData['TaxNumber'] = $tax->tax_number;
            } else {
                match ($tax->tax_type) {
                    ShopTax::TYPE_EORI => $applyData['EoriNumber'] = $tax->tax_number,
                    ShopTax::TYPE_IOSS => $applyData['IossCode'] = $tax->tax_number,
                    ShopTax::TYPE_ENGLAND => $applyData['tax_number'] = $tax->tax_number,
                    default => '',
                };
            }
        }

        //收件人
        $receiver = [
            'Company'     => $package->packageAddress->company, //联系人公司
            'Street1'     => $package->packageAddress->address1, //必须	街道1
            'Street2'     => $package->packageAddress->address2, //街道2
            'Street3'     => '', //街道3
            'City'        => $package->packageAddress->city, //必须	城市
            'Province'    => $package->packageAddress->province, //视所属国家有无州省而定	州/省
            'CountryCode' => $package->packageAddress->country_code, //国家代码（ISO 3166-1 alpha-2标准）,创建包裹时国家代码与国家英文名称二者至少得有一个	US
            'Country'     => $package->packageAddress->country, //国家英文名称	United States
            'Postcode'    => $package->packageAddress->zip, //视所属国家有无邮编而定	邮编
            'Contacter'   => $package->packageAddress->first_name . ' ' . $package->packageAddress->last_name, //必须	收件人	zhangsan
            'Tel'         => $package->packageAddress->phone, //收件人联系电话
            'Email'       => $package->packageAddress->email, //收件人邮箱
            'TaxId'       => $tax ?: '', //收件人/收件公司税号。不同国家叫法不完全一样，如美国为 Tax Identification Number，巴西为 CPF/CNPJ，墨西哥为 RFC/CURP。

        ];

        $data = [
            'ReferenceId'          => $logisticsApply->package_sn, //客户订单号 Y
            'SellingPlatformOrder' => null, //销售平台订单信息
            'ShippingAddress'      => $receiver, //收件人地址信息
            'WeightInKg'           => sprintf("%.3f", $package->weight ?: $weight), //包裹重量(单位:KG)
            'ItemDetails'          => $declares, //包裹件内明细
            //包裹总金额
            'TotalValue'           => [
                'Code'  => 'USD',
                'Value' => $totalPrice,
            ],
            //包裹尺寸
            'TotalVolume'          => [
                'Length' => $package->length ?: 1,
                'Width'  => $package->width ?: 1,
                'Height' => $package->height ?: 1,
                'Unit'   => 'CM',
            ],
            'WithBatteryType'      => 'NOBattery', //包裹是否含有带电产品
            'Notes'                => '', //包裹备注
            'BatchNo'              => $package->package_sn, //批次号或邮袋号
            'WarehouseCode'        => $this->warehouseCode, // 交货仓库代码，请参考查询仓库接口	SZ
            'ShippingMethod'       => $package->express_channel_code, // 发货产品服务代码
            // 'ShippingMethod'       => 'ewghjdshagtywe', // 发货产品服务代码
            'ItemType'             => 'SPX', //包裹类型 DOC-文件 SPX-包裹
            // 'TradeType' => '', //订单交易类型(B2B, B2C)，默认为 B2C
            // 'TrackingNumber' => '', //预分配跟踪号
            // 'ImportLabelUrl' => '', //派送标签 客户自行导入跟踪号模式下，可以选择导入派送标签的地址。必须为可访问的 HTTP/HTTPS 链接，且需要保证在包裹履约期间均可访问。（如保持3个月有效期）
            // 'IsMPS' => false, //快递一票多件
            // 'MPSType' => null, //快递一票多件类型
            // 'AllowRemoteArea' => true, //是否允许偏远区域下单，默认值为 true 如果设置值为false，并且地址属于偏远区域，此接口则会返回代码为 0x10008E 的错误
            // 'AutoConfirm' => false, //自动确认交运包裹，或此值为true，则无须再调用确认交运包裹接口

            /**
             * 发件人地址与税号信息
             * 发件人信息必须提供 英文或者拼音的名称与地址以及联系方式
             * 英国脱欧后，进入英国货物需要提供 VatNo
             * 欧盟2021税改后，进入欧盟货物需要提供 IOSS
             * 按美国 T86 新规，进入美国的包裹需要提供完整的发件人信息(发件人名称，具体地址、邮箱、电话)方可清关。系统优先使用包裹上的发件人地址信息进行清关，如果包裹上未提供此信息，系统则取客户资料上留存的默认发件地址。
             * B2B类型或者B2C的高价值包裹必须填写 EORI
             */
            'ShipperInfo'          => null,
        ];

        $response = $this->request('POST', self::ACTION_CREATE_ORDER, $data);

        if ($response->isFailed()) {
            return $this->applyLogisticFailure($package, $logisticsApply, $response->message());
        }

        $resultData = $response->data();
        // 申报成功
        $record = [
            'way_bill_number' => $resultData['TrackingNumber'] ?? '',
            'tracking_number' => $resultData['ProcessCode'] ?? ''
        ];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);

        //获取物流面单
        $this->getLabel($record['way_bill_number'], $logisticsApply);

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    /**
     * @param $sn
     * @param $logisticsApply
     * @return string
     * @throws AccidentException
     * @throws GuzzleException
     * @throws \Throwable
     */
    public function getLabel($sn, $logisticsApply)
    {
        $processCode = $logisticsApply->tracking_number ?? '';
        throw_unless(
            $processCode,
            new Exception("{$this->channel} ProcessCode can not be empty", Code::OPERATE_FAIL),
        );

        //物流跟踪号为空时先获取跟踪号
        if (empty($logisticsApply->way_bill_number)) {
            $action = sprintf(self::ACTION_GET_ORDER, $processCode);
            $parcel = $this->request('GET', $action)->data();
            $sn = $parcel['TrackingNumber'] ?? '';
            if ($sn) $logisticsApply->update(['way_bill_number' => $sn]);
        }

        if (empty($sn)) $sn = $processCode;

        $action = sprintf(self::ACTION_GET_LABEL, $processCode);
        Log::channel('logistics')->info('获取订单标签', [$action]);

        $label = $this->request('GET', $action);

        // 保存面单
        $time = date('ymdHis');
        $fileName = "/{$sn}_{$time}.pdf";

        // 本地存储
        if (config('app.local_storage')) {
            Storage::disk('admin_public')->put($fileName, $label);
        } else {
            Storage::disk()->put('admin'.$fileName, $label);
        }
        $labelUrl = config('app.url').'/storage/admin'.$fileName;

        $logisticsApply->update([
            'label_url' => $labelUrl
        ]);

        return $labelUrl;
    }

    /**
     * @throws Exception
     */
    public function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_WAN_BANG_NEW)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')
                ->info('============当前获取的 万邦 对接配置============', [
                    'info' => $info
                ]);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }
            $this->account = $info['account'] ?? '';
            $this->token = $info['token'] ?? '';
            $this->warehouseCode = $info['warehouseCode'] ?? '';
        } else {
            Log::channel('logistics')->info('公司尚未配置万邦速达对接信息，对接失败');
            throw new AccidentException('尚未配置 万邦速达 配置信息', Code::OPERATE_FAIL);
        }
    }

    /**
     * @return false
     * @throws AccidentException
     */
    public function channels()
    {
        $response = $this->getChannelCode(self::ACTION_GET_SHIPPING_METHOD);

        if ($response && isset($response['ShippingMethods'])) {
            return collect($response['ShippingMethods'])
                ->map(function ($item) {
                    return [
                        'code' => $item['Code'],
                        'name' => $item['Name'],
                    ];
                })
                ->values()->all();
        }

        return false;
    }

    /**
     * @param string $action
     * @return array
     * @throws AccidentException
     */
    protected function getChannelCode(string $action)
    {
        try {
            return $this->request('GET', $action)->data();
        } catch (GuzzleException $e) {
            Log::channel('logistics')->info("getChannelCode-接口请求失败", ['msg' => $e->getMessage()]);

            throw new \Exception($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    /**
     * @param string $action
     * @return array
     * @throws AccidentException
     */
    protected function getWarehouse(string $action)
    {
        try {
            return $this->request('GET', $action)->data();
        } catch (GuzzleException $e) {
            Log::channel('logistics')->info("getChannelCode-接口请求失败", ['msg' => $e->getMessage()]);

            throw new \Exception($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    public function tracking(string $sn)
    {
        // TODO: Implement getDsConsignment() method.
    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        // TODO: Implement getDsConsignment() method.
    }

    //-------------------------------------------------- Request -----------------------------------------------------------

    /**
     * @param $method
     * @param $path
     * @param $data
     * @return Response|string
     * @throws AccidentException
     * @throws GuzzleException
     */
    protected function request($method, $path, $data = [])
    {
        try {
            $option = [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => $this->getAuthorization(),
                ]
            ];
            $option['json'] = $data;

            $result = $this->client->request($method, "{$this->url}{$path}", $option);

            //面单返回文件流
            $contentType = $result->getHeaderLine('Content-Type');
            if (str_contains($contentType, 'application/pdf') || str_contains($contentType, 'image/png')) {
                return $result->getBody()->getContents();
            }

            $result = (array)json_decode($result->getBody()->getContents(), true);

            $response = (new Response($result));

            Log::channel('logistics')->info("{$method} {$path} 接口返回", array_merge($option, [$response->result()]));

            return $response;
        } catch (RequestException $e) {
            Log::channel('logistics')->info("请求失败", array_merge($option, ['message' => $e->getMessage()]));

            if ($e->getResponse()->getStatusCode() === 401) {
                throw new AccidentException("{$this->channel} authorization failed", Code::OPERATE_FAIL);
            }

            throw new AccidentException("{$this->channel} 接口请求失败：{$e->getMessage()}", Code::OPERATE_FAIL);
        } catch (ConnectException $e) {
            // 连接异常处理，不能调用 hasResponse()
            Log::channel('logistics')->info("接口连接异常", array_merge($option, ['message' => $e->getMessage()]));

            // 返回适当的错误信息或重试
            throw new AccidentException("{$this->channel} 接口连接异常：{$e->getMessage()}", Code::OPERATE_FAIL);
        }
    }

    /**
     * @return string
     */
    protected function getAuthorization(): string
    {
        $this->setNonce();

        return "Hc-OweDeveloper {$this->account};{$this->token};{$this->nonce}";
    }
}
