<?php

namespace App\Jobs;

use App\Lib\Code;
use App\Models\ExpressOrderModel;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Services\Admin\ExpressOrderService;
use App\Services\ExpressCompanies\ExpressCompanies;
use App\Services\Tracking\TrackingService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class LogisticsPlaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $order_ids;

    public array $params;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_ids, $params = [])
    {
        $this->order_ids = $order_ids;

        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        logger('--------------申请运单号---------------');
        logger('order_ids：', $this->order_ids);

        $orders = Order::with([
                                  'lineItems.mapping.goodsSku',
                                  'shippingAddress',
                                  'channel:id,code,name,spec,express_companies_id',
                                  'shop',
                                  'expressOrders:id,package_sn',
                              ])->whereIn('id', $this->order_ids)->get();

        $expressOrderService = new ExpressOrderService(new ExpressOrderModel());
        $orders->each(function ($order) use ($expressOrderService) {
            info('物流编码', [$order->logistics_provider_code]);
            $expressCompanies = new ExpressCompanies($order->logistics_provider_code);

            try {
                //创建包裹 不使用事务
                //订单收件人税号为空时，使用店铺设置的税号
                if (empty($order->shippingAddress->tax)) {
                    $tax = $expressCompanies->getShopTax($order);
                    if ($tax) {
                        $order->shippingAddress->tax = $tax;

                        //更新收件人税号
                        $order->shippingAddress->update(['tax' => $tax]);
                    }
                }
                //获取包裹号 取最新的一个包裹号
                // $packageSn = $order->expressOrders()->orderBy('id', 'desc')->get()->value('package_sn');
                $packageSn = $order->expressOrders->sortByDesc('id')->value('package_sn');

                //包裹号为空时 新建一个包裹
                if (empty($packageSn) && !isset($this->params['change_type'])) {
                    $packageSn = $expressOrderService->create($order)->package_sn ?? '';
                }

                //更换运单时，新增一个包裹
                if (isset($this->params['change_type']) && $this->params['change_type'] !== ExpressOrderModel::CHANGE_TYPE_FIRST) {
                    //重新生成新的包裹号
                    $packageSn = $expressOrderService->create($order, $this->params)->package_sn ?? '';
                }

                $order->package_sn = $packageSn;

                $logisticsApplyData = [
                    'order_id'   => $order->order_id,
                    'package_sn' => $order->package_sn,
                    'remark'     => '创建物流订单',
                ];
                $this->updateLogisticsApply($logisticsApplyData);

                try {
                    DB::beginTransaction();
                    $expressCompanies->place($order);
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();

                    throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
                }

            } catch (\Throwable $e) {
                $logisticsApplyData = [
                    'order_id'   => $order->order_id,
                    'package_sn' => $order->package_sn,
                    'remark'     => $e->getMessage(),
                ];

                $this->updateLogisticsApply($logisticsApplyData);


                unset($order->package_sn);//删除包裹号再更新
                $order->order_status = Order::STATUS_APPLY_NUM_FAILURE;
                $order->save();

                info('申请运单失败', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            }

            //注册物流轨迹
            $expressOrderService->registerTracking($order->package_sn ?? '');
        });
    }

    /**
     * 更新运单号申请信息反馈表
     */
    public function updateLogisticsApply($params)
    {
        $orderId = $params['order_id'];

        //根据包裹号查询物流订单ID
        $expressOrderId = ExpressOrderModel::query()->where('package_sn', $params['package_sn'])->value('id');

        $logisticsApply = LogisticsApplyModel::query()->where('order_id', $orderId)->first();
        if (empty($logisticsApply)) {
            $logisticsApply = new LogisticsApplyModel();
        }

        $logisticsApply->express_order_id = $expressOrderId;
        $logisticsApply->order_id = $orderId;
        $logisticsApply->package_sn = $params['package_sn'] ?? '';
        $logisticsApply->remark = $params['remark'] ?? '';
        return $logisticsApply->save();
    }

}
