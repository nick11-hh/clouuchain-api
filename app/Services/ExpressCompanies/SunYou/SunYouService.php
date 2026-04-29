<?php

namespace App\Services\ExpressCompanies\SunYou;

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
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

/**
 * 顺友物流
 * @docs https://www.sypost.com/docs/index.html
 */
class SunYouService extends Logistics
{
    public string $url;

    public string $userToken;

    public string $developerToken;

    protected string $channel = CompanyExpressModel::CODE_SUNYOU;

    public const ACTION_CREATE_ORDER        = '/createAndConfirmPackages';
    public const ACTION_GET_LABEL           = '/getPackagesLabel';
    public const ACTION_GET_SHIPPING_METHOD = '/findShippingMethods';

    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    public function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()->where('type', OrderDockingRecordModel::TYPE_SUN_YOU)->first();

        if (empty($config)) {
            throw new AccidentException("尚未配置 {$this->channel} 配置信息", Code::OPERATE_FAIL);
        }

        Log::channel($this->channel)->info('config-配置信息', [$config]);

        $this->url            = $config['info']['url'] ?? '';
        $this->userToken      = $config['info']['user_token'] ?? '';
        $this->developerToken = $config['info']['developer_token'] ?? '';
    }

    public function headers()
    {
        return [
            'Content-Type'    => 'application/json',
            'charset'         => 'UTF-8',
            'apiLogUserToken' => $this->userToken,
            'apiDevUserToken' => $this->developerToken,
        ];
    }

    public function place($package, $logisticsApply): bool
    {

        Log::channel($this->channel)->info('申请物流单号-订单状态-1', [$package->status]);

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
                'productSku'      => $sku->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
                'declareEnName'   => $logistics->en_name,   // 申报英文名称 Length <= 50
                'declareCnName'   => $logistics->cn_name,   // 申报中文名称 Length <= 50
                'quantity'        => $sku->quantity, // 申报数量,必填
                'declarePrice'    => $logistics->unit_price, // 申报价格(单价) ,必填
                'currencyCode'    => 'USD', // 海关申报币种 Length = 3 默认：USD 目前只支持USD、GBP、EUR、AUD
                'customCode'      => $logistics->code, // 商品海关编码
                'productMaterial' => $logistics->material, // 材质
                'productPurpose'  => $logistics->use_to, // 用途
            ];

            $weight += $declareWeight * $sku->quantity;

            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $data['packageList'][] = [
            'customerOrderNo'         => $logisticsApply->package_sn, // Y 客户订单号，由客户自定义，不允许重复
            'customerReferenceNo'     => $package->package_sn, // Y客户参考号，由客户自定义，允许重复
            'trackingNumber'          => '', //追踪号码，通常由顺友提供
            'shippingMethodCode'      => $package->express_channel_code, // Y 运输方式代码
            'packageCodAmount'        => 0, // N 代收货款的价值
            'packageCodCurrencyCode'  => '', // N 代收货款的币种
            'packageSalesAmount'      => 0, // N 包裹销售总价值（单位：USD）
            'packageLength'           => 0, // N 包裹长度（单位：CM）
            'packageWidth'            => 0, // N 包裹宽度（单位：CM）
            'packageHeight'           => 0, // N 包裹高度（单位：CM）
            'predictionWeight'        => $weight, // Y 包裹总重量（单位：KG）

            //收件人信息
            'recipientName'           => $package->packageAddress->first_name . ' ' . $package->packageAddress->last_name, //是	收件人姓名
            'recipientCountryCode'    => $package->packageAddress->country_code, // 收件人国家二字代码
            'recipientPostCode'       => $package->packageAddress->zip, // 收件人邮编
            'recipientState'          => $package->packageAddress->province, // 收件人省
            'recipientCity'           => $package->packageAddress->city, // 收件人城市
            'recipientStreet'         => $package->packageAddress->address1, // Y 收件人街道地址
            'recipientStreet1'        => $package->packageAddress->address2, // 收件人地址
            'recipientPhone'          => $package->packageAddress->phone, // 收件人电话
            'recipientMobile'         => $package->packageAddress->phone, // 收件人手机
            'recipientEmail'          => $package->packageAddress->email, // 收件人邮箱
            'recipientTaxNumber'      => $package->packageAddress->tax, // 税号
            'recipientIdentityNumber' => '', // 收件人身份证号码

            //发件人信息
            'senderName'              => $sender->receiver_name, // 发件人姓名 Y
            'senderPhone'             => $sender->phone, // 发件人电话
            'senderPostCode'          => $sender->postcode, // 发件人邮编
            'senderAddress'           => $sender->address, // 发件人地址 Y
            'senderCountryCode'       => 'CN', // 发件人国家二字代码 Y
            'senderState'             => $sender->province, // 发件人省
            'senderCity'              => $sender->city, // 发件人城市
            'senderDistrict'          => '', // 发件人县/区
            'senderEmail'             => '', // 发件人邮箱
            'senderTaxNumber'         => '', // 寄件人税号
            'senderLicenseNumber'     => '', // 寄件人公司执照号
            'iossVatId'               => '', // IOSS欧盟税号、新加坡OVR税号

            /**
             * //包裹属性，例如：“011”、“210”。如果包裹没有任何属性请填入000或者不填。
             * 第一位
             * 0：不含电池
             * 1：含电池
             * 2：纯电池
             * 第二位
             * 0：不含液体及粉末
             * 1：含液体或粉末
             * 第三位
             * 0：不是食品
             * 1：食品
             * Length=3
             */
            'packageAttributes'       => '',

            'productList' => $declares, // 海关申报信息
        ];

        Log::channel($this->channel)->info('申请物流单号-申报信息-3', $data);

        $result = $this->client->request('POST', $this->url . self::ACTION_CREATE_ORDER, [
            'headers' => $this->headers(),
            'json'    => ['data' => $data],
        ]);

        $result = (array)json_decode($result->getBody()->getContents(), true);

        Log::channel($this->channel)->info('申请物流单号-申报结果-4', [$result]);

        $response = new Response($result ?? []);

        //申报失败
        if (!$response->isSuccessful()) { // 申报失败
            $error = $result['errorMsg'] ?? '运单号申请失败，请稍后重试';
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }

        $resultList = $response->result()['resultList'] ?? [];

        $resultData = current($resultList);
        if (strtolower($resultData['processStatus']) === 'failure') {
            $error = implode('；', array_column($resultData['errorList'], 'errorMsg'));
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }

        // 申报成功
        $record = [
            'way_bill_number' => $resultData['syOrderNo'] ?? '',
            'tracking_number' => $resultData['trackingNumber'] ?? ''
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
        $data = [
            'syOrderNoList' => [
                $sn, // 顺友流水号
            ],
            /**
             * * 标签返回时的打包方式
             * 0：返回包含多个面单的单个PDF文件
             * 1：返回包含多个PDF文件的ZIP包，每个PDF文件中仅包含一个包裹的面单信息，仅有一个包裹时也将被打包为ZIP
             * 默认值：0
             */
            'packMethod'    => 0,
            'dataFormat'    => 1, //0：标签返回数据类型为 byte 数组 1：标签返回数据类型为URL路径 默认值：0
        ];

        Log::channel($this->channel)->info('获取订单标签', $data);

        $result = $this->client->request('POST', $this->url . self::ACTION_GET_LABEL, [
            'headers' => $this->headers(),
            'json'    => ['data' => $data],
        ]);

        $result = (array)json_decode($result->getBody()->getContents(), true);
        Log::channel($this->channel)->info('获取订单标签结果', $result);

        $response = new Response($result ?? []);
        if (!$response->isSuccessful()) {
            Log::channel($this->channel)->info('获取订单标签失败', $result);

            throw new AccidentException($result['errorMsg'] ?? "{$this->channel} get label api request failed", Code::OPERATE_FAIL);
        }

        $resultList = $response->result()['resultList'] ?? [];

        $resultData = current($resultList);
        if (strtolower($resultData['processStatus']) === 'failure') {
            throw new AccidentException($resultData['errorMsg'] ?? "{$this->channel} get label failure", Code::OPERATE_FAIL);
        }

        return $response->result()['labelPath'] ?? '';
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
                        'code' => $value['shippingMethodCode'],
                        'name' => $value['shippingMethodCnName'],
                    ];
                })
                ->values()->all();
        }

        return false;
    }

    protected function getChannelCode(string $action)
    {
        try {
            $data = [
                'countryCode' => '', //目的国家二字代码
                'pickupCity'  => '', //揽收城市
                'postCode'    => '', //收件地址邮编
            ];

            $result = $this->client->request('POST', $this->url . $action, [
                'headers' => $this->headers(),
                'json'    => ['data' => $data],
            ]);

            $result = json_decode($result->getBody()->getContents(), true);

            Log::channel($this->channel)->info('getChannelCode-响应数据', $result);

            $response = new Response($result ?? []);

            if (!$response->isSuccessful()) {
                throw new AccidentException($result['errorMsg'] ?? 'Api request failed, please check token');
            }

            return $response->result()['resultList'] ?? [];
        } catch (GuzzleException $e) {
            Log::channel($this->channel)->info("getChannelCode-获取物流渠道失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
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
