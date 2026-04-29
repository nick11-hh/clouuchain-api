<?php


namespace App\Services\ExpressCompanies\HuaLei;


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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Exceptions\AccidentException;

class HuaLei extends Logistics
{
    public $url;
    public $lable_url;
    public $username;
    public $password;
    public $customer_id;
    public $customer_userid;

    protected string $channel = 'hualei';

    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
        $this->auth();
    }

    public function place($package, $logisticsApply)
    {
        Log::channel('logistics')->info('hualei-申请物流单号-订单状态-1', [$package->status]);

        $sender = WarehouseAddress::first();
        if(!$sender){
            throw new AccidentException('发件人信息不存在', Code::OPERATE_FAIL);
        }

        $declares = [];
        $weight = 0;
        $package->items->each(function($sku) use ($package, $logisticsApply, &$declares, &$weight) {
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if(!$logistics) {
                return false;
            }

            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'invoice_amount' => $logistics->unit_price, // 申报价格(单价) ,必填
                'invoice_pcs' => $sku->quantity, // 申报数量,必填
                'invoice_title' => $logistics->en_name,   // 包裹申报名称(英文)必填
                'sku' => $logistics->cn_name,   // 包裹申报名称(中文)非必填
                'sku_code' => $sku->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
                'hs_code' => $logistics->code,#商品海关编码
                'invoice_weight' => sprintf("%.3f", $declareWeight),
                'invoice_currency' => 'USD', // 申报币种，默认USD，英国支持GBP/EUR，欧盟国家支持EUR
            ];

            $weight += $declareWeight * $sku->quantity;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $data = [
            'order_piece' => 1,
            'consignee_name' => $package->packageAddress->first_name.' '.$package->packageAddress->last_name,
            'consignee_address' => $package->packageAddress->address1.' '.$package->packageAddress->address2,
            'consignee_telephone' => $package->packageAddress->phone,
            'country' => $package->packageAddress->country_code,
            'consignee_state' => $package->packageAddress->province,
            'consignee_city' => $package->packageAddress->city,
            'consignee_postcode' => $package->packageAddress->zip,
            // 'consignee_taxno' => $package->packageAddress->tax,
            'customer_id' => $this->customer_id,
            'customer_userid' => $this->customer_userid,
            // 'order_customerinvoicecode' => $order->order_id,
            'order_customerinvoicecode' => $logisticsApply->package_sn,
            'product_id' => $package->express_channel_code,
            'cargo_type' => 'P',
            'orderInvoiceParam' => $declares,
            'weight' => sprintf("%.3f", $weight), //重量
        ];

        Log::channel('logistics')->info('hualei-申请物流单号-申报信息-3', $data);

        $res = $this->client->request('POST', $this->url.'/createOrderApi.htm', [
            'headers' => ['content-type' => 'application/x-www-form-urlencoded'],
            'form_params' => ['param' => urlencode(json_encode($data))]
        ]);

        $content = $res->getBody()->getContents();

        $content = str_replace("'", '"', $content);
        $response = json_decode($content, true);
        // $response = $this->requestHttp('POST', $this->url, ['content-type' => 'application/json;charset=utf-8'], ['param' => $data]);

        Log::channel('logistics')->info('hualei-申请物流单号-申报结果-4', [$response]);

        $record = [
            'track_type'      => 1,
            'remark'          => '',
            'sender_address'  => 0,
            'agent_number'    => '',
            'way_bill_number' => '',
            'tracking_number' => '',
        ];
        if($response['ack'] == 'false') {  // 申报失败
            $error = urldecode($response['message']);
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }

        $record['way_bill_number'] = $response['tracking_number'];
        $record['agent_number'] = $response['order_id'];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);

        $this->getLabel($record['agent_number'], $logisticsApply);

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    public function channels()
    {
        $uri = '/getProductList.htm';
        $res = $this->requestHttp('GET', $this->url.$uri, ['content-type' => 'application/json;charset=utf-8']);

        return collect($res)
            ->map(function ($value) {
                return [
                    'code' => $value['product_id'],
                    'name' => $value['product_shortname'],
                ];
            })
            ->values()->all();
    }

    public function getLabel(string $sn, $logisticsApply)
    {
        $uri = '/order/FastRpt/PDF_NEW.aspx?PrintType=lab10_10&order_id=' . $logisticsApply->agent_number;

        Log::channel('logistics')->info('hualei-获取面单', [$this->lable_url.$uri]);

        // 保存面单
        $fileName = '/'. $sn .'.pdf';
        $label = file_get_contents($this->lable_url.$uri);

        Storage::disk()->put('admin/' . $fileName, $label);

        $labelUrl = config('app.url') .'/storage/admin/' . $fileName;

        $logisticsApply->update([
            'label_url' => $labelUrl,
            'remark' => '',
                                                                         ]);
        return $labelUrl;

        /*$res = $this->requestHttp('GET', $this->lable_url.$uri, ['content-type' => 'application/json;charset=utf-8']);

        Log::channel('logistics')->info('hualei-获取面单结果', [$res]);

        if($res['ask'] === 'Success') {
            LogisticsApplyModel::where('order_id', $order->order_id)->update([
                'label_url' => $res['url'],
                'remark' => ''
            ]);

            return $res['url'];
        }

        LogisticsApplyModel::where('order_id', $order->order_id)->update([
            'remark' => '面单获取失败：'.$res['Error']['errMessage']
        ]);

        return false;*/
    }

    public function tracking(string $sn)
    {
        // TODO: Implement tracking() method.
    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        // TODO: Implement getDsConsignment() method.
    }

    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_HUA_LEI)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')->info('hualei-配置信息', $info);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->lable_url = $info['label_url'];
            $this->username = $info['username'];
            $this->password = $info['password'];
        } else {
            throw new AccidentException('hualei配置信息未设置', Code::OPERATE_FAIL);
        }
    }

    protected function auth()
    {
        $uri = '/selectAuth.htm';

        $res = $this->requestHttp('GET', $this->url.$uri, ['content-type' => 'application/json;charset=utf-8'], data: [
            'username' => $this->username,
            'password' => $this->password
        ]);

        if(!empty($res) && $res['ack']) {
            $this->customer_id = $res['customer_id'];
            $this->customer_userid = $res['customer_userid'];
        } else {
            throw new AccidentException('hualei-授权失败', Code::OPERATE_FAIL);
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
        } catch (GuzzleException $e) {}
    }
}
