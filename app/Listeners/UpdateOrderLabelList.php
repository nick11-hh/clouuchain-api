<?php

namespace App\Listeners;

use App\Events\AfterUpdateLogisticsSn;
use App\Models\Order;
use App\Models\OrderDockingRecord;
use App\Services\ThirdPart\LTExp\LTExp;
use App\Services\ThirdPart\YiDa\YiDa;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateOrderLabelList implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public const TYPE_YI_DA = 'YDH';

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param AfterUpdateLogisticsSn $event
     * @return void
     */
    public function handle(AfterUpdateLogisticsSn $event)
    {
        info('进入LTExp获取');

        $ltExpList = collect($event->list)->filter(function ($item) {
            return !empty($item['company']) && ($item['company'] == 'LTEXP');
        })->toArray();

        $this->getLTExpLabel($ltExpList, $event->companyId);

        info('进入义达面单获取');
        //过滤非义达物流
        $orderList = collect($event->list)->filter(function ($item) {
            return !empty($item['company']) && ($item['company'] == self::TYPE_YI_DA);
        })->toArray();
        info('yida_data', $orderList);
        if (empty($orderList)) return;
        $orderIds = array_column($orderList, 'id');
        if (empty($orderIds)) return;
        //过滤订单
        $orders = Order::query()->whereIn('id', $orderIds)->get(['id', 'order_sn', 'company_id']);
        if ($orders->isEmpty()) return;
        info('yida2_data', $orders->toArray());

        DB::beginTransaction();
        try {
            //删除旧数据
            $orderIds = array_unique($orderIds);
            OrderDockingRecord::query()->whereIn('order_id', $orderIds)->delete();
            //获取面单
            $yiDa = new YiDa($event->companyId);
            $orders->each(function ($order) use ($yiDa) {
                $labelData = $yiDa->getLabel([['reference_no' => $order->order_sn]]);
                info('yida_label_data', $labelData);
                if (!empty($labelData)) {
                    /**@var \App\Models\Order $order */
                    $order->dockingRecords()->create(
                        [
                            'type' => OrderDockingRecord::TYPE_YI_DA,
                            'data' => $labelData,
                            'company_id' => $order->company_id
                        ]
                    );
                }
            });
        } catch (\Exception $ex) {
            DB::rollBack();
            info('获取面单失败' . $ex->getMessage());
            return;
        }
        DB::commit();
        return;
    }

    /**
     * @param array $list
     * @param int $companyId
     * @return void
     */
    protected function getLTExpLabel(array $list, int $companyId)
    {
        if (empty($list)) {
            return;
        }

        $orderIds = array_column($list, 'id');

        if (empty($orderIds)) {
            return;
        }
        //过滤订单
        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->get(['id', 'order_sn', 'company_id']);

        if ($orders->isEmpty()) {
            return;
        }

        info('data', $orders->toArray());

        DB::beginTransaction();
        try {
            //删除旧数据
            $orderIds = array_unique($orderIds);
            OrderDockingRecord::query()->whereIn('order_id', $orderIds)->delete();
            //获取面单
            $yiDa = new LTExp($companyId);
            $orders->each(function ($order) use ($yiDa) {
                $labelData = $yiDa->getLabel([['reference_no' => $order->order_sn]]);
                info('yida_label_data', $labelData);
                if (!empty($labelData)) {
                    /**@var \App\Models\Order $order */
                    $order->dockingRecords()->create(
                        [
                            'type' => OrderDockingRecord::TYPE_YI_DA,
                            'data' => $labelData,
                            'company_id' => $order->company_id
                        ]
                    );
                }
            });
        } catch (\Exception $ex) {
            DB::rollBack();
            info('获取面单失败' . $ex->getMessage());
            return;
        }
        DB::commit();
    }
}
