<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\Admin;
use App\Models\OutboundOrder;
use App\Models\OutboundOrderItem;
use App\Models\PickingOrder;
use App\Services\Base\BarcodeService;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class PickingOrderService extends BaseService
{
    public $filterRules = [
        'picking_sn'       => ['like', 'picking_sn'],
        'warehouse_id'     => ['=', 'warehouse_id'],
        'created_at'       => ['between', ['begin_date', 'end_date']],
        'status'           => ['=', 'status'],
        'type'             => ['=', 'type'],
        'picking_staff_id' => ['=', 'picking_staff_id'],
    ];


    public function __construct()
    {
        $this->model = new PickingOrder();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['warehouse', 'admin'])->withCount('outboundOrders');
        $this->query->latest();
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['outboundOrders.items', 'admin', 'warehouse'])->findOrFail($id);
    }

    public function statusCount()
    {
        //首次加载列表页时会传状态值，导致其他状态的统计数量都为0 所以暂时先过滤状态查询条件
        unset($this->filters['status']);
        $this->setFilter();

        $statuses = $this->query->selectRaw('status, count(*) as count')->groupBy('status')->get();

        $statusCounts = $statuses->pluck('count', 'status');

        $allStatuses = array_keys($this->model::statusList()); //获取所有状态
        $statusCounts = collect($allStatuses)
            ->mapWithKeys(function ($status) use ($statuses) {
                // 如果状态存在于统计结果中，返回它的计数，否则返回0
                return [$status => $statuses->firstWhere('status', $status)?->count ?? 0];
            });

        $data = [];
        foreach ($statusCounts as $status => $count) {
            $data[] = ['status' => $status, 'count' => $count];
        }

        return $data;
    }

    public function scanData($params)
    {
         $picking = $this->model::query()->with(['outboundOrders.items', 'admin', 'warehouse'])
            ->where('picking_sn', $params['picking_sn'] ?? '')->first();
         if (empty($picking)) throw new AccidentException('拣货单不存在', Code::OPERATE_FAIL);
         return $picking;
    }


    /** 通过出库单创建拣货单
     * @param $params
     * @return mixed
     */
    public function createByOutboundIds($params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($params) {
            $query = OutboundOrder::query()->whereIn('id', $params['outbound_order_ids'])
                ->where('status', OutboundOrder::STATUS_WAIT_PICKING);
            $outboundOrderList = $query->get();
            if ($outboundOrderList->isEmpty()) throw new AccidentException('没有可以加入拣货单的出库单', Code::OPERATE_FAIL);

            $typeArr = $outboundOrderList->pluck('type')->unique()->toArray();
            if (count($typeArr) > 1) throw new AccidentException('出库订单属于不同的类型', Code::OPERATE_FAIL);

            $warehouseIdArr = $outboundOrderList->pluck('warehouse_id')->unique()->toArray();
            if (count($warehouseIdArr) > 1) throw new AccidentException('出库订单属于不同的仓库', Code::OPERATE_FAIL);

            $params['type'] = $typeArr[0];
            $params['warehouse_id'] = $warehouseIdArr[0];

            $data = $this->model::init($params);
            $picking = $this->model::query()->create($data);
            return $query->update([
                'picking_id' => $picking->id,
                'add_picking_time' => now(),
                'status' => OutboundOrder::STATUS_WAIT_SECOND_PICK
            ]);
        });
    }

    /** 取消拣货单
     * @param $id
     * @return bool
     */
    public function cancel($id)
    {
        $pickingOrder = $this->model::query()->findOrFail($id);
        $pickingOrder->delete();
        return OutboundOrder::query()->where('picking_id', $id)->update([
            'picking_id' => 0
        ]);
    }

    /**
     * @param $params
     * @return int
     */
    public function assignStaff($params)
    {
        validator($params, $this->assignStaffRules())->validate();
        Admin::query()->findOrFail($params['staff_id']);
        return PickingOrder::query()->whereIn('id', $params['picking_ids'])->update([
            'status' => PickingOrder::STATUS_PICKING,
            'picking_staff_id' => $params['staff_id'],
        ]);
    }

    public function printPicking($id)
    {
        $picking = $this->model::query()->with('outboundOrders.items.orderStocks.stockItem')->findOrFail($id);
        $pickingData = $this->generatePickingData($picking);
        PickingOrder::query()->where('id', $id)->update(['is_print' => 1]);
        return view('labels.picking-list')->with('pickingData', $pickingData);
    }

    /** 保存二次分拣数据
     * @param $id
     * @param $params
     * @return mixed
     */
    public function secondSort($id, $params)
    {
        validator($params, $this->secondSortRules())->validate();
        $picking = PickingOrder::query()->findOrFail($id);
        return DB::transaction(function () use ($picking, $params) {
            if ($picking->status == PickingOrder::STATUS_FINISH) throw new AccidentException('拣货单已完成拣货', Code::OPERATE_FAIL);
            $picking->status = PickingOrder::STATUS_PICKING;
            $picking->save();
            foreach ($params['orders'] as $data) {
                $outbound = OutboundOrder::query()->findOrFail($data['outbound_id']);
                $outbound->status = OutboundOrder::STATUS_WAIT_OUTBOUND;
                $outbound->picking_time = now();
                $outbound->packaged_time = now();
                $outbound->save();
                foreach ($data['items'] as $item) {
                    $outboundItem = OutboundOrderItem::query()->findOrFail($item['id']);
                    $outboundItem->picking_quantity = $item['sort_quantity'];
                    if ($outboundItem->quantity > $outboundItem->picking_quantity) {
                        throw new AccidentException('订单未完成拣货数量', Code::OPERATE_FAIL);
                    }
                    $outboundItem->save();
                }
            }
            return true;
        });
    }

    /**
     * @param $id
     * @return bool
     * @throws Exception
     */
    public function completePicking($id)
    {
        $picking = PickingOrder::query()->with('outboundOrders')->findOrFail($id);
        if (!$this->checkPickingCompleted($picking)) {
            throw new AccidentException('尚有订单未完成拣货', Code::OPERATE_FAIL);
        }
        $picking->complete_time = now();
        $picking->status = PickingOrder::STATUS_FINISH;
        return $picking->save();
    }

    /**
     * @param $picking
     * @return bool
     */
    public function checkPickingCompleted($picking)
    {
        $complete = true;
        $picking->outboundOrders->each(function ($outbound) use (&$complete) {
            if ($outbound->status <= OutboundOrder::STATUS_WAIT_SECOND_PICK) {
                $complete = false;
            }
        });
        return $complete;
    }

    /**
     * @param $picking
     * @return mixed
     */
    protected function generatePickingData($picking)
    {
        $picking->barcode = BarcodeService::getBarcodeBase64($picking->picking_sn);
        $picking->order_count = count($picking->outboundOrders);
        $skuData = [];
        $allQuantity = 0;
        $picking->outboundOrders->each(function ($order) use (&$skuData, &$allQuantity) {
            $order->items->each(function ($item) use (&$skuData, &$allQuantity) {
                $item->orderStocks->each(function ($orderStock) use (&$skuData, $item, &$allQuantity) {
                    $allQuantity += $orderStock->quantity;
                    $key = "{$item->sku}_{$orderStock->stockItem->location_code}";
                    if (isset($skuData[$key])) {
                        $skuData[$key]->quantity += $orderStock->quantity;
                    } else {
                        $skuData[$key] = $item;
                        $skuData[$key]->quantity = $orderStock->quantity;
                        $skuData[$key]->location_code = $orderStock->stockItem->location_code;
                        $skuData[$key]->barcode = BarcodeService::getBarcodeBase64($item->sku);
                        $skuData[$key]->owner = $item->stock->customer->custom_name ?? '本企业';
                    }
                });
            });
        });
        unset($picking->outboundOrders);
        $picking->sku_data = $skuData;
        $picking->sku_count = count($skuData);
        $picking->all_quantity = $allQuantity;
        return $picking;
    }

    protected function rules()
    {
        return [
            'outbound_order_ids' => 'required|array'
        ];
    }

    public function assignStaffRules()
    {
        return [
            'staff_id' => 'required|int',
            'picking_ids' => 'required|array'
        ];
    }

    public function secondSortRules()
    {
        return [
            'orders' => 'required|array',
            'orders.*.outbound_id' => 'required|int',
            'orders.*.items' => 'required|array',
            'orders.*.items.*.id' => 'required|int',
            'orders.*.skus.*.sort_quantity' => 'required|int',
        ];
    }

}


