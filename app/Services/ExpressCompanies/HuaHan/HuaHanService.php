<?php

namespace App\Services\ExpressCompanies\HuaHan;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\ExpressOrderModel;
use App\Models\HandMovementModel;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\OrderDeclarationModel;
use App\Models\OrderDockingRecordModel;
use App\Models\WarehouseAddress;
use App\Services\ExpressCompanies\Logistics;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

class HuaHanService extends Logistics
{
    protected $appToken;
    protected $appKey;
    protected $url;

    protected string $channel = 'huahan';

    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    public function place($package, $logisticsApply)
    {
        Log::channel('logistics')->info('huahan-申请物流单号-订单状态-1', [$package->status]);

        $sender         = WarehouseAddress::first();
        if (!$sender) {
            throw new AccidentException('发件人信息不存在', Code::OPERATE_FAIL);
        }


        $declares = [];
        $weight = 0;
        $package->items->each(function ($sku) use ($logisticsApply, &$declares, &$weight) {
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if (!$logistics) {
                return false;
            }

            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'invoice_enname'    => $logistics->en_name,   // 包裹申报名称(英文)必填
                'invoice_cnname'              => $logistics->cn_name,   // 包裹申报名称(中文)非必填
                'invoice_quantity'      => $sku->quantity, // 申报数量,必填
                'invoice_unitcharge'   => $logistics->unit_price, // 申报价格(单价) ,必填
                'invoice_currencycode' => 'USD', // 申报币种，默认USD，英国支持GBP/EUR，欧盟国家支持EUR
                'invoice_weight' => $declareWeight,
                'hs_code' => $logistics->code,#商品海关编码
                'sku' => $sku->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
                'material' => $logistics->material, // 申报材质
                'invoice_function' => $logistics->use_to, // 用途
            ];

            $weight += $declareWeight * $sku->quantity;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $data = [
            // 'reference_no'    => $order->order_id,
            'reference_no'    => $logisticsApply->package_sn,
            'shipping_method' => $package->express_channel_code,
            'country_code'    => $package->packageAddress->country_code,
            'order_weight'    => sprintf("%.3f", $weight), //重量 KG
            'order_pieces'    => 1, //外包装件数,默认1 小包默认值1即可
            'Consignee'       => [
                'consignee_province'  => $package->packageAddress->province,
                'consignee_city'      => $package->packageAddress->city,
                'consignee_street'    => $package->packageAddress->address1,
                'consignee_street2'   => $package->packageAddress->address2,
                'consignee_postcode'  => $package->packageAddress->zip,
                'consignee_name'      => $package->packageAddress->first_name . ' ' . $package->packageAddress->last_name,
                'consignee_telephone' => $package->packageAddress->phone,
                'consignee_taxno'     => $package->packageAddress->tax,
            ],
            'Shipper'         => [
                'shipper_countrycode' => 'CN',
                'shipper_province'    => $sender->province,
                'shipper_city'        => $sender->city,
                'shipper_street'      => $sender->address,
                'shipper_postcode'    => $sender->postcode,
                'shipper_name'        => $sender->receiver_name,
                'shipper_telephone'   => $sender->phone,
                'shipper_mobile'      => $sender->phone,
            ],
            'ItemArr' => $declares
        ];

        Log::channel('logistics')->info('huahan-申请物流单号-申报信息-3', $data);

        $dataJson = json_encode($data);

        $params = '<?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.example.org/Ec/">
                <SOAP-ENV:Body>
                    <ns1:callService>
                        <paramsJson>'.$dataJson.'</paramsJson>
                        <appToken>' . $this->appToken . '</appToken>
                        <appKey>' . $this->appKey . '</appKey>
                        <service>createOrder</service>
                    </ns1:callService>
                </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>';

        Log::channel('logistics')->info('huahan-申请物流单号-申报信息xml-4', [$params]);

        $res = $this->client->request('POST', $this->url, [
            'headers' => ['content-type' => 'application/xml'],
            'body'    => $params
        ]);

        $content    = $res->getBody()->getContents();
        Log::channel('logistics')->info('huahan-申请物流单号-申报结果xml-5', [$content]);

        $xml        = simplexml_load_string($content, null, LIBXML_NOCDATA);
        $jsonString = (string)$xml->xpath('//ns1:callServiceResponse/response')[0];

        $response   = json_decode($jsonString, true);
        Log::channel('logistics')->info('huahan-申请物流单号-申报结果array-6', [$response]);

        $record = [
            'track_type'      => 1,
            'remark'          => '',
            'sender_address'  => 0,
            'agent_number'    => '',
            'way_bill_number' => '',
            'tracking_number' => '',
        ];

        if($response['ask'] === 'Failure') { // 申报失败
            $error = $response['Error']['errMessage'] ?? $response['message'];
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }
        // 申报成功
        $record['way_bill_number'] = $response['order_code'];
        $record['agent_number'] = $response['shipping_method_no'];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);
        $this->getLabel($record['way_bill_number'], $logisticsApply);

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    public function channels()
    {
        $params = '<?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.example.org/Ec/">
                <SOAP-ENV:Body>
                    <ns1:callService>
                        <paramsJson></paramsJson>
                        <appToken>' . $this->appToken . '</appToken>
                        <appKey>' . $this->appKey . '</appKey>
                        <service>getShippingMethod</service>
                    </ns1:callService>
                </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>';

        Log::channel('logistics')->info('huahan获取渠道列表xml', [$params]);

        try {
            $res = $this->client->request('POST', $this->url, [
                'headers' => ['content-type' => 'application/xml'],
                'body'    => $params
            ]);
        } catch (GuzzleException $e) {
            info("{$this->channel} 获取物流渠道失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage());
        }

        $content    = $res->getBody()->getContents();
        Log::channel('logistics')->info('huahan获取渠道结果xml', [$content]);

        $xml        = simplexml_load_string($content, null, LIBXML_NOCDATA);
        $jsonString = (string)$xml->xpath('//ns1:callServiceResponse/response')[0];

        $response   = json_decode($jsonString, true);
        Log::channel('logistics')->info('huahan获取渠道结果array', [$response]);

        return collect($response['data'])
            ->map(function ($value) {

                return [
                    'code' => $value['code'],
                    'name' => $value['cn_name'],
                ];
            })
            ->values()->all();
    }

    public function getLabel(string $sn, $logisticsApply)
    {
        $labelSpec = $order->channel->spec ?? '10*15';
        $labelType = match ($labelSpec) {
            '10*10' => 1,
            '10*15' => 3,
            'A4' => 2,
            default => 1,
        };

        $data = [
            'reference_no' => $sn,
            'label_type' => $labelType, //1：10 * 10标签；2：A4纸；3：10 * 15标签
            'label_content_type' => 1, //1-标签；2-报关单；3-配货单；4-标签+报关单；5-标签+配货单；6-标签+报关单+配货单，默认为4
        ];

        $dataJson = json_encode($data);

        $params = '<?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.example.org/Ec/">
                <SOAP-ENV:Body>
                    <ns1:callService>
                        <paramsJson>'.$dataJson.'</paramsJson>
                        <appToken>' . $this->appToken . '</appToken>
                        <appKey>' . $this->appKey . '</appKey>
                        <service>getLabelUrl</service>
                    </ns1:callService>
                </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>';

        Log::channel('logistics')->info('huahan获取面单xml', [$params]);

        $res = $this->client->request('POST', $this->url, [
            'headers' => ['content-type' => 'application/xml'],
            'body'    => $params
        ]);

        $content    = $res->getBody()->getContents();
        Log::channel('logistics')->info('huahan获取面单结果xml', [$content]);

        $xml        = simplexml_load_string($content, null, LIBXML_NOCDATA);
        $jsonString = (string)$xml->xpath('//ns1:callServiceResponse/response')[0];

        $response   = json_decode($jsonString, true);
        Log::channel('logistics')->info('huahan获取面单结果array', [$response]);

        if($response['ask'] === 'Success') {
            $logisticsApply->update([
                'label_url' => $response['url'],
                'remark' => ''
            ]);

            return $response['url'];
        }

        $logisticsApply->update([
            'remark' => '面单获取失败：'.$response['Error']['errMessage']
        ]);

        return false;
    }

    public function tracking(string $sn)
    {

    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        // TODO: Implement getDsConsignment() method.
    }

    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_HH)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')->info('huahan配置信息', $info);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->appToken = $info['app_token'];
            $this->appKey   = $info['app_key'];
        } else {
            Log::channel('logistics')->info('huahan配置信息未设置');

            throw new AccidentException('huahan配置信息未设置', Code::OPERATE_FAIL);
        }
    }

    protected function requestHttp($method, $url, array $header = [], array $data = [])
    {
        $method = strtoupper($method);

        try {
            $option = ['headers' => $header,];
            if ($method === 'GET') {
                $option['query'] = $data;
            } else {
                $option = [...$option, ...$data];
            }

            $response = $this->client->request($method, $url, $option);

            $content = $response->getBody()->getContents();

            $content = str_replace("'", '"', $content);

            return json_decode($content, true);
        } catch (GuzzleException $e) {
        }
    }
}
