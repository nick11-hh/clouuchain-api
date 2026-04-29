<?php

namespace App\Services\Admin;

use App\Jobs\LogisticsChannelsJob;
use App\Jobs\LogisticsPlaceJob;
use App\Jobs\LogisticsPlaceJobV2;
use App\Lib\Code;
use App\Models\CompanyDockingInfoModel;
use App\Models\CompanyExpressModel;
use App\Models\LogisticsChannelModel;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\Order;
use App\Models\OrderItemMapping;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\ExpressCompanies\ExpressCompanies;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use App\Exceptions\AccidentException;

class ExpressCompaniesService extends BaseService
{
    protected const SINGLE_MAX_APPLY_FOR_WAYBILL_QUANTITY = 100;

    public function __construct(CompanyExpressModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
    }

    public function index()
    {
        $this->formData['size'] = 1000;
        return parent::index();
    }

    /**
     * 添加物流
     * @return bool
     * @throws ValidationException
     */
    public function store(): bool
    {
        validator($this->formData, [
            'type' => 'required',
            'info' => 'required'
        ], [], [
            'type' => '物流类型',
            'info' => '授权信息'
        ])->validate();

        $info = CompanyDockingInfoModel::where('type', $this->formData['type'])->first();

        $data = [
            'type' => $this->formData['type'],
            'info' => $this->formData['info'],
        ];
        try {
            if($info) {
                CompanyDockingInfoModel::where('id', $info->id)->update($data);
            } else {
                CompanyDockingInfoModel::create($data);
            }

            $this->model::where('type', $this->formData['type'])->update(['enable' => CompanyExpressModel::ENABLE]);
            $expressCompanies = $this->model::where('type', $this->formData['type'])->first();

            if(!empty($expressCompanies)) {
                // 物流公司添加成功，分发任务到队列拉取物流渠道
                // dispatch(new LogisticsChannelsJob($express_companies));

                $expressCompaniesService = new ExpressCompanies($expressCompanies->code);
                $expressCompaniesService->channels($expressCompanies->id);

            }
        } catch (Exception $e) {
            logger('添加物流失败：'.$e->getMessage());
            throw $e;
        }

        return true;
    }

    /**
     * 更新启用状态
     * @param $params
     * @return int
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/10 18:39
     */
    public function updateEnable($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'enable' => 'required|int',
        ], [], [
            'ids' => '订单id',
            'enable' => '启用状态',
        ]
        )->validate();

        $updateData = ['enable' => $params['enable']];

        return $this->query->whereIn('id', $params['ids'])->update($updateData);
    }

    /**
     * 申请运单号
     * @return bool
     * @throws ValidationException
     * @throws \Throwable
     */
    public function place()
    {
        validator($this->formData, [
            'order_ids' => 'required|array',
        ], [], [
            'order_ids' => '订单id',
        ])->validate();

        if (ThirdPartyWarehouseConfig::getConfig()) {
            throw new AccidentException('您已启用第三方ERP进行履约，请到对方系统申请运单！', Code::OPERATE_FAIL);
        }

        $unpaidOrderStatus = [
            Order::STATUS_QUOTE_NO,
            Order::STATUS_QUOTE_ASK,
            Order::STATUS_QUOTED,
        ];
        throw_if(
            Order::whereIn('id', $this->formData['order_ids'])->whereIn('order_status', $unpaidOrderStatus)->first(),
            new AccidentException('操作失败，未支付的订单不能申请运单号', Code::OPERATE_FAIL)
        );

        throw_if(
            Order::whereIn('id', $this->formData['order_ids'])->where('logistics_provider', '')->first(),
            new AccidentException('操作失败，请填写完整物流渠道', Code::OPERATE_FAIL)
        );

        // 单次申请不得大于100条
        if(count($this->formData['order_ids']) > self::SINGLE_MAX_APPLY_FOR_WAYBILL_QUANTITY) {
            throw new AccidentException('操作失败，单次申请不得大于'. self::SINGLE_MAX_APPLY_FOR_WAYBILL_QUANTITY .'条', Code::OPERATE_FAIL);
        }

        $orders = Order::query()->with('packages')->whereIn('id', $this->formData['order_ids'])->get();
        $packageService = new PackageService();
        foreach ($orders as $order) {
            $packageIds = $order->packages->pluck('id')->toArray();
            $packageService->applyLogistics(['ids' => $packageIds]);
        }

        return true;
    }

    /**
     * 获取已启用物流渠道列表
     */
    public function channels(): Collection|array
    {
        return LogisticsChannelModel::with('expressCompanies:id,name,type,code')
            ->where('enable', LogisticsChannelModel::ENABLE)
            ->get();
    }

    /**
     * 根据物流商获取物流渠道
     */
    public function channelsByCompanes($express_companies_id)
    {
        $channelModel = new LogisticsChannelModel();
        $channelModel = $channelModel->where('express_companies_id', $express_companies_id);

        if(isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            $channelModel = $channelModel->where(function ($query) {
                $query->where('code', 'like', '%'. $this->formData['keyword'].'%')->orWhere('name', 'like', '%'.$this->formData['keyword'].'%');
            });
        }
        return $channelModel->orderBy('enable', 'desc')->paginate($this->formData['size'] ?? 50);
    }

    /**
     * 根据物流商获取已启用的物流渠道
     */
    public function enabledChannelsList($express_companies_id)
    {
        $channelModel = new LogisticsChannelModel();
        $channelModel = $channelModel->where(['express_companies_id' => $express_companies_id, 'enable' => LogisticsChannelModel::ENABLE]);

        return $channelModel->orderBy('enable', 'desc')->paginate($this->formData['size'] ?? 1000);
    }

    /**
     * 启用物流渠道
     * @return void
     */
    public function enableChannel()
    {
        validator($this->formData, [
            'channel_id' => 'required',
            'enable'  => 'required'
        ], [], [
            'channel_id' => '渠道id',
            'enable'  => '启用禁用状态'
        ])->validate();

        $data = [
            'enable' => $this->formData['enable'],
            'spec' => $this->formData['spec'] ?? '',
            'is_print_order_info' => $this->formData['is_print_order_info'] ?? 0,
        ];

        if(isset($this->formData['tail_course'])) {
            $data['tail_course'] = $this->formData['tail_course'];
        }

        return LogisticsChannelModel::where('id',$this->formData['channel_id'])->update($data);
    }

    /**
     * 获取物流面单
     * @param $id
     * @return bool
     * @throws Exception
     */
    public function getLabel($id): bool
    {
        $order = Order::with(['logisticsApply', 'channel'])->where('id', $id)->first();
        if (empty($order)) {
            throw new AccidentException('订单不存在', Code::OPERATE_FAIL);
        }

        if ($order->fulfillment_platform != ThirdPartyWarehouseConfig::PLATFORM_YUNLIANTIAO) {
            throw new AccidentException('当前履约平台不支持获取面单', Code::OPERATE_FAIL);
        }

        $logisticsProviderCode = $order->logistics_provider_code ?? '';//服务商代码
        $wayBillNumber = $order->logisticsApply->way_bill_number ?? '';//物流单号

        if(empty($logisticsProviderCode)) {
            throw new AccidentException('未绑定物流商', Code::OPERATE_FAIL);
        }

        if(empty($wayBillNumber)) {
            throw new AccidentException('运单号为空', Code::OPERATE_FAIL);
        }

        return (new ExpressCompanies($logisticsProviderCode))->getLabel($wayBillNumber, $order);
    }

    /**
     * 获取尾程单号
     * @return void
     */
    public function getDsConsignment($order_id)
    {
        $order = Order::with('logisticsApply')->where('id', $order_id)->first();

        if(empty($order->logistics_provider_code)) {
            throw new AccidentException('未绑定物流商', Code::OPERATE_FAIL);
        }
        $companies = new ExpressCompanies($order->logistics_provider_code);

        return $companies->getDsConsignment($order->logisticsApply->way_bill_number, $order, $order->order_id);
    }

    /**
     * 刷新物流渠道
     */
    public function refreshChannels()
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '物流商ID',
            ])->validate();

        $res = CompanyExpressModel::query()->whereIn('id', $this->formData['ids'])->select('code', 'id')->get();

        $res->each(function($item) {
            $expressCompanies = new ExpressCompanies($item->code);

            $expressCompanies->channels($item->id);
        });

        return true;
    }

    /**
     * 刷新物流渠道
     */
    public function getAuthorization()
    {
        validator($this->formData, [
            'type' => 'required'
        ], [], [
                      'type' => '物流类型',
                  ])->validate();

        $info = CompanyDockingInfoModel::where('type', $this->formData['type'])->first();

        return $info->info ?? '';
    }

}
