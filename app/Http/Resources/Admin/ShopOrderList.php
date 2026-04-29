<?php

namespace App\Http\Resources\Admin;

use App\Lib\Platform;
use App\Models\ExpressOrderModel;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopOrderList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        //物流订单信息
//        if ($this->expressOrders) {
//            //包裹号 目前只考虑一对一
//            $this->package_sn = $this->expressOrders->sortByDesc('id')->value('package_sn');

            //包裹物流信息
//            $this->logisticsApplyList = $this->expressOrders->where('status', ExpressOrderModel::STATUS_SUCCESS)->map(function ($item) {
//                return [
//                    'package_sn'               => $item->package_sn,
//                    'way_bill_number'          => $item->logistics->way_bill_number ?? '',
//                    'fulfillment_express_line' => $item->logistics->fulfillment_express_line ?? '',
//                    'tracking_status_name'     => $item->tracking->status_name ?? '',
//                ];
//            })->toArray();

            //轨迹状态名称
//            $this->tracking_status_name = $this->expressOrders->sortByDesc('id')->first()->tracking->status_name ?? '';
//            $this->tracking_status_id = $this->expressOrders->sortByDesc('id')->first()->tracking->status ?? 0;
//        }

        //采购计划
        if ($this->purchasePlan) {
            $purchaseSnList = [];
            $this->purchasePlan->map(function ($plan) use (&$purchaseSnList) {
                $plan->purchase->map(function ($purchase) use (&$purchaseSnList) {
                    $purchaseSnList[] = $purchase->order_sn;
                });
            });
            if (!empty($purchaseSnList)) {
                $this->purchase_sn = implode(',', $purchaseSnList);
            }
        }

        //物流费用 开启商品一口价 物流费用为sku的物流总报价
        $logisticsFee = $this->order_one_price === Order::ONE_PRICE_OPEN ? $this->sku_logistics_fee : $this->logistics_fee;

        //订单报价总计:商品总报价+实际物流报价/sku物流总报价-优惠金额+其他补价（非商品一口价）
        $this->total_amount = $this->vendor_price + $logisticsFee - $this->favourable_price + $this->other_supplement_price;

        return [
            'id'                           => $this->id,
            'customer_id'                  => $this->customer_id, //客户id
            'goods_once_price'                  => $this->custom->goods_once_price, //客户id
            'order_id'                     => $this->order_id, //第三方平台订单id
            'payment_info'                 => $this->payment_info, //支付详情
            'platform'                     => $this->platform, //订单所属平台
            'cancel_reason'                => $this->cancel_reason, //取消理由
            'cancelled_at'                 => (string)$this->cancelled_at, //取消时间
            'created_at'                   => (string)$this->created_at, //创建时间
            'currency'                     => 'USD', //报价货币
            'original_currency'            => $this->currency, //订单支付货币
            'current_subtotal_price'       => $this->current_subtotal_price, //税前金额
            'current_total_discounts'      => $this->current_total_discounts, //折扣金额
            'current_total_price'          => $this->current_total_price, //合计金额
            'current_total_tax'            => $this->current_total_tax, //税金金额
            'subtotal_price'               => $this->subtotal_price, //小计
            'updated_at'                   => (string)$this->updated_at, //更新时间
            'deleted_at'                   => (string)$this->deleted_at, //
            'shop_id'                      => $this->shop_id, //
            'order_status'                 => $this->order_status, //订单状态
            'status'                       => $this->order_status, //订单状态
            'status_name'                  => $this->status_name, //订单状态
            'vendor_price'                 => $this->order_one_price === Order::ONE_PRICE_OPEN ? ($this->vendor_price + $logisticsFee) : $this->vendor_price, //供应商报价
            'vendor_change_price'          => $this->vendor_change_price, //供应商改价
            'other_supplement_price'       => $this->other_supplement_price, //其他补价
            'logistics_provider'           => $this->logistics_provider, //物流商
            'sku_status'                   => $this->sku_status, //sku状态：0-单sku单数 1-单sku多数 2-多sku
            'logistics_cost'                => $this->logistics_cost, //物流成本费用
            'logistics_fee'                => $logisticsFee, //物流费用
            'send_fail_reason'             => $this->send_fail_reason, //发货失败原因
            'logistics_provider_code'      => $this->logistics_provider_code, //物流商编码
            'paymented_at'                 => (string)$this->paymented_at, //付款时间
            'commited_at'                  => (string)$this->commited_at, //提交时间
            'is_change'                    => $this->is_change, //是否更换物流
            'change_status'                => $this->change_status, //换单状态：0-获取新单号 1-待打单 2-发货失败 3-发货成功
            'change_logistics_provider'    => $this->change_logistics_provider, //更换后的物流渠道
            'change_logistics_fee'         => $this->change_logistics_fee, //更换后的物流费用
            'express_line_id'              => $this->express_line_id, //渠道路线id
            'custom_order_id'              => $this->custom_order_id, //系统订单号
            'fulfillment_request_status'   => $this->fulfillment_request_status, //履约请求状态
            'is_hand_customs'              => $this->is_hand_customs, //是否手动报关: 1-是 0-否
            'name'                         => $this->name, //订单序列号
            'warehouse_remark'             => $this->warehouse_remark, //仓库备注
            'system_remark'                => $this->system_remark, //系统备注
            'change_price_remark'          => $this->change_price_remark, //改价备注
            'order_one_price'              => $this->order_one_price, //订单一口价: 1-是 2-否 0-区分默认一口价跟设置一口价的条件
            'favourable_price'             => $this->favourable_price, //优惠价格
            'sku_logistics_fee'            => $this->sku_logistics_fee, //sku物流报价 订单一口价时保存
            'order_type'                   => $this->order_type, //订单类型 1-代发订单 2-备货订单
            'warehouse_id'                 => $this->warehouse_id, //仓库ID
            'remark'                       => $this->remark, //订单备注
            'order_status_before'          => $this->order_status_before, //前一个订单状态
            'is_shipping'                  => $this->is_shipping, //是否发货：0-否 1是
            'staff_id'                     => $this->staff_id, //员工ID
            'fulfillment_platform'         => $this->fulfillment_platform, //履约平台
            'fulfillment_push_status'      => $this->fulfillment_push_status, //履约推送状态
            'fulfillment_push_status_name' => $this->fulfillment_push_status_name,
            'fulfillment_platform_name'    => $this->fulfillment_platform_name,
            'deliver_time'                 => (string)$this->deliver_time, //发货时间
            'use_customer_stock'           => $this->use_customer_stock, //使用客户库存 0-否 1-是
            'financial_status'             => $this->financial_status, //财务状态 0-未支付 1-已支付 2-补收费用 3-部分退款 4-全额退款
            'is_disable'                   => $this->is_disable, //禁止处理：0-否 1是
            'charge_type_id'               => $this->charge_type_id ?? 0, //补收类型
            'supplement_price'             => $this->supplement_price ?? 0, //补收金额
            'supplement_charge_remark'     => $this->supplement_charge_remark ?? '', //补收备注
            'refund_price'                 => $this->refund_price ?? 0,

            'line_items'            => ShopOrderLineItemList::collection($this->allLineItems),
            'country'               => $this->shippingAddress->country ?? '',//收件人国家
            'country_code'          => $this->shippingAddress->country_code ?? '',//收件人国家代码
            'is_billing_address'    => $this->shippingAddress->is_billing_address ?? 0,
            'purchase_sn'           => $this->purchase_sn ?? '', //采购订单号
            'package_sn'            => $this->package_sn ?? '',//包裹号
            'tracking_status_name'  => $this->tracking_status_name ?? '', //轨迹状态名称
            'tracking_status_id'    => $this->tracking_status_id ?? 0, //轨迹状态
            'shop_name'             => $this->shop->shop_name ?? '', //店铺名称
            'shop_url'              => $this->shop->shop_url ?? '', //店铺url 用于订单商品跳转
//            'logistics_apply'      => $this->logisticsApply ?? '', //物流申请信息
            'logistics_apply'       => null,
            'packages'              => $this->packages ?: [],
//            'logistics_apply_list' => $this->logisticsApplyList ?? [], //物流申请信息（多个）
            'logistics_apply_list'  => [],
            'channel'               => $this->channel ?? '',//物流渠道
            'express_line'          => $this->expressLine ?? '', //渠道路线
            'staff_name'            => $this->staff->name ?? '', //员工名称
            'total_amount'          => $this->total_amount ?? 0, //报价总计
            'shop'                  => $this->shop ?? [],
            'customer_number'       => $this->custom->customer_number ?? '', //客户编号
            'abnormal_status'       => $this->abnormal_status, //异常状态
            'abnormal'              => $this->abnormal ?: [], //异常信息
            'sync_type'             => $this->sync_type ?? 1, //同步类型 1-自动同步 2-手动同步
            'charge_type_name'      => $this->chargeType ? $this->chargeType->name : '', //补收类型数据
            'refund_type_str'       => $this->refund_type_str,
            'platform_status'       => $this->platform_status,
            'platform_status_name'  => $this->platform_status_name,
            'logistics_status'      => $this->logistics_status,
            'stock_status'          => $this->stock_status,
            'platform_fulfillments' => $this->platformFulfillments,
            'prop_id' => $this->prop_id,
            'total_weight' => $this->total_weight,
            'zip' => $this->shippingAddress->zip ?? ''
        ];
    }
}
