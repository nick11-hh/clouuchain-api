<?php

namespace App\Services\ExpressCompanies\YiDa;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\DeclareOrder;
use App\Models\ExpressOrderModel;
use App\Models\HandMovementModel;
use App\Models\Order;
use App\Models\OrderDeclarationModel;
use App\Models\OrderDockingRecordModel;
use App\Models\WarehouseAddress;
use App\Services\Admin\ExpressCompaniesService;
use App\Services\ExpressCompanies\Logistics;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

class YiDaService extends Logistics
{
    public $url;

    public $appKey;

    public $appToken;

    protected string $channel = 'yida';

    public const ACTION_CREATE_ORDER = 'createorder';

    public const ACTION_GET_LABEL = 'getnewlabel';

    public const ACTION_GET_SHIPPING_METHOD = 'getshippingmethod';

    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    public function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()->where('type', OrderDockingRecordModel::TYPE_YI_DA)->first();

        if (empty($config)) {
            throw new AccidentException('尚未配置 yida 配置信息', Code::OPERATE_FAIL);
        }

        Log::channel('yida')->info('config-配置信息', [$config]);

        $this->url = $config['info']['url'] ?? '';
        $this->appKey = $config['info']['app_key'] ?? '';
        $this->appToken = $config['info']['app_token'] ?? '';
    }

    protected function transform($operate, $params): array
    {
        return [
            'appKey' => $this->appKey,
            'appToken' => $this->appToken,
            'serviceMethod' => $operate,
            'paramsJson' => json_encode($params)
        ];
    }

    public function place($package, $logisticsApply): bool
    {

        Log::channel('yida')->info('yida-申请物流单号-订单状态-1', [$package->status]);

        $sender = WarehouseAddress::query()->first();
        if (empty($sender)) {
            throw new AccidentException('发件人信息不存在，请设置仓库信息', Code::OPERATE_FAIL);
        }

        //海关申报信息
        $declares = [];
        $weight = 0;
        $package->items->each(function ($sku) use ($package, &$declares, &$weight) {

            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if (empty($logistics)) {
                return false;
            }

            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'sku' => $sku->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
                'invoice_enname' => $logistics->en_name,   // 包裹申报名称(英文)必填
                'invoice_cnname' => $logistics->cn_name,   // 包裹申报名称(中文)非必填
                'invoice_quantity' => $sku->quantity, // 申报数量,必填
                'unit_code' => '',// 单位
                'invoice_unitcharge' => $logistics->unit_price, // 申报价格(单价) ,必填
                'jp_hs_code' => '', // 日本hs code
                'invoice_currencycode' =>'USD', // 申报币种，默认USD，英国支持GBP/EUR，欧盟国家支持EUR
                'hs_code' => $logistics->code, // 商品海关编码
                'invoice_note' => '', // 配货信息
                'invoice_url' => '', // 销售地址
                'invoice_info' => '', // 商品图片地址
                'invoice_material' => $logistics->material, // 材质
                'invoice_spec' => $sku->mapping->goodsSku->spec_name ?? '', // 规格
                'posttax_num' => '', // 国内报关申报价值
                'country_origin' => '', // 国内报关币种
                'register_code' => '', // 店铺名称(长度100)
                'invoice_store_names' => '', // 店铺名称(长度300)
                'invoice_use' => $logistics->use_to, // 用途
            ];

            $weight += $declareWeight * $sku->quantity;

            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        //发件人信息
        $shipper = [
            'shipper_name' => $sender->receiver_name, // 发件人姓名 Y
            'shipper_company' => $sender->receiver_name, // 发件人公司
            'shipper_countrycode' => 'CN', // 发件人国家二字代码 Y
            'shipper_province' => $sender->province, // 发件人省
            'shipper_city' => $sender->city, // 发件人城市
            'shipper_district' => '', // 发件人县/区
            'shipper_street' => $sender->address, // 发件人地址 Y
            'shipper_street2' => '', // 发件人地址2
            'shipper_postcode' => $sender->postcode, // 发件人邮编
            'shipper_areacode' => '', // 发件人区域代码
            'shipper_telephone' => $sender->phone, // 发件人电话
            'shipper_mobile' => $sender->phone, // 发件人手机 Y
            'shipper_email' => '', // 发件人邮箱
            'shipper_fax' => '', // 发件人传真
            'shipper_certificatetype' => '', // 发件人证件号码类型代码(S:税号)
            'shipper_certificatecode' => '', // 发件人证件号码
        ];

        //收件人信息
        $consignee = [
            'consignee_name' => $package->packageAddress->first_name.' '.$package->packageAddress->last_name, //是	收件人姓名
            'consignee_company' => '', //收件人公司名
            'consignee_countrycode' => $package->packageAddress->country_code, // 收件人国家二字代码
            'consignee_province' => $package->packageAddress->province, // 收件人省
            'consignee_city' => $package->packageAddress->city, // 收件人城市
            'consignee_district' => '', // 收件人县/区
            'consignee_street' => $package->packageAddress->address1, // 是	收件人地址
            'consignee_street2' => $package->packageAddress->address2, // 收件人地址2
            'consignee_postcode' => $package->packageAddress->zip, // 收件人邮编
            'consignee_doorplate' => '', // 收件人门牌号
            'consignee_areacode' => '', // 收件人区域代码
            'consignee_telephone' => $package->packageAddress->phone, // 是	收件人电话
            'consignee_mobile' => $package->packageAddress->phone, // 收件人手机
            'consignee_email' => $package->packageAddress->email, // 收件人邮箱
            'consignee_katakana' => '', // 收件人片假名
            'consignee_fax' => '', // 收件人传真
            'consignee_certificatetype' => '', // 证件类型代码 ID：身份证 PP：护照
            'consignee_certificatecode' => '', // 证件号码
            'consignee_credentials_period' => '', // 证件有效期
            'consignee_tariff' => $package->packageAddress->tax, // 税号
        ];

        //额外服务
        $extraService = [
            'extra_servicecode' => '', // 额外服务类型代码
            'extra_servicevalue' => '', // 额外服务值（有些额外服务类型必须传此值）
            'extra_servicenote' => '', // 备注
        ];

        $data = [
            'reference_no' => $logisticsApply->package_sn, // 是	客户参考号
            'shipping_method' => $package->express_channel_code, // 是	运输方式代码
            'shipping_method_no' => '', // 服务商单号
            'order_weight' => $weight, // 订单重量，单位KG，默认为0.2
            'order_pieces' => 1, // 外包装件数,默认1
            'cargotype' => 'W', // 货物类型 W：包裹 D：文件 B：袋子
            'mail_cargo_type' => 4, // 包裹申报种类 1：Gif礼品 2：CommercialSample 商品货样 3：Document 文件 4：Other 其他 默认4
            'VatNum' => '', // 英国VAT税号
            'IossNum' => '', // 欧盟IOSS税号
            'return_sign' => 'N', // 是否需要标识退件退回 (Y,N)
            'buyer_id' => '', // EORI
            'order_info' => '', // 订单备注
            'platform_id' => '', // 平台ID（如果您是电商平台，请联系我们添加并确认您对应的平台ID）
            'custom_hawbcode' => '', // 自定义单号

            'shipper' => $shipper,//发件人信息
            'consignee' => $consignee,//收件人信息
            'invoice' => $declares, // 海关申报信息
            'extra_service' => $extraService,//额外服务
        ];

        Log::channel('yida')->info('yida-申请物流单号-申报信息-3', $data);

        $result = $this->client->request('POST', $this->url, [
            'form_params' => $this->transform(self::ACTION_CREATE_ORDER, $data)
        ]);

        $result = (array)json_decode($result->getBody()->getContents(), true);

        Log::channel('yida')->info('yida-申请物流单号-申报结果-4', [$result]);

        $response = new Response($result ?? []);

        //申报失败
        if (!$response->isSuccessful()) { // 申报失败
            $error = $result['cnmessage'] ?? '运单号申请失败，请稍后重试';
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }

        $resultData = $response->result();
        // 申报成功
        $record = [
            'way_bill_number'        => $resultData['shipping_method_no'] ?? ''
        ];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);

        //获取物流面单
        app()->make(ExpressCompaniesService::class)->getLabel($package);

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    public function getLabel($sn, $logisticsApply)
    {
        $packageSn = $logisticsApply->package_sn;

        $data = [
            'configInfo' => [
                'lable_file_type' => '2', // 是 标签文件类型 1：PNG文件 2：PDF文件
                'lable_paper_type' => '1', // 是	纸张类型 1：标签纸(10*10厘米) 2：A4纸(21*29.7厘米)
                'lable_content_type' => '1', // 是	标签内容类型代码 1：标签 2：报关单 3：配货单 4：标签+报关单 5：标签+配货单 6：标签+报关单+配货单
                'additional_info' => [
                    'lable_print_invoiceinfo' => 'N', // 标签上打印配货信息 (Y:打印 N:不打印) 默认 N:不打印
                    'lable_print_buyerid' => 'N', // 标签上是否打印买家ID (Y:打印 N:不打印) 默认 N:不打印
                    'lable_print_datetime' => 'Y', // 标签上是否打印日期 (Y:打印 N:不打印) 默认 Y:打印
                    'customsdeclaration_print_actualweight' => 'N', // 报关单上是否打印实际重量 (Y:打印 N:不打印) 默认 N:不打印
                ],
            ],
            'listorder' => [
                ['reference_no' => $packageSn], //是	客户参考号
            ]
        ];

        Log::channel('yida')->info('yida-获取订单标签', $data);

        $result = $this->client->request('POST', $this->url, [
            'form_params' => $this->transform(self::ACTION_GET_LABEL, $data)
        ]);

        $result = (array)json_decode($result->getBody()->getContents());
        Log::channel('yida')->info('yida-获取订单标签结果', $result);

        $response = new Response($result ?? []);
        if (!$response->isSuccessful()) {
            Log::channel('yida')->info('yida-获取订单标签失败', $result);

            throw new AccidentException('义达物流商：' . $result['cnmessage'], Code::OPERATE_FAIL);
        }

        $result = $response->result();
        $label = (array)current($result);//取第一个标签
        return $label['lable_file'] ?? '';
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
                        'name' => $value['cnname'],
                    ];
                })
                ->values()->all();
        }

        return false;
    }

    protected function getChannelCode(string $action)
    {
        try {
            $result = $this->client->request('POST', $this->url, [
                'form_params' => $this->transform($action, [])
            ]);

            $result = json_decode($result->getBody()->getContents(), true);

            Log::channel('yida')->info('yida-channels-响应数据', $result);

            $response = new Response($result ?? []);

            if (!$response->isSuccessful()) {
                return false;
            }

            return $response->result();
        } catch (GuzzleException $e) {
            info("{$this->channel} 获取物流渠道失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage());
        }
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

}
