<?php

namespace App\Services\ExpressCompanies\JiePuSi;

use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\DeclareOrderModel;
use App\Models\ExpressOrderModel;
use App\Models\HandMovementModel;
use App\Models\LogisticsApplyModel;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\Order;
use App\Models\OrderBoxesModel;
use App\Models\OrderDockingRecordModel;
use App\Models\OrderDeclarationModel;
use App\Models\OrderItemMapping;
use App\Models\ThirdPartyTrackingLogModel;
use App\Models\WarehouseAddress;
use App\Services\ExpressCompanies\Logistics;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Exceptions\AccidentException;

/**
 * 捷普思
 */
class JiePuSiService extends Logistics
{
    protected string $url;

    protected const ACTION_FORECAST_ORDER = 'createorder'; //运单申请/创建订单
    protected const ACTION_TRACK = 'gettrack'; //轨迹查询/获取订单跟踪记录
    protected const ACTION_OBTAIN_CHANNEL_CODE = 'getcustomershippingmethod'; //获取运输方式/获取可用的运输方式
    protected const ACTION_GET_FACE = 'getnewlabel'; //打印标签

    protected Client $client;

    protected Order $order;

    protected DeclareOrderModel $declare;

    protected string $token;

    protected string $key;

    protected string $channel = 'jiepusi';

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }

    /**
     * 获取面单
     * @param string $sn 客户端的订单唯一标识
     * @param string $method API接口名称
     * @return false|mixed
     */
    public function getLabel(string $sn, $logisticsApply)
    {
        $param['listorder'][] = ['reference_no' => $sn];
        $param['configInfo'] = [
            'lable_file_type' => '2',#标签文件类型 1：PNG文件 2：PDF文件
            'lable_paper_type' => '1',#纸张类型 1：标签纸 2：A4纸
            'lable_content_type' => '1',#标签内容类型代码 1：标签 2：报关单 3：配货单 4：标签+报关单 5：标签+配货单 6：标签+报关单+配货单
            'additional_info' => [
                'lable_print_invoiceinfo' => 'Y',#标签上打印配货信息 (Y:打印 N:不打印) 默认 N:不打印
                'lable_print_buyerid' => 'N',#标签上是否打印买家ID (Y:打印 N:不打印) 默认 N:不打印
                'lable_print_datetime' => 'Y',#标签上是否打印日期 (Y:打印 N:不打印) 默认 Y:打印
                'customsdeclaration_print_actualweight' => 'N',#报关单上是否打印实际重量 (Y:打印 N:不打印) 默认 N:不打印
            ]
        ];
        $data = [
            'appToken' => $this->token,
            'appKey' => $this->key,
            'serviceMethod' => self::ACTION_GET_FACE,
            'paramsJson' => json_encode($param)
        ];

        Log::channel('logistics')
            ->info('获取面单', [
                'data' => $data,
                'url' => $this->url,
            ]);
        try {
            $result = $this->client->request(
                'POST',
                $this->url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'charset' => 'UTF-8',
                    ],
                    'form_params' => $data
                ]
            );

            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('获取面单响应数据', [
                    'response' => $response
                ]);
            if ($response['success'] == 1) {

                $logisticsApply->update([
                    'label_url' => $response['data'][0]['lable_file'] ?? '',
                    'remark' => '',
                ]);

                return $response['data'][0]['lable_file'] ?? '';
            }else{
                $logisticsApply->update([
                    'remark' => '获取面单失败：' . $response['cnmessage']
                ]);
            }

        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('获取面单失败', [
                    'exception' => $exception->getResponse()->getBody()
                ]);

            $logisticsApply->update([
                'remark' => '获取面单失败：' . $exception->getResponse()->getBody()
            ]);
        } catch (\Throwable $ex) {

            Log::channel('logistics')
                ->info('获取面单失败', [
                    'ex' => $ex->getMessage()
                ]);

            $logisticsApply->update([
                'remark' => '获取面单失败：' . $ex->getMessage()
            ]);
        }

        return null;
    }

    /**
     * @return array|boolean
     */
    public function place($package, $logisticsApply)
    {
        $data = $declares = [];
        $weight = 0;

        Log::channel('logistics')->info('jiepusi-申请物流单号-订单状态-1', [$package->status]);

        $sender = WarehouseAddress::first();
        if(!$sender){
            throw new AccidentException('发件人信息不存在', Code::OPERATE_FAIL);
        }

        $logistics = null;

        $package->items->each(function($sku) use ($package, $logisticsApply, &$declares, &$weight) {
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();

            if(!$logistics) {
                return false;
            }

            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'sku' => $sku->sku,#商品sku
                'invoice_enname' => $logistics->en_name,#英文品名
                'invoice_cnname' => $logistics->cn_name,
                'invoice_quantity' => $sku->quantity,
                'invoice_unitcharge' => $logistics->unit_price,
                'net_weight' => sprintf("%.3f", $declareWeight),
            ];

            $weight += $declareWeight * $sku->quantity;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        #单票创建
        $data = [
            // 'reference_no' => $order->order_id,#客户参考号
            'reference_no' => $logisticsApply->package_sn,#客户参考号
            'shipping_method' => $package->express_channel_code,//运输方式代码
            'order_weight' => sprintf("%.3f", $weight), //重量
            'mail_cargo_type' => '4',#包裹申报种类 1：Gif礼品 2：CommercialSample 3：Document 文件 4：Other 其他 默认4
            // 'order_info' => ' ',#订单备注

            #发件人
            'shipper' => [
                'shipper_name' => $sender->receiver_name,
                'shipper_countrycode' => 'CN',
                'shipper_province' => $sender->province,
                'shipper_city' => $sender->city,
                'shipper_district' => $sender->district,
                'shipper_street' => $sender->address,
                'shipper_mobile' => $sender->phone,
            ],
            #收件人
            'consignee' => [
                'consignee_name' => $package->packageAddress->name,
                'consignee_mobile' => $package->packageAddress->phone,
                'consignee_countrycode' => $package->packageAddress->country_code,
                'consignee_province' => $package->packageAddress->province,
                'consignee_city' => $package->packageAddress->city,
                'consignee_district' => '',
                'consignee_street' => $package->packageAddress->address1 . ' ' . $package->packageAddress->address2,
                'consignee_postcode' => $package->packageAddress->zip,
            ],
            'invoice' => $declares
        ];

        if(!empty($package->packageAddress->tax)) {
            $data['consignee']['consignee_tariff'] = $package->packageAddress->tax;
        }

        Log::channel('logistics')->info('jiepusi-申请物流单号-申报信息-3', $data);

        $res = $this->post(self::ACTION_FORECAST_ORDER, $data);

        Log::channel('logistics')->info('jiepusi-申请物流单号-申报结果-4', [$res]);

        if ($res) {

            #0代表失败；1代表成功；2代表重复订单
            if(($res['success'] ?? 0) == 1) {
                $record = [
                    'remark'          => '',
                    'way_bill_number' => $res['data']['shipping_method_no'],#服务商单号
                    'tracking_number' => $res['data']['channel_hawbcode'],#渠道转单号
                ];
                $this->applyLogisticSuccess($package, $logisticsApply, $record);
                // $this->getLabel($order->order_id, $order);
                $this->getLabel($logisticsApply->package_sn, $logisticsApply);

                // 同步到仓库
                $this->syncToWarehouse($package, $logisticsApply);

            }else{

                $error = $res['cnmessage'] ?? ($res['msg'] ?? '申请物流单号失败');
                return $this->applyLogisticFailure($package, $logisticsApply, $error);
            }
        }

        return true;
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
        $data = [
            'appToken' => $this->token,
            'appKey' => $this->key,
            'serviceMethod' => $method,
            'paramsJson' => json_encode($data, true)
        ];
        Log::channel('logistics')
            ->info('创建订单', [
                'data' => $data
            ]);

        try {
            $response = $this->client->request(
                'POST',
                $this->url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'charset' => 'UTF-8',
                    ],
                    'form_params' => $data
                ]
            );
        } catch (ClientException $exception) {
            Log::channel('logistics')->info('捷普思对接数据没有通过校验', [
                'msg' => $exception->getResponse()->getBody()
            ]);

            return ['msg' => $exception->getResponse()->getBody()];
        } catch (ServerException $exception) {
            Log::channel('logistics')->info('捷普思系统内部发生错误', [
                'msg' => $exception->getResponse()->getBody()
            ]);

            return ['msg' => $exception->getResponse()->getBody()];
        } catch (TransferException $exception) {

            Log::channel('logistics')->info('捷普思网络请求超时，请重新对接', [
                'msg' => $exception->getMessage()
            ]);

            return ['msg' => $exception->getMessage()];
        } catch (GuzzleException $exception) {
            Log::channel('logistics')->info('捷普思创建订单对接失败', [
                'msg' => $exception->getMessage()
            ]);

            return ['msg' => $exception->getMessage()];
        }

        $res = Response::from($response)->result();

        Log::channel('logistics')->info('创建订单返回数据', [$res]);

        return $res;
    }

    /**
     * @return array|false
     */
    public function channels()
    {

        $response = $this->getChannelCode(self::ACTION_OBTAIN_CHANNEL_CODE);

        if ($response) {
            return collect($response['data'])
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

    /**
     * @param string $action
     * @param array $data
     * @return array|false
     */
    protected function getChannelCode(string $action)
    {
        $data = [
            'appToken' => $this->token,
            'appKey' => $this->key,
            'serviceMethod' => $action,
            'paramsJson' => ''
        ];
        Log::channel('logistics')
            ->info('getChannelCode', [
                'data' => $data
            ]);

        try {
            $response = $this->client->request(
                'POST',
                $this->url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'charset' => 'UTF-8',
                    ],
                    'form_params' => $data
                ]
            );
        } catch (GuzzleException $e) {
            info("{$this->channel} 获取物流渠道失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage());
        }

        return Response::from($response)->result();
    }

    /**
     * 物流轨迹查询
     * @param string $sn 服务商单号
     * @return array|null
     */
    public function tracking(string $sn)
    {
        $data = [
            'appToken' => $this->token,
            'appKey' => $this->key,
            'serviceMethod' => self::ACTION_TRACK,
            'paramsJson' => json_encode(['tracking_number' => $sn])
        ];
        Log::channel('logistics')
            ->info('getChannelCode', [
                'data' => $data
            ]);

        try {
            $result = $this->client->request(
                'POST',
                $this->url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'charset' => 'UTF-8',
                    ],
                    'form_params' => $data
                ]
            );
            //处理响应结果
            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('物流轨迹查询响应数据', [
                    'response' => $response
                ]);

            if ($response['success'] == 1) {
                return $response['data'];
            }

        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('物流轨迹查询失败', [
                    'response' => $exception->getResponse()->getBody()
                ]);
        }

        return null;
    }

    /**
     * @throws Exception
     */
    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_JIEPUSI)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')
                ->info('============当前获取的 捷普思 对接配置============', [
                    'info' => $info
                ]);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->token = $info['token'];
            $this->key = $info['key'];
        } else {
            Log::channel('logistics')->info('公司尚未配置捷普思对接信息，对接失败');
            throw new AccidentException('尚未配置 捷普思 配置信息', Code::OPERATE_FAIL);
        }
    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        // TODO: Implement getDsConsignment() method.
    }
}
