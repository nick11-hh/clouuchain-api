<?php
/**
 * 云途物流
 */
namespace App\Services\ExpressCompanies\Quwuliu;

use App\Helper\Common;
use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\DeclareOrderModel;
use App\Models\ExpressOrderModel;
use App\Models\HandMovementModel;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\OrderBoxesModel;
use App\Models\OrderDeclarationModel;
use App\Models\OrderDockingRecordModel;
use App\Models\ThirdPartyTrackingLogModel;
use App\Services\ExpressCompanies\Logistics;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Exceptions\AccidentException;

class QuwuliuService extends Logistics
{
    protected string $url;

    protected const BASE_PATH = '/xms/services/order?wsdl';
    protected const ACTION_FORECAST_ORDER = '/api/WayBill/CreateOrder'; //运单申请
    protected const ACTION_OBTAIN_CHANNEL_CODE = '/services/getTransportWayList'; //获取运输方式
    protected const ACTION_GET_FACE = '/api/Label/Print'; //获取面单
    protected const ACTION_TRACK = '/api/Tracking/GetTrackInfo'; //轨迹查询

    protected Client $client;

    protected Order $order;

    protected DeclareOrderModel $declare;

    protected string $username;

    protected string $apiToken;

    protected string $channel = 'quwuliu';

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
            'logistics_company' => 'YunTuLogistics',
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
    public function getLabel(string $sn, $logisticsApply)
    {
        /**
         * 纸张尺寸，
         * “1”表示80.5mm × 90mm
         * “2”表示105mm × 210mm
         * “7”表示100mm × 150mm
         * “4”表示102mm × 76mm
         * “5”表示110mm × 85mm
         * “6”表示100mm × 100mm（默认）
         * “3”表示A4
         */
        $labelSpec = $order->channel->spec ?? '10*15';
        $labelType = match ($labelSpec) {
            '10*10' => 6,
            '10*15' => 7,
            'A4' => 3,
            default => 6,
        };

        /**
         * 选择打印样式
         * “1” 地址标签打印
         * “11” 报关单
         * “2” 地址标签+配货信息
         * “3” 地址标签+报关单（默认）
         * “13”地址标签+(含配货信息)
         * “12” 地址标签+(含配货信息)+报关单
         * “15” 地址标签+报关单+配货信息
         */
        $printSelect = 1;

        /**
         * 是否显示客户单号，
         * 0代表不显示（默认），
         * 1代表显示
         */
        $showCnoBarcode = 0;

        $url = "{$this->url}/xms/client/order_online!print.action";
        $url .= "?userToken={$this->apiToken}";
        $url .= "&trackingNo={$sn}";
        $url .= "&printSelect={$printSelect}";
        $url .= "&pageSizeCode={$labelType}";
        $url .= "&showCnoBarcode={$showCnoBarcode}";

        Log::channel('logistics')->info('获取面单url', [$url]);

        // $url = $this->url.'/xms/client/order_online!print.action?userToken='.$this->apiToken.'&trackingNo='.$sn.'&printSelect=3&pageSizeCode=6&showCnoBarcode=0';

        // 保存面单
        $fileName = '/'. $sn .'.pdf';
        $path = 'pdf/'. Carbon::now()->format('Ymd');
        $label = file_get_contents($url);

        Storage::disk()->put('admin/'.$path .$fileName, $label);

        $labelUrl = config('app.url') .'/storage/admin/'. $path . $fileName;

        $logisticsApply->update([
            'label_url' => $labelUrl
        ]);

        return $labelUrl;
    }

    /**
     * @return array|boolean
     */
    public function place($package, $logisticsApply)
    {
        Log::channel('logistics')->info('quwuliu-申请物流单号-订单状态-1', [$package->status]);

        $declares = $packageItems = [];

        $weight = $skuQuantityCount = 0;
        $package->items->each(function($sku) use ($package, $logisticsApply, &$declares, &$packageItems, &$weight, &$skuQuantityCount) {
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if(!$logistics) {
                return false;
            }

            //申报信息
            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'name'               => $logistics->en_name,   // 包裹申报名称(英文)必填
                'cnName'             => $logistics->cn_name,   // 中文申报名
                'pieces'             => $sku->quantity, // 申报数量,必填
                'netWeight'          => sprintf("%.3f", $declareWeight), // 申报重量(单重)，单位 kg
                'unitPrice'          => $logistics->unit_price,
            ];

            //包裹信息 取关联sku的重量尺寸
            $goodsSku = $sku->mapping->goodsSku ?? null;
            if ($goodsSku) {
                $skuWeight = ($goodsSku->weight * $sku->quantity) / 1000;
                $packageItems[] = [
                    'length' => $goodsSku->length,
                    'width' => $goodsSku->width,
                    'height' => $goodsSku->height,
                    'weight' => sprintf("%.3f", $skuWeight),
                ];
            }

            //货物件数
            $skuQuantityCount += $sku->quantity;

            $weight  += $declareWeight * $sku->quantity;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $data = [
            'userToken' => $this->apiToken,
            'createOrderRequest' => [
                'cargoCode' => 'W', // 货物类型。取值范围[W:包裹/D:文件]
                // 收件人信息
                'city' => $package->packageAddress->city, // 收件人所在城市
                'consigneeMobile' => $package->packageAddress->phone, // 收件人手机
                'consigneeName' => $package->packageAddress->first_name . ' ' . $package->packageAddress->last_name, // 收件人姓名
                'consigneePostcode' => $package->packageAddress->zip, // 收件人邮编
                'consigneeTelephone' => $package->packageAddress->phone, // 收件人电话
                // 申报明细列表
                'declareItems' => $declares,
                'destinationCountryCode' => strtoupper($package->packageAddress->country_code), // 目的国家二字简码
                'goodsCategory' => 'O', // 物品类别。取值范围[G:礼物/D:文件/S:商业样本/R:回货品/O:其他
                'insured' => 'N', // 购买保险（投保：Y，不投保：N）
                // 'orderNo' => $order->order_id, // 客户订单号,不能重复
                'orderNo' => $logisticsApply->package_sn, // 客户订单号,不能重复
                // 包裹明细列表
                'packageItems' => $packageItems,
                // 'pieces'        => $skuQuantityCount, // 运单包裹的件数，必须大于 0 的整数
                'pieces'        => 1, // 运单包裹的件数，必须大于 0 的整数
                'province' => $package->packageAddress->province, // 收件人所在省
                'street' => $package->packageAddress->address1.' '.$package->packageAddress->address2 ?? '',  // 收件人详细地址
                'transportWayCode'  => $package->express_channel_code, // 运输方式代码
                'weight' => sprintf("%.3f", $weight), // 货物预报重量（kg）取申报信息的重量总和
            ]
        ];

        Log::channel('logistics')->info('quwuliu-申请物流单号-申报信息-3', $data);

        $data = Common::arrayToXml($data);

        Log::channel('logistics')->info('quwuliu-申请物流单号-申报信息xml-3', [$data]);

        $res = $this->post(self::ACTION_FORECAST_ORDER, $data);

        Log::channel('logistics')->info('quwuliu-申请物流单号-申报结果-4', [$res]);

        if(empty($res) || $success = $res['Body']['createAndAuditOrderResponse']['return']['success'] == 'true') {  // 申报失败
            $error = $res['Body']['createAndAuditOrderResponse']['return']['error']['errorInfo'];
            return $this->applyLogisticFailure($package, $logisticsApply, $error);
        }


        //申报成功
        $record = [
            'remark'          => $success ? '' : $res['Body']['createAndAuditOrderResponse']['return']['error']['errorInfo'],
            'agent_number'    => $success ? $res['Body']['createAndAuditOrderResponse']['return']['id'] : '',
            'way_bill_number' => $success ? $res['Body']['createAndAuditOrderResponse']['return']['trackingNo'] : '',
            'tracking_number' => $success ? $res['Body']['createAndAuditOrderResponse']['return']['trackingNo'] : '',
        ];
        $this->applyLogisticSuccess($package, $logisticsApply, $record);
        $this->getLabel($record['way_bill_number'], $logisticsApply);

        // 同步到仓库
        $this->syncToWarehouse($package, $logisticsApply);

        return true;
    }

    /**
     * @param string $action
     * @param $data
     * @return array|false
     */
    protected function post(string $action, $data)
    {
        return $this->request($action, $data);
    }

    /**
     * @param string $method
     * @param $data
     * @return array|false
     */
    protected function request(string $method, $data)
    {
        $params = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.hop.service.ws.hlt.com/">
           <soapenv:Header/>
           <soapenv:Body>
              <ser:createAndAuditOrder>'.$data.'</ser:createAndAuditOrder>
           </soapenv:Body>
        </soapenv:Envelope>';

        $url = $this->url . self::BASE_PATH;
        try {
            $response = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );
        } catch (GuzzleException $exception) {
            info('趣物流请求失败', [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'msg'  => $exception->getMessage()
            ]);
            return ['msg' => $exception->getMessage()];
        }

        $result = $response->getBody();

        $xml = str_replace(['soap:', 'ns1:'], '', $result);
        $response = json_decode(json_encode(simplexml_load_string($xml)), true);
        // $res = Response::from($response)->result();

        Log::channel('logistics')->info('趣物流返回信息', [$response]);

        return $response;
    }

    /**
     * @return array|false
     */
    public function channels(): bool|array
    {
        $response = $this->getChannelCode(self::ACTION_OBTAIN_CHANNEL_CODE);

        if ($response) {
            return collect($response)
                ->map(function ($value) {
                    return [
                        'code' => $value['code'],
                        'name' => $value['name'],
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
        $params = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.hop.service.ws.hlt.com/">
           <soapenv:Header/>
           <soapenv:Body>
              <ser:getTransportWayList>
                 <userToken>'.$this->apiToken.'</userToken>
              </ser:getTransportWayList>
           </soapenv:Body>
        </soapenv:Envelope>';

        $url = $this->url . self::BASE_PATH;

        try {
            $response = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );
        } catch (GuzzleException $e) {
            info("{$this->channel} 获取物流渠道失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage());
        }

        $result = $response->getBody();

        $xml = str_replace(['soap:', 'ns1:'], '', $result);
        $response = json_decode(json_encode(simplexml_load_string($xml)), true);
        if ($response['Body']['getTransportWayListResponse']['return']['error']) {
            throw new AccidentException($response['Body']['getTransportWayListResponse']['return']['error']['errorInfo'] ?? 'quwuliu authorization failed. Please check user token', Code::OPERATE_FAIL);
        }

        if($response['Body']['getTransportWayListResponse']['return']['success']) {
            return $response['Body']['getTransportWayListResponse']['return']['transportWays'];
        }

        throw new AccidentException('趣物流获取渠道失败', Code::OPERATE_FAIL);

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
                'logistics_company' => 'YunTuLogistics',
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
        return $this->apiToken;
    }

    /**
     * @throws Exception
     */
    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_QUWULIU)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')->info('quwuliu-配置信息', $info);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->apiToken = $info['token'];
        } else {
            throw new AccidentException('尚未配置 趣物流 配置信息', Code::OPERATE_FAIL);
        }
    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        // TODO: Implement getDsConsignment() method.
    }
}
