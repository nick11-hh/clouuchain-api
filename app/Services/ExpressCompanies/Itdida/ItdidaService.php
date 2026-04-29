<?php
/**
 * 易抵达
 */
namespace App\Services\ExpressCompanies\Itdida;

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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Exceptions\AccidentException;

class ItdidaService extends Logistics
{
    protected string $url = 'http://wgyl.itdida.com/itdida-api';
    protected string $token;
    protected string $username;
    protected string $password;

    protected string $channel = 'itdida';

    public function __construct()
    {
        parent::__construct();
        $this->loadConfig();
        $this->login();
    }

    public function login()
    {
        $uri = '/login';

        $data = [
            'form_params' => [
                'username' => $this->username,
                'password' => $this->password
            ]
        ];

        $res = $this->requestHttp('POST', $this->url.$uri, ['Content-Type' => 'application/x-www-form-urlencoded'], $data);

        $this->token = $res['data'];
    }

    /**
     * @throws Exception
     */
    protected function loadConfig()
    {
        $config = CompanyDockingInfoModel::query()
            ->where('type', OrderDockingRecordModel::TYPE_IT_DIDA)
            ->first();

        if ($config) {
            $info = $config['info'];

            Log::channel('logistics')->info('配置信息', $info);
            info('配置信息', $info);

            if (isset($info['url'])) {
                $this->url = $info['url'];
            }

            $this->username = $info['username'];
            $this->password = $info['password'];
        } else {
            throw new AccidentException('尚未配置 易抵达物流 配置信息', Code::OPERATE_FAIL);
        }
    }

    public function place($package, $logisticsApply)
    {
        $uri = '/yundans';

        Log::channel('logistics')->info('itdida-申请物流单号-订单状态-1', [$package->status]);

        $declares = [];
        $weight = 0;
        $package->items->each(function($sku) use ($package, $logisticsApply, &$declares, &$weight) {
            $logistics = OrderDeclarationModel::where('order_item_id', $sku->lineItem->id)->first();
            if(!$logistics) {
                return false;
            }

            $declareWeight = $logistics->weight / 1000;
            $declares[] = [
                'shenBaoPinMing' => $logistics->en_name,#英文品名,
                'shenBaoDanJia' => $logistics->unit_price,
                'shenBaoShuLiang' => $sku->quantity,
                'zhongWenPinMing' => $logistics->cn_name,
                'unitNetWeight' => sprintf("%.3f", $declareWeight),
                'sku' => $sku->lineItem->mapping->goodsSku->sku_id ?? '', //订单关联的sku
            ];

            $weight  += $declareWeight * $sku->quantity;

            return true;
        });

        if (empty($declares)) {
            throw new AccidentException('海关申报信息不能为空', Code::OPERATE_FAIL);
        }

        $data = [
            'json' => [
                [
                    'baoGuoLeiXing'     => 1, // 包裹类型 0:文件 1:包裹 2:包裹袋
                    'fuShuiJin'         => 2, // 付税金 0:收件人 1:寄件人 2:第三方
                    'guoJia'            => $package->packageAddress->country_code ?? '', // 收件人国家，支持中文名，英文名或二字代码
                    // 'keHuDanHao'        => $order->order_id, // 客户单号，唯一，最大长度为30字符
                    'keHuDanHao'        => $logisticsApply->package_sn, // 客户单号，唯一，最大长度为30字符
                    'shouHuoQuDao'      => $package->express_channel_code, //'CA-DLRG-PH', // 收货渠道，需系统开通此渠道
                    'shouHuoShiZhong'   => sprintf("%.3f", $weight), // 收货实重
                    'shouJianRenDiZhi1' => $package->packageAddress->address1 ?? '', // 地址1
                    'shouJianRenDiZhi2' => $package->packageAddress->address2 ?? '', // 地址2
                    'zhouMing'          => $package->packageAddress->province ?? '', // 收件人省/洲
                    'shouJianRenXingMing' => $package->packageAddress->first_name.' '.$package->packageAddress->last_name, // 收件人姓名
                    'shouJianRenDianHua' => $package->packageAddress->phone, // 收件人电话
                    'shouJianRenYouBian' => $package->packageAddress->zip, // 收件人邮编
                    'shouJianRenChengShi' => $package->packageAddress->city, // 收件人城市
                    'shenBaoXinXiList' => $declares,
                ]
            ]
        ];

        $header = [
            'Authorization' => 'Bearer '.$this->token,
            'Content-Type' => 'application/json'
        ];

        Log::channel('logistics')->info('itdida-申请物流单号-申报信息-3', $data);

        $res = $this->requestHttp('POST', $this->url.$uri, $header, $data);

        Log::channel('logistics')->info('itdida-申请物流单号-申报结果-4', [$res]);

        foreach($res['data'] as $item) {
            if($item['code'] != 200) {
                $error = $item['message'];
                return $this->applyLogisticFailure($package, $logisticsApply, $error);
            }

            //暂时只考虑单包裹预报，固定订单编号
            $item['keHuDanHao'] = $package->package_sn;

            $record = [
                'order_id'        => $item['keHuDanHao'],
                'track_type'      => 3,
                'remark'          => $item['message'] ?? '',
                'way_bill_number' => $item['xiTongDanHao'] ?? '',
                'label_url'       => $item['labelUrl'] ?? '',
                'tracking_number' => $item['zhuanDanHao'] ?? ','
            ];
            if($item['label']) {
                $fileName = '/IT_'.Str::random(8).'.pdf';

                $label = base64_decode($item['label']);

                // 本地存储

                Storage::disk()->put('admin'.$fileName, $label);

                $labelUrl = config('app.url').'/storage/admin'.$fileName;

                $record['label_url'] = $labelUrl;
            }

            $this->applyLogisticSuccess($package, $logisticsApply, $record);

            // 同步到仓库
            $this->syncToWarehouse($package, $logisticsApply);

        }

        return true;
    }

    /**
     * 获取渠道列表
     * @return void
     */
    public function channels()
    {
        $uri = '/getReceivingChannels';

        $response = $this->requestHttp('GET', $this->url.$uri);

        if ($response) {
            return collect($response['data'])
                ->map(function ($value) {
                    return [
                        'code' => $value['logisticsModeCode'] ?: $value['channelName'],
                        'name' => $value['channelName'],
                    ];
                })
                ->values()->all();
        }

        return false;
    }

    public function getLabel(string $sn, $logisticsApply)
    {
        $uri = '/files';

        $packageSn = $logisticsApply->package_sn;

        $header = [
            'Authorization' => 'Bearer '.$this->token,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $data = [
            'form_params' => [
                // 'keHuDanHaoList' => (string)$order->order_id,
                'keHuDanHaoList' => $packageSn,
                'wenJianLeiXingList' => '1',
            ]
        ];

        Log::channel('logistics')->info('itdida-获取面单', [$this->url.$uri, $header, $data]);

        $res = $this->requestHttp('POST', $this->url.$uri, $header, $data);

        Log::channel('logistics')->info('itdida-获取面单结果', [$res]);

        $fileName = '/'.$sn.'.pdf';

        $labelUrl = '';

        $data = $res['data'] ?? [];
        foreach($data as $item) {
            $fileList = $item['fileList'] ?? [];
            foreach($fileList as $file) {
                if($file['code'] === 200) {
                    if ($file['url'] ?? '') {
                        $labelUrl = $file['url'];
                    } else {
                        $label = base64_decode($file['data']);

                        Storage::disk()->put('admin'.$fileName, $label);

                        $labelUrl = config('app.url') . '/storage/admin' . $fileName;
                    }

                    $logisticsApply->update([
                        'label_url' => $labelUrl
                    ]);
                } else {
                    $logisticsApply->update([
                        'remark' => '获取面单失败：' . $file['message'] ?? '',
                    ]);
                }
            }
        }

        return $labelUrl;
    }

    public function tracking(string $sn)
    {
        // TODO: Implement tracking() method.
    }

    public function getDsConsignment($way_bill_number, $logisticsApply)
    {
        // TODO: Implement getDsConsignment() method.
    }
}
