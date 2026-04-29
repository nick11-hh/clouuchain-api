<?php

namespace App\Services\ExpressCompanies\FeiTe;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\DeclareOrder;
use App\Models\ExpressOrderModel;
use App\Models\HandMovementModel;
use App\Models\LogisticsApplyModel;
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

class FeiTeService extends Logistics
{
    public string $url;

    public string $token;

    public string $username;

    public string $password;

    public string $syncPlatformFlag;

    public string $labelUrl;

    public string $labelToken;

    public string $labelUsername;

    public string $labelPassword;

    public const ACTION_GET_SHIPPING_METHOD = '/BaseInfo/GetPostTypes';

    public const ACTION_CREATE_ORDER = '/api/OrderSyn/ErpUploadOrder';

    public const ACTION_GET_ACCESS_TOKEN = '/api/auth/Authorization/GetAccessToken';

    public const ACTION_GET_LABEL = '/api/label/LabelProvider/GetLabelBatchExt';

    protected string $channel = 'feite';


    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    public function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()->where('type', OrderDockingRecordModel::TYPE_FEI_TE)->first();

        if (empty($config)) {
            throw new AccidentException('尚未配置 feite 授权信息', Code::OPERATE_FAIL);
        }
        Log::channel('feite')->info('config-授权信息', [$config]);

        $info = $config['info'] ?? [];

        $this->url = $info['url'] ?? ''; //请求url
        $this->token = $info['token'] ?? ''; //Token令牌
        $this->username = $info['username'] ?? ''; //物流-账号
        $this->password = $info['password'] ?? ''; //物流-密码
        $this->syncPlatformFlag = $info['sync_platform_flag'] ?? ''; //物流-订单同步平台标识

        $this->labelUrl = $info['label_url'] ?? ''; //标签-url
        $this->labelUsername = $info['label_username'] ?? ''; //标签-用户名
        $this->labelPassword = $info['label_password'] ?? ''; //标签-密码
    }

    protected function transform($params): array
    {
        return [
            'Token' => $this->token,
            'UAccount' => $this->username,
            'Password' => strtoupper(md5($this->password)),
            'OrderList' => $params,
        ];
    }

    public function place($package, $logisticsApply): bool
    {

        Log::channel('feite')->info('feite-申请物流单号-订单状态-1', [$package->status]);

        $sender = WarehouseAddress::query()->first();
        if (empty($sender)) {
            throw new AccidentException('发件人信息不存在，请设置仓库信息', Code::OPERATE_FAIL);
        }

        //海关申报信息
        $orderItems = $declares = [];
        $weight = 0;
        $package->items->each(function ($sku) use (&$declares, &$weight, &$orderItems) {

            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if (empty($logistics)) {
                return false;
            }

            //订单明细
            $orderItems[] = [
                'Color' => '', // 颜色
                'ColorCode' => '', // 颜色代码：#000000
                'Freight' => '', // 运费
                'ItemId' => $sku->lineItem->line_item_id, // 物品id[ebay等销售平台订单必填]
                'ItemName' => $sku->lineItem->name ?: $sku->lineItem->title, // 是 物品名称
                'ItemTransactionId' => '', // 物品交易号[ebay订单必填]
                'OriginalPlatformOrderId' => $sku->lineItem->line_item_id, // 销售平台订单号[销售平台订单必填]
                'Price' => $sku->lineItem->price, // 是 价格（单价）
                'Quantities' => $sku->quantity, // 是 数量
                'Remark' => '', // 备注
                'SalePrice' => '', // 销售价格
                'Sku' => $sku->lineItem->sku, // 货号(SKU)
            ];

            //报关信息
            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'HwCode' => $logistics->code, // 海关编码
                'ItemCnName' => $logistics->cn_name, // 是 物品中文名称
                'ItemEnName' => $logistics->en_name, // 是 物品英文名称
                'ItemId' => $sku->line_item_id, // 物品ID（平台物品标示，平台必填）
                'ProducingArea' => 'CN', // 是 原产地（默认值：CN）
                'Quantities' => $sku->quantity, // 是 物品数量
                'RealPrice' => $sku->price, // 真实价格
                'Remark' => '', // 备注
                'Sku' => $sku->sku, // 产品编号(SKU)
                'UnitPrice' => $logistics->unit_price, // 是 报关单价
                'Weight' => $declareWeight, // 是 重量(kg)
                'BtId' => '', // 电池类型(带电池货物必填，非电池类可为空)
                'FbaNumber' => '', // 亚马逊FBA分箱号(邮递方式为fba时,该字段为[必填])
                'CCode' => 'USD', // 是 货币代码(默认为USD美元)
                'Model' => '', // 型号(FBA必填)
                'Brand' => '', // 品牌(FBA必填)
                'Purpose' => $logistics->use_to, // 用途(FBA必填，部分快递必填，例如香港DHL)
                'Material' => $logistics->material, // 材质(FBA必填，部分快递必填，例如香港DHL)
                'SaleLink' => '', // 销售链接
                'ReferenceID' => '', // eference ID
                'HomeDeclarePrice' => '', // 国内报关申报价值
                'HomeCCode' => '', // 国内报关币种
            ];

            $weight += $declareWeight * $sku->quantity;

            return true;
        });

        if (empty($declares) || count($declares) !== $package->items->count()) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        //发件人信息
        $shipper = [
            'SenderName' => $sender->receiver_name, // 发件人姓名
            'Country' => 'CN', // 发件人国家
            'Phone' => $sender->phone, // 电话号码
            'Email' => '', // 邮箱地址
            'Zip' => $sender->postcode, // 邮编
            'Province' => $sender->province, // 州/省
            'City' => $sender->city, // 城市
            'District' => '', // 区
            'Street' => '', // 街道
            'HouseNumber' => '', // 门牌
            'Address' => $sender->address, // 地址
        ];

        //预报重量明细集合
        $orderVolumeWeights[] = [
            'OrderId' => $package->package_sn, // 订单号
            'PackageCode' => '', // 分箱单号
            'Length' => '', // 长
            'Width' => '', // 宽
            'Height' => '', // 高
            'WeighingWeight' => $weight, // 称重重量
            'TraceId' => '', // 跟踪号
            'FbaShipmentId' => '', // FBA单号
            'Remark' => '', // 备注
        ];

        $orderList[] = [
            'ApiOrderId' => $logisticsApply->package_sn, // 是 第三方平台订单号(平台唯一)
            'PackType' => 3, // 是 包装类型（1：信封，2：文件，3：包裹）(默认包裹)
            // 'OnlineShippingType' => '', // 线上货运方式名称(走线上渠道时必填)
            // 'OnlineShopName' => '', // 线上店铺名(走线上渠道时必填)
            // 'Traceid' => '', // 跟踪号（线上发货等自带跟踪号的渠道需要上传，此字段不允许二次更新，系统以第一次上传为准，如需更新，请换单号重新下单）
            // 'TransitNo' => '', // 中转单号（若非要求的，请勿上传））
            'PtId' => $package->express_channel_code, // 是 货运方式(邮递方式简码)
            // 'IsCollectingMoney' => false, // 是否COD代收货款（true/false）
            // 'CollectingMoney' => 0, // 代收货款金额
            // 'CollectingMoneyCCode' => 'USD', // 代收货款币种（THB/USD/GBP三种）
            'SalesPlatformFlag' => 1, // 	是	int	2	销售平台标识[0=默认(不分);1=ebay;2=amazon();3=aliexpress(速卖通);4=wish](ebay,amazon,aliexpress,wish平台订单必填，其余可不填)
            'SyncPlatformFlag' => $this->syncPlatformFlag, // 	是	string	50	订单同步平台标识(一般指第三方平台标识，格式类似：scb.logistics.flyt，具体可询问飞特技术人员)
            // 'TraceId' => '', // 跟踪号
            // 'UAccount' => '', // 物流账号
            'MultiPackageQuantity' => 1, // 一票多件件数(FBA必填),默认1（不允许一票多件的渠道，全部为1）
            // 'IsSeparateDeclaration' => false, // 是否独立报关，true：是，false：否
            'InputWeight' => $weight, // 包裹重量
            // 'PlatformDeliveryTime' => '', // 发货时间
            // 'PlatformWarehouseCode' => '', // 亚马逊仓库编码（寄往FBA亚马逊仓库的时候必填）

            'ReceiverName' => $package->packageAddress->first_name.' '.$package->packageAddress->last_name, // 是收件人姓名
            'CiId' => $package->packageAddress->country_code, // 是 国家/地区简码
            'Address1' => $package->packageAddress->address1, // 是 地址1（地址1为空时，地址2必填）
            'Address2' => $package->packageAddress->address2, // 是 地址2
            // 'BuyerId' => '', // 买家id
            'County' => $package->packageAddress->province, // 是 州/省
            'City' => $package->packageAddress->city, // 是 城市
            'District' => '', // 区县
            'HouseNumber' => '', // 门牌号
            'CCode' => '', // 货币代码
            'Email' => $package->packageAddress->email, // 收件人Email
            'Phone' => $package->packageAddress->phone, // 收件人电话或手机
            'Remark' => '', // 	否	string	200	备注
            'Zip' => $package->packageAddress->zip, // 	是	string	10	邮编
            'TaxNumber' => $package->packageAddress->tax, // 收件人税号
            // 'SenderTaxNumber' => '', // 寄件人税号
            // 'VatNumber' => '', // VAT识别账号（英国专用）
            // 'IossNumber' => '', // IOSS号（欧盟国家专用）
            // 'PrepaidVat' => '', // 预缴增值税方式(取值范围：IOSS、no-IOSS、other)
            // 'ReceiverPassportIssueDate' => '', // 收件人护照签发日期
            // 'ReceiverPassportSerialNumber' => '', // 收件人护照序列号
            // 'ReceiverPassportNum' => '', // 收件人护照号码
            // 'ReceiverPassportIssueMechanism' => '', // 收件人护照签发机构
            // 'ReceiverIDCard' => '', // 收件人身份证号码
            // 'ReceiverBirthday' => '', // 出生年月日
            // 'ReceiverPayCardNumber' => '', // 存储付款卡号

            'OrderDetailList' => $orderItems, // 	是	OrderDetail[]		订单明细集合
            'HaikwanDetialList' => $declares, // 	是	HaikwanDetail[]		报关明细集合
            'Sender' => $shipper, // 发件人信息
            // 'OrderVolumeWeights' => $orderVolumeWeights, // OrderVolumeWeightModel[]		预报重量明细集合
        ];

        $data = $this->transform($orderList);

        Log::channel('feite')->info('feite-申请物流单号-申报信息-3', $data);

        $result = $this->client->request('POST', $this->url.self::ACTION_CREATE_ORDER, [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => $data,
        ]);

        $result = (array)json_decode($result->getBody()->getContents(), true);

        Log::channel('feite')->info('feite-申请物流单号-申报结果-4', [$result]);

        $response = new Response($result ?? []);

        //申报失败
        if (!$response->isSuccessful()) {
            $error = current($result['ErpFailOrders'])['Remark'] ?? '运单号申请失败，请稍后重试';
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }

        $resultData = current($result['ErpSuccessOrders']);
        $record = ['way_bill_number' => $resultData['OrderId'] ?? ''];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);

        //获取物流面单
        app()->make(ExpressCompaniesService::class)->getLabel($package);

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    public function getLabel($sn, LogisticsApplyModel $logisticsApply)
    {
        $this->setAccessToken();

        $data = [
            'OrderIdlst' => [$sn], // 是 运单号列表（物流单号，以F开头。同一次请求最多支持500个订单）
            'Format' => 0, // 0 (10*10), 1 (A4)，3（10*15，渠道面单），4（10*20，渠道面单）
            'IsPrintSkuInfo' => false, // Bool	是否打印配货信息
            'AddAdditionalPage' => false, // Bool	是否附加配货页
        ];

        Log::channel('feite')->info('feite-获取订单标签', $data);
        $result = $this->client->request('POST', $this->labelUrl.self::ACTION_GET_LABEL, [
            'headers' => ['Content-Type' => 'application/json', 'token' => $this->labelToken],
            'json'    => $data,
        ]);

        $result = (array)json_decode($result->getBody()->getContents());
        Log::channel('feite')->info('feite-获取订单标签结果', $result);

        $response = new Response($result ?? []);
        if (!$response->statusSuccessful()) {
            Log::channel('feite')->info('feite-获取订单标签失败', $result);

            throw new AccidentException('飞特物流商：' . $result['ErrMsg'], Code::OPERATE_FAIL);
        }

        $result = (array)$response->result();

        return $result['Label'] ?? '';
    }

    protected function setAccessToken(): string
    {
        $data = [
            'grant_type' => 'password',
            'username' => $this->labelUsername,
            'password' => strtoupper(md5($this->labelPassword)),
        ];

        $result = $this->client->request('POST', $this->labelUrl.self::ACTION_GET_ACCESS_TOKEN, [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => $data,
        ]);

        $result = (array)json_decode($result->getBody()->getContents(), true);
        Log::channel('feite')->info('feite-获取标签授权结果', $result);

        $accessToken = $result['access_token'] ?? '';
        if (empty($accessToken)) {
            throw new AccidentException('获取面单失败：' . ($result['error_description'] ?? '请检查飞特物流商标签授权信息'), Code::OPERATE_FAIL);
        }

        $this->labelToken = $accessToken;

        return $accessToken;
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
                        'name' => $value['posttypeName'],
                    ];
                })
                ->values()->all();
        }

        return false;
    }

    protected function getChannelCode(string $action)
    {
        try {
            $url = $this->url.$action;
            $result = $this->client->request('GET', $url);

            $result = json_decode($result->getBody()->getContents(), true);

            Log::channel('feite')->info('feite-channels-响应数据', $result);

            $response = new Response($result ?? []);

            if (!$response->statusSuccessful()) {
                return false;
            }

            return $result['datas'] ?? [];
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
