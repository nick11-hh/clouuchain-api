<?php

namespace App\Services\Open;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\Country;
use App\Models\Custom;
use App\Models\GoodsSku;
use App\Models\Order;
use App\Models\OrderItemMapping;
use App\Models\OrderLineItem;
use App\Models\OrderShippingAddress;
use App\Models\ShopModel;
use App\Models\ShopOrderLogs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderService extends BaseService
{
    /**
     * @throws AccidentException
     * @throws ValidationException
     */
    public function push($params)
    {
        $orderData = $params['data'] ?? [];
        if (empty($orderData)) {
            throw new AccidentException("数据为空", Code::OPERATE_FAIL);
        }

        return DB::transaction(function () use ($params, $orderData) {
            // 预先获取所有需要的数据以减少查询次数
            $custom_id = $params['custom_id'] ?? 0;
            $custom = Custom::query()->whereKey($custom_id)->first();
            if (empty($custom)) {
                throw new AccidentException("客户ID：{$custom_id} 不存在", Code::OPERATE_FAIL);
            }

            // 获取所有店铺信息
            $shopNames = array_column($orderData, 'shop_name');
            $shops = ShopModel::query()
                ->whereIn('shop_name', $shopNames)
                ->where('customer_id', $custom_id)
                ->get()
                ->keyBy('shop_name');

            // 获取所有国家信息
            $countries = array_column($orderData, 'country');
            $countryData = Country::query()
                ->where(function ($query) use ($countries) {
                    $query->whereIn('cn_name', $countries)
                        ->orWhereIn('en_name', $countries)
                        ->orWhereIn('code', $countries);
                })
                ->get()
                ->keyBy('code');

            foreach ($orderData as $row) {
                // 验证所有需要使用的字段
                Validator::make($row, [
                    'shop_name' => 'required|string',
                    'order_id' => 'required|string',
                    'first_name' => 'required|string',
                    'last_name' => 'sometimes|nullable|string',
                    'country' => 'required|string',
                    'city' => 'required|string',
                    'address1' => 'required|string',
                    'zip' => 'required|string',
                    'name' => 'sometimes|nullable|string',
                    'remark' => 'sometimes|nullable|string',
                    'current_total_price' => 'sometimes|nullable|numeric',
                    'province' => 'sometimes|nullable|string',
                    'address2' => 'sometimes|nullable|string',
                    'phone' => 'sometimes|nullable|string',
                    'email' => 'sometimes|nullable|string',
                    'tax' => 'sometimes|nullable|string',
                    'image_url' => 'sometimes|nullable|string',
                    'logistics_provider' => 'required|string',
                    'logistics_provider_code' => 'required|string',
                    'express_line_id' => 'required|integer',
                    'channel_name' => 'required|string',
                    'quote_id' => 'required|integer',
                ])->validate();
                //校验order_id，只校验下划线后的部分为纯字母
                if (!preg_match('/_([a-zA-Z])+$/i', $row['order_id'])) {
                    throw new AccidentException('订单ID格式错误，下划线后必须是纯字母格式', Code::OPERATE_FAIL);
                }

                // 验证店铺是否存在
                if (!$shops->has($row['shop_name'])) {
                    throw new AccidentException("店铺名称：{$row['shop_name']} 不存在", Code::OPERATE_FAIL);
                }
                $shop = $shops->get($row['shop_name']);

                // 验证国家是否存在
                $country = $countryData->first(function ($item) use ($row) {
                    return $item->cn_name == $row['country'] ||
                        $item->en_name == $row['country'] ||
                        $item->code == $row['country'];
                });

                if (empty($country)) {
                    throw new AccidentException("国家：{$row['country']}，不支持", Code::OPERATE_FAIL);
                }

                if (empty($row['express_line_id'])) {
                    throw new AccidentException("物流ID：{$row['express_line_id']} 不存在", Code::OPERATE_FAIL);
                }

                $orderDataArray = [
                    'customer_id' => $custom_id,
                    'order_id' => $row['order_id'],
                    'platform_order_id' => $row['order_id'],
                    'shop_id' => $shop->id,
                    'platform' => $shop->platform,
                    'currency' => 'USD',
                    'order_status' => Order::STATUS_QUOTE_ASK,
                    'custom_order_id' => generateOrderId(),
                    'payment_info' => [],
                    'name' => $row['name'] ?? '',
                    'remark' => $row['remark'] ?? '',
                    'current_total_price' => $row['current_total_price'] ?? 0,
                    'express_line_id' => $row['express_line_id'],
                ];

                // 使用updateOrCreate保证原子性并减少查询次数
                $order = Order::query()->updateOrCreate(
                    ['order_id' => $orderDataArray['order_id'], 'shop_id' => $shop->id],
                    $orderDataArray
                );

                $shippingData = [
                    'order_id' => $order->id,
                    'first_name' => $row['first_name'] ?? '',
                    'last_name' => $row['last_name'] ?? '',
                    'name' => ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''),
                    'country' => $row['country'],
                    'country_code' => strtoupper($country->code),
                    'province' => $row['province'] ?? '',
                    'city' => $row['city'],
                    'address1' => $row['address1'],
                    'address2' => $row['address2'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'email' => $row['email'] ?? '',
                    'zip' => $row['zip'],
                    'tax' => $row['tax'] ?? '',
                ];

                // 使用updateOrCreate保证原子性并减少查询次数
                OrderShippingAddress::query()->updateOrCreate(
                    ['order_id' => $order->id],
                    $shippingData
                );

                $sku_arr = $row['skus'];
                foreach ($sku_arr as $sku) {
                    $goodsSku = GoodsSku::query()->with('goods')->where('sku_id', $sku['sku_id'])->first();
                    if (!$goodsSku) {
                        throw new AccidentException("sku_id：{$sku['sku_id']} 不存在", Code::OPERATE_FAIL);
                    }
                    $skuArray = $goodsSku->toArray();
                    $skuArray['quantity'] = $sku['quantity'];
                    $skuArray['add_type'] = OrderLineItem::ADD_TYPE_MANUALLY_ADD;
                    $skuArray['goods_name'] = $goodsSku->goods->goods_name ?? '';
                    $skuData = OrderLineItem::initByLocalProduct($order->id, $skuArray);
                    $item = OrderLineItem::query()->create($skuData);

                    //保存item-sku映射关系
                    OrderItemMapping::query()->updateOrCreate([
                        'platform' => $order->platform,
                        'platform_variant_id' => $item->variant_id,
                    ], ['goods_sku_id' => $goodsSku->id]);

                    ShopOrderLogs::addLog([
                        'order_id' => $order->id,
                        'operate_type' => ShopOrderLogs::OPERATOR_TYPE_MANUALLY_ADD_ORDER_GOODS,
                        'content' => "客户手动增加订单商品,SKU：{$item->variant_id}，规格名称：{$item->variant_title}",
                    ]);
                }
            }
            return true;
        });
    }

    /**
     * 获取订单状态
     *
     * @return array
     * @throws AccidentException
     * @throws ValidationException
     */
    public function getOrderInfo($params)
    {
        $query = Order::query();
        $createTimeStart = $params['createTimeStart'] ?? '';
        $createTimeEnd = $params['createTimeEnd'] ?? '';
        $orderIds = $params['orderIds'] ?? '';
        $orderArr = explode(',', $orderIds);
        if (count($orderArr) > 10) {
            throw new AccidentException("最多只能同时查询10个订单", Code::OPERATE_FAIL);
        }
        if (!empty($orderArr)) {
            $query = $query->whereIn('platform_order_id', $orderArr);
        }
        if (!empty($createTimeStart) || !empty($createTimeEnd)) {
            $query = $query->whereBetween('created_at', [$createTimeStart, $createTimeEnd]);
        }
        $orderStatus = $params['orderStatus'] ?? '';
        $orderStatusArr = [
            Order::STATUS_QUOTE_NO,
            Order::STATUS_QUOTE_ASK,
            Order::STATUS_QUOTED,
            Order::STATUS_PENDING,
            Order::STATUS_APPLY_NUM,
            Order::STATUS_SHIPPED,
            Order::STATUS_CANCELLED,
            Order::STATUS_SHELVE,
            Order::STATUS_NOT_SHIPPING,
            Order::STATUS_DELIVERED,
            Order::STATUS_ARCHIVE,
        ];
        if (!empty($orderStatus)) {
            if (!in_array($orderStatus, $orderStatusArr)) {
                throw new AccidentException("订单状态错误", Code::OPERATE_FAIL);
            }
            $query = $query->where('order_status', $orderStatus);
        }

        $custom_id = $params['custom_id'] ?? 0;
        $custom = Custom::query()->whereKey($custom_id)->first();
        if (empty($custom)) {
            throw new AccidentException("客户不存在", Code::OPERATE_FAIL);
        }

        // 返回订单状态信息
        return $query->get()->toArray();
    }

    /**
     * 获取物流信息
     *
     * @return array
     * @throws AccidentException
     * @throws ValidationException
     */
    public function getLogisticInfo($params)
    {
        $query = Order::query();
        $orderIds = $params['orderIds'] ?? '';
        if (empty($orderIds)) {
            throw new AccidentException("订单不存在", Code::OPERATE_FAIL);
        }
        $orderArr = explode(',', $orderIds);
        if (count($orderArr) > 10) {
            throw new AccidentException("最多只能同时查询10个订单", Code::OPERATE_FAIL);
        }
        $query = $query->whereIn('platform_order_id', $orderArr);

        $custom_id = $params['custom_id'] ?? 0;
        $custom = Custom::query()->whereKey($custom_id)->first();
        if (empty($custom)) {
            throw new AccidentException("客户不存在", Code::OPERATE_FAIL);
        }

        return $query->with(['packages.logisticsApply:package_id,way_bill_number,tracking_status,tracking_platform,last_mail_tracking_number'])
                     ->select('id', 'platform_order_id')
                     ->get()
                     ->toArray();
    }
}
