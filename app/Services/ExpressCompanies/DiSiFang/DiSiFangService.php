<?php

namespace App\Services\ExpressCompanies\DiSiFang;

use App\Jobs\GetDsConsignmentJob;
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
use App\Models\ThirdPartyTrackingLogModel;
use App\Models\WarehouseAddress;
use App\Models\DiSiFangApiInfo;
use App\Services\ExpressCompanies\Logistics;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Exceptions\AccidentException;

/**
 * 递四方
 */
class DiSiFangService extends Logistics
{
    protected string $url;

    protected const ACTION_FORECAST_ORDER = 'ds.xms.order.create'; //运单申请/创建直发委托单
    protected const ACTION_GET = 'ds.xms.order.get'; //查询直发委托单
    protected const ACTION_CANCEL = 'ds.xms.order.cancel'; //取消直发委托单
    protected const ACTION_OBTAIN_CHANNEL_CODE = 'ds.xms.logistics_product.getlist'; //获取运输方式/物流产品查询
    protected const ACTION_GET_FACE = 'ds.xms.label.get'; //获取面单
    protected const ACTION_GET_FACE_LIST = 'ds.xms.label.getlist'; //批量获取标签
    protected const ACTION_TRACK = 'tr.order.tracking.get'; //轨迹查询

    protected Client $client;

    protected Order $order;

    protected DeclareOrderModel $declare;

    protected string $appKey;

    protected string $appSecret;

    protected string $channel = 'disifang';

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->loadConfig();
    }


    /**
     * 取消直发委托单
     * @param string $requestNo 请求单号（只支持4PX单号和客户单号请求）
     * @param string $orderId 订单号
     * @param string $cancelReason 取消原因
     * @return false|mixed
     */
    public function cancel(string $requestNo, $orderId, $cancelReason='test')
    {

        $method = self::ACTION_CANCEL;
        $timestamp = Carbon::now()->valueOf();
        $params = json_encode(['request_no' => $requestNo, 'cancel_reason' => $cancelReason]);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?method=' . $method . '&app_key=' . $this->appKey . '&v=1.0&timestamp=' . $timestamp . '&format=json&sign=' . $sign . '&language=cn';

        Log::channel('logistics')
            ->info('取消直发委托单', [
                'timestamp' => $timestamp,
                'params' => $params,
                'url' => $url,
                'sign' => $sign,
            ]);

        try {
            $result = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );

            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('取消直发委托单响应数据', [
                    'response' => $response
                ]);
            if ($response['result'] == '1') {

                LogisticsApplyModel::where('order_id', $orderId)->update([
                    'remark' => '取消直发委托单成功'
                ]);

            }

            if ($response['result'] == '0') {

                $error = '';
                if(isset($response['errors'])){

                    foreach ($response['errors'] as $key => $value) {
                        $error .= 'ErrorCode[' . $value['error_code'] . ']，' . $value['error_msg'] . ';';
                    }
                }

                if($error){

                    LogisticsApplyModel::where('order_id', $orderId)->update([
                        'remark' => '取消直发委托单失败：' . $error
                    ]);

                }
            }

        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('取消直发委托单失败', [
                    'exception' => $exception->getResponse()->getBody()
                ]);
            LogisticsApplyModel::where('order_id', $orderId)->update([
                'remark' => '取消直发委托单失败：' . $exception->getMessage()
            ]);
        } catch (\Throwable $ex) {

            Log::channel('logistics')
                ->info('取消直发委托单失败', [
                    'ex' => $ex->getMessage()
                ]);

            LogisticsApplyModel::where('order_id', $orderId)->update([
                'remark' => '取消直发委托单失败：' . $ex->getMessage()
            ]);
        }

        return true;
    }

    /**
     * 批量获取面单
     * @param array $requestNo 请求单号（支持4PX单号、客户单号和面单号）
     * @param string $logisticsProductCode 物流产品代码
     * @param string $labelSize 面单尺寸 (如需要10*15的面单和10*10的面单一起打印，大小需要传 label_100x150  才可以)
     * @return false|mixed
     */
    public function getLabelList(array $requestNo, $logisticsProductCode, $labelSize='label_100x150')
    {

        $method = self::ACTION_GET_FACE_LIST;
        $timestamp = Carbon::now()->valueOf();
        $params = json_encode([
            'request_no' => $requestNo,
            'logistics_product_code' => $logisticsProductCode,
            'label_size' => $labelSize
        ]);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?method=' . $method . '&app_key=' . $this->appKey . '&v=1.0&timestamp=' . $timestamp . '&format=json&sign=' . $sign . '&language=cn';

        Log::channel('logistics')
            ->info('批量获取面单', [
                'timestamp' => $timestamp,
                'params' => $params,
                'url' => $url,
                'sign' => $sign,
            ]);

        try {
            $result = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );

            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('批量获取面单响应数据', [
                    'response' => $response
                ]);
            if ($response['result'] == '1') {

                return $response['data']['logistics_label'];
            }

            if ($response['result'] == '0') {

                // $error = '';
                // if(isset($response['errors'])){

                //     foreach ($response['errors'] as $key => $value) {
                //         $error .= 'ErrorCode[' . $value['error_code'] . ']，' . $value['error_msg'] . ';';
                //     }
                // }

                // if($error){

                //     LogisticsApplyModel::where('order_id', $order->order_id)->update([
                //         'remark' => '获取面单失败：' . $error
                //     ]);

                // }
            }
        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('批量获取面单失败', [
                    'exception' => $exception->getResponse()->getBody()
                ]);

        } catch (\Throwable $ex) {

            Log::channel('logistics')
                ->info('批量获取面单失败', [
                    'ex' => $ex->getMessage()
                ]);

        }

        return null;
    }

    /**
     * 获取面单
     * @param string $requestNo 请求单号（支持4PX单号、客户单号和面单号）
     * @param LogisticsApplyModel $logisticsApply
     * @return false|mixed
     */
    public function getLabel(string $requestNo, LogisticsApplyModel $logisticsApply)
    {
        $method = self::ACTION_GET_FACE;
        $timestamp = Carbon::now()->valueOf();
        $params = json_encode(['request_no' => $requestNo]);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?method=' . $method . '&app_key=' . $this->appKey . '&v=1.0&timestamp=' . $timestamp . '&format=json&sign=' . $sign . '&language=cn';

        Log::channel('logistics')
            ->info('获取面单', [
                'timestamp' => $timestamp,
                'params' => $params,
                'url' => $url,
                'sign' => $sign,
            ]);

        try {
            $result = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );

            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('获取面单响应数据', [
                    'response' => $response
                ]);
            if ($response['result'] == '1') {

                $logisticsApply->update([
                    'label_url' => $response['data']['label_url_info']['logistics_label'],
                    'remark' => ''
                ]);

                DiSiFangApiInfo::where('logistics_apply_id', $logisticsApply->id)->update([
                    'label_barcode2' => $response['data']['label_barcode'],
                    'logistics_label' => $response['data']['label_url_info']['logistics_label'],
                    'custom_label' => $response['data']['label_url_info']['custom_label'] ?? '',
                    'package_label' => $response['data']['label_url_info']['package_label'] ?? '',
                    'invoice_label' => $response['data']['label_url_info']['invoice_label'] ?? '',
                    'child_label_barcode' => '',
                ]);

                return $response['data']['label_url_info']['logistics_label'];
            }

            if ($response['result'] == '0') {

                $error = '';
                if(isset($response['errors'])){

                    foreach ($response['errors'] as $key => $value) {
                        $error .= 'ErrorCode[' . $value['error_code'] . ']，' . $value['error_msg'] . ';';
                    }
                }

                if($error){

                    $logisticsApply->update([
                        'remark' => '获取面单失败：' . $error
                    ]);

                }
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
     * 查询直发委托单
     * @param $requestNo // 请求单号（只支持4PX单号和客户单号请求）
     * @param $logisticsApply
     * @return false|mixed
     */
    public function getDsConsignment($requestNo, $logisticsApply)
    {
        $method = self::ACTION_GET;
        $timestamp = Carbon::now()->valueOf();
        $params = json_encode(['request_no' => $requestNo]);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?method=' . $method . '&app_key=' . $this->appKey . '&v=1.0&timestamp=' . $timestamp . '&format=json&sign=' . $sign . '&language=cn';

        Log::channel('logistics')
            ->info('查询直发委托单', [
                'timestamp' => $timestamp,
                'params' => $params,
                'url' => $url,
                'sign' => $sign,
            ]);

        try {
            $result = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );

            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('查询直发委托单响应数据', [
                    'response' => $response
                ]);
            if ($response['result'] == '1') {

                $data = json_decode($response['data'], true);

                #先保存返回数据
                DiSiFangApiInfo::updateOrCreate(['logistics_apply_id' => $logisticsApply->id],
                    [
                        'ds_consignment_no' => $data[0]['consignment_info']['ds_consignment_no'] ?? null,
                        '4px_tracking_no' => $data[0]['consignment_info']['4px_tracking_no'] ?? null,
                        'label_barcode' => $data[0]['consignment_info']['4px_tracking_no'] ?? null,
                        'ref_no' => $data[0]['consignment_info']['ref_no'] ?? null,
                        'logistics_channel_no' => $data[0]['consignment_info']['logistics_channel_no'] ?? null,
                        'get_no_mode' => $data[0]['consignment_info']['get_no_mode'] ?? null,
                        'get_no_exmsg' => $data[0]['consignment_info']['get_no_exmsg'] ?? null,
                        'logistics_product_code' => $data[0]['consignment_info']['logistics_product_code'] ?? null,
                        'logistics_product_name' => $data[0]['consignment_info']['logistics_product_name'] ?? null,
                        'consignment_status' => $data[0]['consignment_info']['consignment_status'] ?? null,
                        'insure_status' => $data[0]['consignment_info']['insure_status'] ?? null,
                        'insure_type' => $data[0]['consignment_info']['insure_type'] ?? null,
                        'has_check_oda' => $data[0]['consignment_info']['has_check_oda'] ?? null,
                        'oda_result_sign' => $data[0]['consignment_info']['oda_result_sign'] ?? null,
                        'is_hold_sign' => $data[0]['consignment_info']['is_hold_sign'] ?? null,
                        'consignment_create_date' => $data[0]['consignment_info']['consignment_create_date'] ?? null,
                        '4px_inbound_date' => $data[0]['consignment_info']['4px_inbound_date'] ?? null,
                        '4px_outbound_date' => $data[0]['consignment_info']['4px_outbound_date'] ?? null,

                        'confirm_parcel_qty' => $data[0]['parcel_confirm_info']['confirm_parcel_qty'] ?? null,
                        'confirm_parcel_weight' => $data[0]['parcel_confirm_info']['confirm_parcel_weight'] ?? null,
                        'confirm_parcel_volume_weight' => $data[0]['parcel_confirm_info']['confirm_parcel_volume_weight'] ?? null,
                        'confirm_parcel_charge_weight' => $data[0]['parcel_confirm_info']['confirm_parcel_charge_weight'] ?? null,
                        'confirm_weight' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['confirm_weight'] ?? null,
                        'confirm_volume_weight' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['confirm_volume_weight'] ?? null,
                        'confirm_length' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['confirm_length'] ?? null,
                        'confirm_width' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['confirm_width'] ?? null,
                        'confirm_high' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['confirm_high'] ?? null,
                        'confirm_charge_weight' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['confirm_charge_weight'] ?? null,
                        'confirm_include_battery' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['confirm_include_battery'] ?? null,
                        'confirm_battery_type' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['confirm_battery_type'] ?? null,
                        'parcel_total_value_confirm' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['parcel_total_value_confirm'] ?? null,
                        'currency_code' => $data[0]['parcel_confirm_info']['parcel_list_confirm_info'][0]['currency_code'] ?? null,
                    ]
                );

                #判断get_no_mode
                $get_no_mode = $data[0]['consignment_info']['get_no_mode'] ?? '';
                if($get_no_mode == 'U'){

                    $this->getLabel($requestNo, $logisticsApply);

                }elseif ($get_no_mode == 'C') {

                    $logistics_channel_no = $data[0]['consignment_info']['logistics_channel_no'] ?? '';
                    if($logistics_channel_no != ''){

                        $this->getLabel($requestNo,  $logisticsApply);
                    }

                    $get_no_exmsg = $data[0]['consignment_info']['get_no_exmsg'] ?? '';
                    if(!empty($get_no_exmsg)){
                        $logisticsApply->update([
                            'remark' => $data[0]['consignment_info']['get_no_exmsg']
                        ]);
                    }

                    if(empty($logistics_channel_no) && empty($get_no_exmsg)){

                        dispatch(new GetDsConsignmentJob($requestNo, $logisticsApply))->delay(now()->addMinutes(1));
                    }
                }
            }

            if ($response['result'] == '0') {

                $error = '';
                if(isset($response['errors'])){

                    foreach ($response['errors'] as $key => $value) {
                        $error .= 'ErrorCode[' . $value['error_code'] . ']，' . $value['error_msg'] . ';';
                    }
                }

                if($error){
                    $logisticsApply->update([
                            'remark' => $error
                        ]);
                }
            }
        } catch (GuzzleException $exception) {
            Log::channel('logistics')
                ->info('查询直发委托单失败', [
                    'exception' => $exception->getResponse()->getBody()
                ]);
            $logisticsApply->update([
                    'remark' => '查询直发委托单失败：' . $exception->getResponse()->getBody()
                ]);

        } catch (\Throwable $ex) {

            Log::channel('logistics')
                ->info('查询直发委托单失败', [
                    'ex' => $ex->getMessage()
                ]);

            $logisticsApply->update([
                    'remark' => '查询直发委托单失败：' . $ex->getMessage()
                ]);
        }

        return true;
    }


    /**
     * @return array|boolean
     */
    public function place($package, $logisticsApply)
    {

        Log::channel('logistics')->info('disifang-申请物流单号-订单状态-1', [$package->status]);

        $sender = WarehouseAddress::first();
        if(!$sender){
            throw new AccidentException('发件人信息不存在', Code::OPERATE_FAIL);
        }

        $invoiceValue = 0;
        $weight = 0;
        $declares = [];
        $package->items->each(function($sku) use ($logisticsApply, &$declares,  &$invoiceValue, &$weight) {

            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();
            if (empty($logistics)) return true;

            $declareWeight = $logistics->weight;
            $declares[] = [
                'declare_product_code_qty' => $sku->quantity,//申报数量
                'declare_unit_price_export' => $logistics->unit_price ?? 1,//出口申报单价（目前建议进出口申报单价传一样）
                'currency_export' => 'USD',//出口货币类型，货币类型保持一致
                'declare_unit_price_import' => $logistics->unit_price ?? 1,//进口申报单价（目前建议进出口申报单价传一样
                'currency_import' => 'USD',//进口货币类型,货币类型保持一致
                'brand_export' => '',//出口品牌，如无可以填空""
                'brand_import' => '',//进口品牌，如无可以填空""
                'declare_product_name_en' => $logistics->en_name ?? 'test',//英文申报品名，不能包含中文，及特殊符号
                'declare_product_name_cn' => $logistics->cn_name,
                'unit_net_weight' => $logistics->weight, //单件商品净重（默认以g为单位）
            ];

            $invoiceValue += $sku->quantity * $logistics->unit_price;

            $weight  += $declareWeight * $sku->quantity;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $parcelList[] = [
            'weight' => $weight,#,#预报重量（g）
            'parcel_value' => $invoiceValue,#包裹申报价值（最多4位小数）
            // 'currency' => $order->currency,#包裹申报价值币别（按照ISO标准三字码；支持的币种，根据物流产品+收件人国家配置；币种需和进出口国申报币种一致）
            'currency' => 'USD',#包裹申报价值币别（按照ISO标准三字码；支持的币种，根据物流产品+收件人国家配置；币种需和进出口国申报币种一致）
            'include_battery' => 'N',#是否含电池（Y/N）
            'declare_product_info' => $declares,
        ];

        $data = [
            // 'ref_no' => $order->order_id, //参考号（客户自有系统的单号，如客户单号）
            'ref_no' => $logisticsApply->package_sn, //参考号（客户自有系统的单号，如客户单号）
            'business_type' => 'BDS',//业务类型(4PX内部调度所需，如需对接传值将说明，默认值：BDS。)
            'duty_type' => 'P',//税费费用承担方式(可选值：U、P); U：DDU由收件人支付关税; P：DDP 由寄件方支付关税 （如果物流产品只提供其中一种，则以4PX提供的为准）
            // 'cargo_type' => '',//货物类型（1：礼品;2：文件;3：商品货样;5：其它；默认值：5）
            #'vat_no' => '',//VAT税号(数字或字母)；欧盟国家(含英国)使用的增值税号；
            #'ioss_no' => '',//IOSS号码
            #'buyer_id' => '',//买家ID(数字或字母)
            #'sales_platform' => '',//销售平台
            #'trade_id' => '',//交易号ID(数字或字母)
            #'seller_id' => '',//卖家ID(数字或字母)
            #'is_commercial_invoice' => '',//能否提供商业发票（Y/N） Y：能提供商业发票(则系统不会生成形式发票)；N：不能提供商业发票(则系统会生成形式发票)； 默认为N；DHL产品必填，如产品代码A1/A5；
            'parcel_qty' => 1,//包裹件数（一个订单有多少件包裹，就填写多少件数，请如实填写包裹件数，否则DHL无法返回准确的子单号数和子单号标签；DHL产品必填，如产品代码A1/A5；）
            #'freight_charges' => '',//运费(客户填写自己估算的运输费用；支持的币种，根据物流产品+收件人国家配置)
            #'currency_freight' => '',//运费币种(按照ISO标准三字码；支持的币种，根据物流产品+收件人国家配置)
            #'declare_insurance' => '',//申报保险费（是否必填，根据物流产品+目的国配置；根据欧盟IOSS政策，货值/运费/保险费可单独申报）支持小数点后2位
            #'currency_declare_insurance' => '',//申报保险费币种（按照ISO标准，币种需和进出口国申报币种一致）

            #物流服务信息
            'logistics_service_info' => [
                'logistics_product_code' => $package->express_channel_code,//物流产品代码
                // 'customs_service' => '',//单独报关（Y：单独报关；N：不单独报关） 默认值：N
                // 'signature_service' => '',//签名服务（Y/N)；默认值：N
            ],

            #退件信息
            'return_info' => [
                'is_return_on_domestic' => 'N',//境内/国内异常处理策略(Y：退件--实际是否支持退件，以及退件策略、费用，参考报价表；N：销毁；U：其他--等待客户指令) 默认值：N；
                'is_return_on_oversea' => 'N',//境外/国外异常处理策略(Y：退件--实际是否支持退件，以及退件策略、费用，参考报价表；N：销毁；U：其他--等待客户指令) 默认值：N；

                #境内/国内退件接收地址信息（退件地址非必填；若填写，则姓名/电话/邮编/国家/城市/详细地址均需填写）
                // 'domestic_return_addr' => [
                //     'first_name' => '',//名/姓名
                //     'last_name' => '',//姓
                //     'company' => '',//公司名
                //     'phone' => '',//电话（必填）
                //     'phone2' => '',//电话2
                //     'email' => '',//邮箱
                //     'post_code' => '',//邮编
                //     'country' => '',//国家（国际二字码 标准ISO 3166-2 )
                //     'state' => '',//州/省
                //     'city' => '',//城市
                //     'district' => '',//区、县
                //     'street' => '',//街道/详细地址
                //     'house_number' => '',//门牌号
                // ],

                #境外/国外退件接收地址信息（退件地址非必填；若填写，则姓名/电话/邮编/国家/城市/详细地址均需填写）
                // 'oversea_return_addr' => [
                //     'first_name' => '',//名/姓名
                //     'last_name' => '',//姓
                //     'company' => '',//公司名
                //     'phone' => '',//电话（必填）
                //     'phone2' => '',//电话2
                //     'email' => '',//邮箱
                //     'post_code' => '',//邮编
                //     'country' => '',//国家（国际二字码 标准ISO 3166-2 )
                //     'state' => '',//州/省
                //     'city' => '',//城市
                //     'district' => '',//区、县
                //     'street' => '',//街道/详细地址
                //     'house_number' => '',//门牌号
                // ]
            ],

            #包裹列表
            'parcel_list' => $parcelList,

            'is_insure' => 'N',//是否投保(Y、N)
            #保险信息（投保时必须填写）
            // 'insurance_info' => [
            //     'insure_type' => '',
            //     'insure_value' => '',
            //     'currency' => '',
            //     'insure_person' => '',
            //     'certificate_type' => '',
            //     'certificate_no' => '',
            // ],

            #发件人信息
            'sender' => [
                'first_name' => $sender->receiver_name,
                'last_name' => '',
                'company' => $sender->warehouse_name,
                'phone' => $sender->phone,
                'phone2' => '',
                'email' => '',
                'post_code' => $sender->postcode,
                'country' => 'CN',
                'state' => $sender->province,
                'city' => $sender->city,
                'district' => $sender->district,
                'street' => $sender->address,
                'house_number' => '',
            ],

            #收件人信息
            'recipient_info' => [
                'first_name' => $package->packageAddress->name,
                'last_name' => '',
                'company' => $package->packageAddress->company,
                'phone' => $package->packageAddress->phone,
                'phone2' => '',
                'email' => '',
                'post_code' => $package->packageAddress->zip,
                'country' => $package->packageAddress->country_code,
                'state' => $package->packageAddress->province_code,
                'city' => $package->packageAddress->city,
                'district' => '',
                'street' => $package->packageAddress->address1 .' '. $package->packageAddress->address2,
                'house_number' => $package->packageAddress->address2,
            ],

            #货物到仓方式信息
            'deliver_type_info' => [
                'deliver_type' => '1',//到仓方式（1:上门揽收；2:快递到仓；3:自送到仓；5:自送门店）
            ]
        ];

        Log::channel('logistics')->info('disifang-申请物流单号-申报信息-3', $data);

        $res = $this->post(self::ACTION_FORECAST_ORDER, $data);

        Log::channel('logistics')->info('disifang-申请物流单号-申报结果-4', [$res]);

        if ($res) {

            $result = (integer)$res['result'];
            if(!$result) {// 申报失败
                $error = '';
                if(isset($res['errors'])){

                    foreach ($res['errors'] as $key => $value) {
                        $error .= 'ErrorCode[' . $value['error_code'] . ']，' . $value['error_msg'] . ';';
                    }
                }
                return $this->applyLogisticFailure($package, $logisticsApply, $error);
            }else{

                $record = [
                    'agent_number'    => $res['data']['ds_consignment_no'] ?? '',#直发委托单号
                    'remark'          => '',
                    'way_bill_number' => $res['data']['4px_tracking_no'] ?? '',#4PX单号（可以查看预报到出库发货前的头程轨迹）
                    'tracking_number' => $res['data']['logistics_channel_no'] ?? '',#服务商单号，一般提供给平台或客户的，查询尾程轨迹（如果结果返回为空字符，表示暂时没有物流渠道号码，请稍后主动调用查询直发委托单接口查询）
                ];
                $this->applyLogisticSuccess($package, $logisticsApply, $record);

                #查询直发委托单
                // $this->getDsConsignment($record['way_bill_number'], $order, $order->order_id);
                $this->getDsConsignment($record['way_bill_number'], $logisticsApply);

                // 同步到仓库
                $this->syncToWarehouse($package, $logisticsApply);
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

        $timestamp = Carbon::now()->valueOf();
        $params = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?method=' . $method . '&app_key=' . $this->appKey . '&v=1.0&timestamp=' . $timestamp . '&format=json&sign=' . $sign . '&language=cn';
        // dd($url);
        Log::channel('logistics')
            ->info('创建直发委托单', [
                'timestamp' => $timestamp,
                'params' => $params,
                'url' => $url,
                'sign' => $sign,
            ]);

        try {
            $response = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => '*/*',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );
        } catch (GuzzleException $exception) {
            Log::channel('logistics')->info('递四方对接创建直发委托单失败', [
                'msg' => $exception->getMessage()
            ]);

            return ['errors' => [['error_msg' => $exception->getMessage(), 'error_code' => 0]], 'result' => 0];
        }

        $res = Response::from($response)->result();

        Log::channel('logistics')->info('递四方对接创建直发委托单返回数据', [$res]);

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
                        'code' => $value['logistics_product_code'],
                        'name' => $value['logistics_product_name_cn'],
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

        $timestamp = Carbon::now()->valueOf();
        $params = json_encode(['transport_mode' => 1]);
        $sign = $this->getSign($action, $timestamp, $params);
        $url = $this->url . '?method=' . $action . '&app_key=' . $this->appKey . '&v=1.0&timestamp=' . $timestamp . '&format=json&sign=' . $sign . '&language=cn&access_token=';

        Log::channel('logistics')
            ->info('getChannelCode', [
                'timestamp' => $timestamp,
                'params' => $params,
                'sign' => $sign,
                'url' => $url
            ]);

        try {
            $response = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8',
                    ],
                    'body' => $params
                ]
            );
        } catch (GuzzleException $e) {
            info("{$this->channel} 获取物流渠道失败", ['msg' => $e->getMessage()]);

            throw new AccidentException($e->getMessage());
        }

        return Response::from($response)->result();
    }


    /**
     * @param string $sn 物流单号
     * @return array|null
     */
    public function tracking(string $sn)
    {
        $method = self::ACTION_TRACK;
        $timestamp = Carbon::now()->valueOf();
        $params = json_encode(['deliveryOrderNo' => $sn]);
        $sign = $this->getSign($method, $timestamp, $params);
        $url = $this->url . '?method=' . $method . '&app_key=' . $this->appKey . '&v=1.0&timestamp=' . $timestamp . '&format=json&sign=' . $sign . '&language=cn&access_token=';

        Log::channel('logistics')
            ->info('物流轨迹查询', [
                'timestamp' => $timestamp,
                'params' => $params,
                'sign' => $sign,
                'url' => $url
            ]);

        try {
            $result = $this->client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'charset' => 'UTF-8'
                    ],
                    'body' => $params
                ]
            );
            //处理响应结果
            $response = Response::from($result)->result();

            Log::channel('logistics')
                ->info('物流轨迹查询响应数据', [
                    'response' => $response
                ]);

            if ($response['result'] == '1') {
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
     * 生成签名
     */
    protected function getSign($method, $timestamp, $jsonParams)
    {

        $arr = [
            'app_key' => $this->appKey,
            'format' => 'json',
            'method' => $method,
            'timestamp' => $timestamp,
            'v' => '1.0'
        ];

        ksort($arr);#将这个数组以参数名的字典升序排序

        $str = '';
        foreach ($arr as $key => $value) {
            $str .= $key . $value;
        }

        $sign = md5($str . $jsonParams . $this->appSecret);

        return $sign;
    }

    /**
     * @throws Exception
     */
    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_4PX)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')
                ->info('============当前获取的 递四方 对接配置============', [
                    'info' => $info
                ]);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->appKey = $info['appKey'];
            $this->appSecret = $info['appSecret'];
        } else {
            throw new AccidentException('尚未配置 递四方 配置信息', Code::OPERATE_FAIL);
        }
    }
}
