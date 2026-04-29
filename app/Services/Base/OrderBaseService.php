<?php

namespace App\Services\Base;

use App\Jobs\FulfillmentOrderJob;
use App\Lib\Code;
use App\Lib\Platform;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderShippingAddress;
use App\Models\Package;
use App\Models\ShopModel;
use App\Models\ShopOrderAbnormal;
use App\Models\ShopOrderLogs;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\AccidentException;
use Illuminate\Support\Facades\DB;

class OrderBaseService
{

    protected $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    /** 创建待报价订单
     * @param $params
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create($params, $logContent = '')
    {
        validator($params, [
            'order_id' => 'required|string|max:30',
            'shop_id' => 'required|integer',
            'currency' => 'required|string',
            'order_status' => 'sometimes|nullable|integer',
            'payment_info' => 'sometimes|nullable|array',
            'name' => 'sometimes|nullable|string|max:30',
            'remark' => 'sometimes|nullable|string|max:255',
            'current_total_price' => 'sometimes|nullable|numeric|min:0',
            'subtotal_price' => 'sometimes|nullable|numeric|min:0',
            'shipping_address' => 'required|array',
            'shipping_address.first_name' => 'required|string|max:50',
            'shipping_address.last_name' => 'sometimes|nullable|string|max:50',
            'shipping_address.country' => 'required|string|max:50',
            'shipping_address.country_code' => 'required|string|size:2',
            'shipping_address.province' => 'sometimes|nullable|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.address1' => 'required|string|max:255',
            'shipping_address.address2' => 'sometimes|nullable|string|max:255',
            'shipping_address.phone' => 'sometimes|nullable|string',
            'shipping_address.zip' => 'sometimes|nullable|string',
            'shipping_address.tax' => 'sometimes|nullable|string',
            'shipping_address.email' => 'sometimes|nullable|string',
            'line_items' => 'required|array',
            'line_items.*.name' => 'required|string|max:255',
            'line_items.*.title' => 'required|string|max:255',
            'line_items.*.variant_title' => 'sometimes|nullable|max:255',
            'line_items.*.product_id' => 'required|string|max:255',
            'line_items.*.variant_id' => 'required|string|max:255',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.price' => 'required|numeric|min:0',
            'line_items.*.sku' => 'sometimes|nullable|string|max:255',
            'line_items.*.product_url' => 'sometimes|nullable|string|max:255',
            'line_items.*.image_url' => 'sometimes|nullable|string|max:255',
        ])->validate();
        $shop = ShopModel::query()->findOrFail($params['shop_id']);
        $params['order_id'] =  $params['order_id'] . '_' . $shop->id;
        $orderExist = Order::query()->where('order_id', $params['order_id'])->where('shop_id', $shop->id)->first();
        if (!empty($orderExist)) throw new AccidentException('已存在相同订单号');
        return DB::transaction(function () use ($params, $shop, $logContent) {
            $params['created_at'] = now();
            $params['updated_at'] = now();
            $orderData = Order::init($params, $shop);
            $orderData['custom_order_id'] = generateOrderId($shop->customer_id ?? 0);
            $order = Order::query()->create($orderData);
            foreach ($params['line_items'] as $item) {
                $item['imgs'] = [$item['image_url']];
                $itemData = OrderLineItem::init($order->id, $item);
                $item = OrderLineItem::query()->create($itemData);
                //关联sku
                $goodsSku = $item->mapping->goodsSku;
                if (!empty($goodsSku)) {
                    $item->update(['goods_sku_id' => $goodsSku->id]);
                }
            }
            $addressData = OrderShippingAddress::init($params['shipping_address'], $order->id);
            OrderShippingAddress::query()->create($addressData);
            ShopOrderLogs::addLog([
                'order_id' => $order->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_CREATE_ORDER,
                'content' => $logContent ?: '创建订单成功',
                'operator_id' => 0,
            ]);
            return $order;
        });
    }


    /** 新增订单异常
     * @param $reason //异常原因  ShopOrderAbnormal::PLATFORM_ABNORMAL_LIST
     * @param $describe // 异常描述
     * @return Order|null
     */
    public function addOrderAbnormal($reason, $describe)
    {
        // 已报价但是未完成的订单才标记异常
        if (!in_array($this->order->order_status, Order::QUOTED_BUT_NOT_COMPLETE)) {
            return null;
        }

        if ($this->order->abnormal_status != Order::ORDER_STATUS_ABNORMAL) {
            $this->order->abnormal_status = Order::ORDER_STATUS_ABNORMAL;
            $this->order->save();
        }

        $sameAbnormal = ShopOrderAbnormal::query()->where('order_id', $this->order->id)
            ->where('abnormal_reason', $reason)
            ->where('deal_status', ShopOrderAbnormal::STATUS_WAIT_DEAL)
            ->first();

        if (empty($sameAbnormal)) {
            //写入订单异常记录
            ShopOrderAbnormal::query()->create([
                'order_id' => $this->order->id,
                'abnormal_reason' => $reason,
                'description' => $describe,
                'operator_id' => getAdminId(),
                'abnormal_time' => now()
            ]);

            //写入订单操作日志
            ShopOrderLogs::addLog([
                'order_id' => $this->order->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
                'content' => $describe,
                'operator_id' => 0
            ]);
        } else {
            //有相同原因的异常则更新时间
            $sameAbnormal->description = $describe;
            $sameAbnormal->operator_id = getAdminId();

            $sameAbnormal->save();
        }
        return $this->order;
    }

    /** 处理订单异常
     * @param $dealType // 处理类型
     * @return mixed
     * @throws Exception
     */
    public function dealOrderAbnormal($dealType)
    {
        if ($this->order->abnormal_status != Order::ORDER_STATUS_ABNORMAL) return null;
        // 该操作能够处理的异常原因
        $dealReasons = match ($dealType) {
            ShopOrderAbnormal::DEAL_TYPE_ORDER_CANCEL, ShopOrderAbnormal::DEAL_TYPE_MOVE_TO_QUOTING,
            ShopOrderAbnormal::DEAL_TYPE_IGNORE_ABNORMAL => [  // 取消报价、移入报价中和忽略异常操作解决所有异常
                ShopOrderAbnormal::ABNORMAL_ORDER_CANCEL,
                ShopOrderAbnormal::ABNORMAL_GOODS_ADD,
                ShopOrderAbnormal::ABNORMAL_GOODS_REMOVE,
                ShopOrderAbnormal::ABNORMAL_GOODS_UPDATE,
                ShopOrderAbnormal::ABNORMAL_ADDRESS_UPDATE,
                ShopOrderAbnormal::ABNORMAL_APPLY_LOGISTICS_FAILURE,
                ShopOrderAbnormal::ABNORMAL_DELIVERY_FAILURE,
                ShopOrderAbnormal::ABNORMAL_OTHER_FULFILMENT,
            ],
            ShopOrderAbnormal::DEAL_TYPE_REAPPLY_LOGISTICS => [  // 重新申请运单号
                ShopOrderAbnormal::ABNORMAL_APPLY_LOGISTICS_FAILURE,
            ],
            ShopOrderAbnormal::DEAL_TYPE_DELIVERY_SUCCESS => [
                ShopOrderAbnormal::ABNORMAL_DELIVERY_FAILURE
            ],
            default => throw new AccidentException('不支持该处理异常的方式', Code::OPERATE_FAIL),
        };

        if ($dealType === ShopOrderAbnormal::DEAL_TYPE_IGNORE_ABNORMAL) { // 忽略异常
            $fulfillmentAbnormal = ShopOrderAbnormal::query()->where('order_id', $this->order->id)->where('deal_status', ShopOrderAbnormal::STATUS_WAIT_DEAL)
                ->where('abnormal_reason', ShopOrderAbnormal::ABNORMAL_OTHER_FULFILMENT)->first();
            if (!empty($fulfillmentAbnormal)) {  // 如果忽略了订单被其他供应商履约的异常，代表这一单强制发货，添加标识，后面不再记录该异常
                $this->order->confirm_shipment_at = now();
                $this->order->save();
            }
        }
        // 把异常处理状态设为已处理
        ShopOrderAbnormal::query()->where('order_id', $this->order->id)
            ->where('deal_status', ShopOrderAbnormal::STATUS_WAIT_DEAL)
            ->whereIn('abnormal_reason', $dealReasons)
            ->update([
                'deal_status' => ShopOrderAbnormal::STATUS_PROCESSED,
                'deal_type' => $dealType,
                'deal_time' => now()
            ]);

        // 没有异常了把订单状态设为正常
        if (!$this->order->hasAbnormal()) {
            $this->order->abnormal_status = Order::ORDER_STATUS_NORMAL;
            $this->order->save();
        }
        return $this->order;
    }

    /** 订单是否异常并拦截操作
     * @param $order
     * @return bool
     */
    public static function abnormalIntercept($order): bool
    {
        if ($order->abnormal_status === Order::ORDER_STATUS_ABNORMAL) {
            $abnormalList = ShopOrderAbnormal::query()->where('order_id', $order->id)->select('abnormal_reason')->get();
            foreach ($abnormalList as $abnormal) {
                if ($abnormal != ShopOrderAbnormal::ABNORMAL_DELIVERY_FAILURE) return true;
            }
        }
        return false;
    }



    public function syncPackageLogisticApplyStatus()
    {
        $count = 0;
        $statusCount = [];
        $order = Order::query()->with('packages')->find($this->order->id);
        $order->packages->each(function ($package) use (&$count, &$statusCount) {
            $statusCount[$package->logistics_status] = isset($statusCount[$package->logistics_status]) ? $statusCount[$package->logistics_status] + 1 : 1;
            $count ++;
        });
        $logisticsStatus = null;
        foreach ($statusCount as $status => $value) {
            if ($value == $count) {
                $logisticsStatus = $status;
            }
        }
        if (empty($logisticsStatus)) {
            if (!empty($statusCount[Package::LOGISTICS_APPLY_FAILURE])) {
                $logisticsStatus = Package::LOGISTICS_APPLY_FAILURE;
            }
        }
        if (!empty($logisticsStatus)) {
            $this->order->logistics_status = $logisticsStatus;
            $this->order->save();
        }
    }

    public function syncPackageStockStatus()
    {
        $count = 0;
        $statusCount = [];
        $order = Order::query()->with('packages')->find($this->order->id);
        $order->packages->each(function ($package) use (&$count, &$statusCount) {
            $statusCount[$package->stock_status] = isset($statusCount[$package->stock_status]) ? $statusCount[$package->stock_status] + 1 : 1;
            $count ++;
        });
        $stockStatus = null;
        foreach ($statusCount as $status => $value) {
            if ($value == $count) {
                $stockStatus = $status;
            }
        }
        if (empty($stockStatus)) {
            if (!empty($statusCount[Package::STOCK_LACK])) {
                $stockStatus = Package::STOCK_LACK;
            }
        }
        if (!empty($stockStatus)) {
            $this->order->stock_status = $stockStatus;
            $this->order->save();
        }
    }

}
