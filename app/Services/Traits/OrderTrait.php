<?php

namespace App\Services\Traits;

use App\Lib\Platform;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Arr;

trait OrderTrait
{
    protected $query;

    public function queryCondition(): bool
    {
        //无关联采购单
        if (isset($this->formData['purchase']) && !empty($this->formData['purchase'])) {
            $this->query->doesntHave('purchaseOrder');
        }

        // 如果没有订单状态使用other status（包括取消和搁置） 字段查询
        if (!isset($this->formData['status']) || $this->formData['status'] === '') $this->formData['status'] = $this->formData['other_status'] ?? '';

        //订单状态
        if(isset($this->formData['status']) && $this->formData['status'] !== '') {
            $this->formData['status'] = Arr::wrap($this->formData['status']);
            //异常订单专门查询处理
            if (in_array('abnormal', $this->formData['status'])) {
                if (!empty($this->formData['abnormal_reason'])) {
                    $this->query->whereHas('abnormal', function ($query) {
                        $query->where('abnormal_reason', $this->formData['abnormal_reason']);
                    });
                }
                $this->query->where('abnormal_status', Order::ORDER_STATUS_ABNORMAL);
            } else {
                $this->query->whereIn('order_status', $this->formData['status'])->where('abnormal_status',Order::ORDER_STATUS_NORMAL);
            }

            //未报价状态只查询平台订单状态为开启、平台支付状态为已付款
            if (count($this->formData['status']) === 1 && $this->formData['status'][0] == Order::STATUS_QUOTE_NO)  {
                $this->query->where(function ($query) {
                    $query->whereIn('platform_payment_status', ['paid', 'partially_refunded', 'partially_paid', 'authorized'])->orWhere('platform', '!=', Platform::SHOPIFY);
                });
            }
        }

        //店铺ID
        if(isset($this->formData['shop_ids']) && !empty($this->formData['shop_ids'])) {
            $this->query->whereIn('shop_id', $this->formData['shop_ids']);
        }

        // 订单属性ID
        if (isset($this->formData['prop_id']) && !empty($this->formData['prop_id'])) {
            $this->query->where('prop_id', $this->formData['prop_id']);
        }

        //无运单号
        if (!empty($this->formData['no_waybill_number'])) {
            $this->query->where(function ($query) {
                $query->whereHas('logisticsApply', function ($query) {
                    $query->whereNull('way_bill_number')->orWhere('way_bill_number', '');
                })->orDoesntHave('logisticsApply');
            });
        }


        //订单关键字
        if (!empty($this->formData['order_keyword'] ?? '')) {
            switch ($this->formData['order_keyword_type']) {
                case 1:
                    //平台订单
                    $field = 'order_id';
                    break;
                case 2:
                    //平台编号
                    $field = 'name';
                    break;
                case 3:
                    //系统单号
                    $field = 'custom_order_id';
                    break;
                default:
                    $field = 'order_id';
            }

            $keyword = $this->formData['order_keyword'];
            if (is_array($keyword)) {
                $this->query->whereIn($field, $keyword);
            } else {
                $this->query->where($field, 'like', $keyword.'%');
            }
        }

        //产品关键字
        if (!empty($this->formData['product_keyword'] ?? '')) {
            switch ($this->formData['product_keyword_type']) {
                case 1:
                    //平台SKU
                    $this->query->whereHas('allLineItems', function ($query) {
                        $query->where('variant_id', 'like', $this->formData['product_keyword'].'%');
                    });
                    break;
                case 2:
                    //关联SKU
                    $this->query->whereHas('allLineItems.mapping.goodsSku', function ($query) {
                        $query->where('sku_id', 'like', $this->formData['product_keyword'].'%');
                    });
                    break;
                case 3:
                    //产品名称
                    $this->query->whereHas('allLineItems', function ($query) {
                        $query->where('name', 'like', $this->formData['product_keyword'].'%')
                            ->orWhere('title', 'like', $this->formData['product_keyword'].'%');
                    });
                    break;
            }
        }

        //物流关键字
        if (!empty($this->formData['logistics_keyword'] ?? '')) {
            switch ($this->formData['logistics_keyword_type']) {
                case 1:
                    //物流单号
                    $this->query->whereHas('logisticsApply', function ($query) {
                        $query->where('way_bill_number', 'like', $this->formData['logistics_keyword'].'%');
                    });
                    break;
                case 2:
                    //包裹号
                    $this->query->whereHas('expressOrders', function ($query) {
                        $query->where('package_sn', 'like', $this->formData['logistics_keyword'].'%');
                    });
                    break;
                case 3:
                    //收件人
                    $this->query->whereHas('shippingAddress', function ($query) {
                        $query->where('name', 'like', $this->formData['logistics_keyword'].'%');
                    });
                    break;
                case 4:
                    //邮箱
                    $this->query->whereHas('shippingAddress', function ($query) {
                        $query->where('email', 'like', $this->formData['logistics_keyword'].'%');
                    });
                    break;
            }
        }

        //搜索时间范围类型
        if (!empty($this->formData['time_range_type'] ?? '') && !empty($this->formData['time_range_start'] ?? '') && !empty($this->formData['time_range_end'] ?? '')) {
            $timeArray = [Carbon::parse($this->formData['time_range_start'])->startOfDay(), Carbon::parse($this->formData['time_range_end'])->endOfDay()];

            switch ($this->formData['time_range_type']) {
                case 1:
                    //创建时间
                    $this->query->whereBetween('created_at', $timeArray);
                    break;
                case 2:
                    //更新时间
                    $this->query->whereBetween('updated_at', $timeArray);
                    break;
                case 3:
                    //付款时间
                    $this->query->whereBetween('paymented_at', $timeArray);
                    break;
                case 4:
                    //交运时间
                    $this->query->whereBetween('shipment_time', $timeArray);
                    break;
                case 5:
                    //发货时间
                    $this->query->whereBetween('deliver_time', $timeArray);
                    break;
            }
        }

        return true;
    }
}
