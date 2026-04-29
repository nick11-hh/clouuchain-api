<?php

namespace App\Services\ExpressCompanies\CNE;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\ExpressOrderModel;
use App\Models\HandMovementModel;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\OrderDeclarationModel;
use App\Models\OrderDockingRecordModel;
use App\Models\Package;
use App\Models\WarehouseAddress;
use App\Services\ExpressCompanies\Logistics;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

class CNEService extends Logistics
{
    public $url;
    public $token;
    public $icid;
    public $timeStamp;
    public $sign;

    protected string $channel = 'cne';

    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();

        $this->sign();
    }

    public function place($package, $logisticsApply): bool
    {
        $recList = [];

        Log::channel('logistics')->info('cne-申请物流单号-订单状态-1', [$package->status]);

        $sender = WarehouseAddress::first();
        if (!$sender) {
            throw new AccidentException('发件人信息不存在', Code::OPERATE_FAIL);
        }

        $declares = [];


        $weight = $skuQuantityCount = 0;
        $package->items->each(function ($sku) use ($logisticsApply, &$declares, &$weight) {
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();
            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'cxGoodsA'   => $logistics->en_name,   // 包裹申报名称(英文)必填
                'cxGoods'    => $logistics->cn_name,   // 包裹申报名称(中文)非必填
                'ixQuantity' => $sku->quantity, // 申报数量,必填
                'fxPrice'    => $logistics->unit_price, // 申报价格(单价) ,必填
                'cxMoney'    => 'USD', // 申报币种，默认USD，英国支持GBP/EUR，欧盟国家支持EUR
                'cxGCodeA'   => $sku->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
                'hsCode' => $logistics->code,#商品海关编码
            ];

            $weight += $declareWeight * $sku->quantity;

            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $labelSpec = $order->channel->spec ?? '10*15';
        $labelType = match ($labelSpec) {
            '10*10' => 'label10x10',
            '10*15' => 'label10x15',
            default => 'label10x10',
        };

        $recList[] = [
            'cEmsKind'     => $package->express_channel_code, // 运输方式代码
            'nItemType'    => 1,
            'cAddrFrom'    => 'OTHER',
            'iItem'        => 1,
            'cRNo'         => $logisticsApply->package_sn, // 客户订单号,不能重复
            'cDes'         => $package->packageAddress->country_code, // 收件人所在国家，填写国际通用标准 2 位简码，可通过国家查询服务查询
            'cReceiver'    => $package->packageAddress->first_name . ' ' . $package->packageAddress->last_name,
            'cRAddr'       => $package->packageAddress->address1 . ' ' . $package->packageAddress->address2,
            'cRCity'       => $package->packageAddress->city,
            'cRPostcode'   => $package->packageAddress->zip,      // 邮编
            'cRCountry'    => $package->packageAddress->country,
            'cRTaxCode'    => $package->packageAddress->tax,
            'fWeight'      => sprintf("%.3f", $weight),//包裹申报重量 KG
            'cSender'      => $sender->receiver_name, // 寄件人姓名
            'cSUnit' => $sender->name, //寄件人公司
            'cSAddr' => $sender->address, //寄件人地址
            'cSCity' => $sender->city,
            'cSProvince' => $sender->province,
            'cSPostcode' => $sender->postcode,
            'cSCountry' => 'CN',
            'cSPhone' => $sender->phone,
            'cSSms' => '',
            'cSEmail' => '',
            'GoodsList'    => $declares,
            'labelContent' => [
                'fileType'  => 'pdf',
                'labelType' => $labelType,
                'pickList'  => 0,
            ]
        ];

        $data = [
            'RequestName' => 'PreInputSet',
            'icID'        => $this->icid,
            'TimeStamp'   => $this->timeStamp,
            'MD5'         => $this->sign,
            'RecList'     => $recList
        ];
        Log::channel('logistics')->info('cne-申请物流单号-申报信息-3', $data);

        $response = $this->client->request(
            'POST',
            $this->url,
            [
                'headers' => [
                    'Accept'       => '*/*',
                    'Content-Type' => 'application/json;charset=UTF-8',
                    'charset'      => 'UTF-8',
                ],
                'json'    => $data
            ]
        );

        $res = json_decode($response->getBody(), true);

        Log::channel('logistics')->info('cne-申请物流单号-申报结果-4', [$res]);

        $record = [
            'track_type'      => 1,
            'remark'          => '',
            'sender_address'  => 0,
            'agent_number'    => '',
            'way_bill_number' => '',
            'tracking_number' => '',
        ];
        if ($res['OK'] < 1) {
            // 申报失败
            $error = $res['cMess'] ?? $res['ErrList'][0]['cMess'];
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }

        foreach ($res['ErrList'] as $item) {
            $record['way_bill_number'] = $item['cNo'];
            $record['label_url']       = $item['printUrl'];
        }
        $this->applyLogisticSuccess($package, $logisticsApply, $record);

        if ($record['way_bill_number']) {
            $this->getLabel($record['way_bill_number'], $logisticsApply);
        }

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    public function channels()
    {
        $data = [
            'RequestName' => 'EmsKindList',
            'icID'        => $this->icid,
            'TimeStamp'   => $this->timeStamp,
            'MD5'         => $this->sign
        ];

        Log::channel('logistics')->info('cne获取渠道列表', $data);

        try {
            $response = $this->client->request(
                'POST',
                $this->url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json;charset=UTF-8',
                    ],
                    'json'    => $data
                ]
            );
        } catch (GuzzleException $e) {
            info("{$this->channel} 获取物流渠道失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage());
        }

        $res = json_decode($response->getBody(), true);
        Log::channel('logistics')->info('cne获取渠道列结果', [$res]);

        if ($res['ReturnValue'] < 0) {
            throw new AccidentException($res['cMess'], Code::OPERATE_FAIL);
        }

        return collect($res['List'])
            ->map(function ($value) {
                return [
                    'code' => $value['oName'],
                    'name' => $value['cName'],
                ];
            })
            ->values()->all();
    }

    public function getLabel(string $sn, LogisticsApplyModel $logisticsApply)
    {
        $url = 'https://label.cne.com/CnePrint';

        //默认值：label10x10_0
        // label10x10_0：10x10标签不带配货单
        // label10x10_1：10x10标签带配货单
        // label10x15_0：10x15标签不带配货单
        // label10x15_1：10x15标签带配货单
        $labelSpec = $order->channel->spec ?? '10*15';
        $labelType = match ($labelSpec) {
            '10*10' => 'label10x10_0',
            '10*15' => 'label10x15_0',
            default => 'label10x10_0',
        };

        $data = [
            'icID'      => $this->icid,
            'cNos'      => $sn,
            'ptemp'     => $labelType,
            'signature' => md5($this->icid . $sn . $this->token),
        ];

        Log::channel('logistics')->info('cne获取面单', $data);

        $response = $this->client->request(
            'GET',
            $url,
            [
                'query' => $data
            ]
        );

        if ($response->getStatusCode() === 400) {
            return false;
        }

        $query = http_build_query($data);

        Log::channel('logistics')->info('cne获取面单结果', ['https://label.cne.com/CnePrint?' . $query]);

        $logisticsApply->update([
            'label_url' => 'https://label.cne.com/CnePrint?' . $query,
            'remark'    => ''
        ]);


        return 'https://label.cne.com/CnePrint?' . $query;
    }

    public function tracking(string $sn)
    {
        // TODO: Implement tracking() method.
    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        $url = 'https://api.cne.com/cgi-bin/EmsData.dll?DoApi';

        $data = [
            'RequestName' => 'GetTrackNumber',
            'icID'        => $this->icid,
            'TimeStamp'   => $this->timeStamp,
            'MD5'         => $this->sign,
            'cNo'         => $way_bill_number,
        ];
    }

    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_CNE)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')->info('cne配置信息', $info);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->token = $info['token'];
            $this->icid  = $info['icid'];
        } else {
            Log::channel('logistics')->info('cne配置信息未设置');

            throw new AccidentException('cne配置信息未设置', Code::OPERATE_FAIL);
        }
    }

    protected function sign()
    {
        $this->timeStamp = time() * 1000;

        $this->sign = md5($this->icid . $this->timeStamp . $this->token);
    }
}
