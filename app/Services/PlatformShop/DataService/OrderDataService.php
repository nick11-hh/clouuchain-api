<?php

namespace App\Services\PlatformShop\DataService;

use App\Http\Controllers\Client\ExpressPriceController;
use App\Models\Country;
use App\Models\Custom;
use App\Models\CustomsQuoteConfig;
use App\Models\ExchangeRateModel;
use App\Models\ExpressLineCountryModel;
use App\Models\ExpressLineModel;
use App\Models\GoodsSku;
use App\Models\LogisticsChannelModel;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\LogisticsCustomsModel;
use App\Models\Order;
use App\Models\OrderItemMapping;
use App\Models\OrderLineItem;
use App\Models\OrderShippingAddress;
use App\Models\OrderThirdPartyFulfillmentLogs;
use App\Models\PlatformVirtualSku;
use App\Models\ProductQuoteApplyItem;
use App\Models\QuotationRecordModel;
use App\Models\ShopOrderAbnormal;
use App\Models\ShopOrderFulfillments;
use App\Models\ShopOrderLogs;
use App\Models\SystemConfig;
use App\Models\ThirdPartyWarehouseConfig;
use App\Models\WarehouseAddress;
use App\Models\PlatformProductSku;
use App\Models\PlatformProduct;
use App\Services\Admin\ExpressPriceService;
use App\Services\Admin\OrderService;
use App\Services\Admin\PackageService;
use App\Services\Admin\WarehouseAddressService;
use App\Services\Base\OrderQuoteService;
use App\Services\Base\SystemConfigService;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseService;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\Base\OrderBaseService;

class OrderDataService
{
    public function createOrder($data, $shop, $source = Order::ORDER_SOURCE_MANUAL_SYNC)
    {
        $isExists = Order::query()->where('platform_order_id', (string)$data['platform_order_id'])
            ->where('shop_id', $shop->id)
            ->where('platform', $data['platform'])
            ->where('customer_id', $shop->customer_id)
            ->first();

        $order = DB::transaction(function () use ($data, $shop, $source, $isExists) {
            $order = $isExists;

            if (!empty($order)) { // 更新数据
                $orderBaseService = new OrderBaseService($order);

                $address = OrderShippingAddress::query()->where('order_id', $order->id)->first();

                $addressData = OrderShippingAddress::init($data['address'], $order->id);
                if (!empty($address)) {
                    if (!empty($addressData['first_name']) || !empty($addressData['name'])) {
                        //比较差异值
                        $differential = differenceComparing($addressData, $address->toArray(), OrderShippingAddress::fieldName(['tax', 'email', 'company']));

                        if (empty($address->company) && !empty($addressData['company'])) {
                            $address->update($addressData);
                            (new PackageService())->syncOrderAddress($order);
                        }

                        //没有手动更新过才更新shopify地址
                        if (empty($address->edited_at)) {
                            if ($differential['content'] ?? '') {
                                if (in_array($order->order_status, [Order::STATUS_QUOTE_NO, Order::STATUS_QUOTE_ASK, Order::STATUS_QUOTED, Order::STATUS_PENDING])) {
                                    $orderBaseService->addOrderAbnormal(ShopOrderAbnormal::ABNORMAL_ADDRESS_UPDATE, '平台收货地址变更 ' . $differential['content']);
                                }

                                // 已报价未支付的订单直接打回报价中
                                if  ($order->order_status === Order::STATUS_QUOTED) {
                                    (new OrderService(new  Order()))->orderRollbackQuote(['ids' => [$order->id]], '待付款订单地址变更 ');
                                    $order->quoting_reason = 'abnormal';
                                }

                                // 同步更新到马帮收货地址
                                if ($order->fulfillment_platform == ThirdPartyWarehouseConfig::PLATFORM_MABANG) {
                                    $thirdPartyWarehouseService = new ThirdPartyWarehouseService();
                                    $thirdPartyWarehouseService->updateOrderData($order, OrderThirdPartyFulfillmentLogs::UPDATE_ORDER_SHIPPING_ADDRESS, $addressData);
                                }


                                $address->update($addressData);
                                (new PackageService())->syncOrderAddress($order);
                            }
                        }

                    }
                } else {
                    OrderShippingAddress::query()->create($addressData);
                }

                $this->createOrUpdateFulfillments($order, $data['fulfillments'] ?? []);

                $updateMark = '';
                $orderAbnormalReason = '';
                if (!empty($data['refunds']) && !empty($data['platform_order_status']) && $data['platform_order_status'] != 'cancel') {
                    //查询不包含软删除的item
                    $allLineItemIds = OrderLineItem::query()->where('order_id', $order->id)->pluck('line_item_id')->toArray();

                    foreach ($data['refunds'] as $refund) {
                        foreach ($refund['refund_line_items'] as $refundLineItem) {
                            //现有数量为0时才会判断当前item被删除，因为item数量减少时refunds也有记录
                            //比如下单时item数量为5，后续在店铺将数量调整为4的时也能在这里查询到item
                            $currentQuantity = $refundLineItem['line_item']['current_quantity'] ?? 0;

                            if (in_array($refundLineItem['line_item_id'], $allLineItemIds) && empty($currentQuantity)) {
                                //使用软删除
                                OrderLineItem::query()->where('order_id', $order->id)->where('line_item_id', $refundLineItem['line_item_id'])->delete();

                                $variantId = $refundLineItem['line_item']['variant_id'] ?? '';
                                $sku = $refundLineItem['line_item']['sku'] ?? '';
                                $updateMark .= "订单商品被删除，sku：{$sku}，variant_id: {$variantId}；";

                                $orderAbnormalReason = ShopOrderAbnormal::ABNORMAL_GOODS_REMOVE;
                            }
                        }
                    }
                }

                foreach ($data['line_items'] as $item) {
                    //包含软删除的item
                    $lineItem = OrderLineItem::query()->withTrashed()->where('order_id', $order->id)->where('line_item_id', $item['line_item_id'])->first();
                    if (empty($lineItem)) {
                        $this->addOrderItem($order->id, $data['platform'], $item);
                        $updateMark .= "订单新增商品，sku：{$item['sku']}，variant_id: {$item['variant_id']}；";
                        $orderAbnormalReason = ShopOrderAbnormal::ABNORMAL_GOODS_ADD;
                    } else {
                        $lineItem->variant_id = $item['variant_id'] ?? 0;
                        $lineItem->properties = $item['properties'] ?? [];
                        if ($lineItem->quantity !== $item['quantity']) {
                            if (empty($lineItem->deleted_at)) {
                                $updateMark .= "订单商品数量变更，由{$lineItem->quantity} 到 {$item['quantity']}，sku：{$item['sku']}，variant_id: {$item['variant_id']}；";
                                $orderAbnormalReason = $lineItem->quantity < $item['quantity'] ? ShopOrderAbnormal::ABNORMAL_GOODS_ADD : ShopOrderAbnormal::ABNORMAL_GOODS_REMOVE;
                            }
                            $lineItem->quantity = $item['quantity'];
                        }
                        if (empty($lineItem->imgs[0]) && !empty($item['imgs'])) {
                            $lineItem->imgs = $item['imgs'];
                        }
                        $lineItem->save();
                    }
                }
                if (empty($data['platform_status'])) $data['platform_status'] = Order::PLATFORM_WAITING_SHIPMENT;

                // 订单在待发货状态，并且我们平台没有交运，但是shopify平台已经发货，添加异常
                if (in_array($order->order_status, [Order::STATUS_PENDING, Order::STATUS_APPLY_NUM])
                    && !$order->is_shipping && in_array($data['platform_status'], [Order::PLATFORM_SHIPPING, Order::PLATFORM_COMPLETED])
                    && empty($order->confirm_shipment_at)  //没有手动设置了发货
                ) {
                    $service = new OrderBaseService($order);
                    $service->addOrderAbnormal(ShopOrderAbnormal::ABNORMAL_OTHER_FULFILMENT, '订单在已在其他平台发货');
                }

                if ($data['platform_status'] == Order::PLATFORM_CANCELLED) {
                    $updateMark .= '平台订单取消；';
                    $orderAbnormalReason = ShopOrderAbnormal::ABNORMAL_ORDER_CANCEL;
                }

                if ($orderAbnormalReason) {
                    // 已经报价过且未完成的订单，设置异常
                    if (in_array($order->order_status, Order::QUOTED_BUT_NOT_COMPLETE)) {
                        $service = new OrderBaseService($order);
                        $service->addOrderAbnormal($orderAbnormalReason, $updateMark);
                    }

                    // 已报价未支付的订单直接打回报价中
                    if  ($order->order_status === Order::STATUS_QUOTED) {
                        (new OrderService(new  Order()))->orderRollbackQuote(['ids' => [$order->id]], '待付款订单商品变更 ');
                        $order->quoting_reason = 'abnormal';
                    }

                    // 未处理的订单直接取消
                    if ($orderAbnormalReason == ShopOrderAbnormal::ABNORMAL_ORDER_CANCEL
                        && in_array($order->order_status, [Order::STATUS_QUOTE_NO, Order::STATUS_QUOTE_ASK, Order::STATUS_QUOTED])) {
                        $order->order_status = Order::STATUS_CANCELLED;

                        ShopOrderLogs::addLog([
                            'order_id'      => $order->id,
                            'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
                            'content'       => "平台订单取消，当前订单暂未付款，系统自动设置为取消",
                            'operator_id'   => 0
                        ]);
                    }
                }

                // 订单在平台已经发货，但是在我们系统未付款的且没有强制发货，自动进入不发货状态
                if (
                    in_array($data['platform_status'], [Order::PLATFORM_COMPLETED, Order::PLATFORM_SHIPPING])
                    && in_array($order->order_status, [Order::STATUS_WAIT, Order::STATUS_QUOTE_ASK, Order::STATUS_QUOTED])
                    && empty($order->confirm_shipment_at)
                ) {
                    ShopOrderLogs::addLog([
                        'order_id'      => $order->id,
                        'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
                        'content'       => "订单在平台已发货，系统未付款，自动移入不发货状态",
                        'operator_id'   => 0
                    ]);
                    $order->order_status_before = $order->order_status;
                    $order->order_status = Order::STATUS_NOT_SHIPPING;
                }

                $order->sku_status = count($data['line_items']) > 1 ? 2 : ($data['line_items'][0]['quantity'] === 1 ? 0 : 1);
                $order->platform_status = $data['platform_status'] ?? '';
                $order->platform_order_status = $data['platform_order_status'] ?? '';
                $order->platform_payment_status = $data['platform_payment_status'] ?? '';
                $order->platform_fulfillment_status = $data['platform_fulfillment_status'] ?? '';
                $order->platform_order_id = $data['platform_order_id'];
                $order->save();
                (new PackageService())->syncOrderGoods($order);
                return $order;
            }

            //处理并发重复写入问题
            $cacheKey = 'CREATE_SHOP_ORDER:' . $shop->id . '_' . $data['order_id'];
            if (Cache::has($cacheKey)) {
                return false;
            }
            Cache::set($cacheKey, $data['order_id'], 3);

            $syncOrderFilterRule = SystemConfigService::getConfigValue(SystemConfig::SYNC_ORDER_FILTER_RULE);

            //同步订单过滤规则 1所有订单 2部分订单(产品已报价)
            if ($syncOrderFilterRule != 1) {
                info('同步订单过滤');
                foreach ($data['line_items'] as $index => $item) {
                    $platformProduct = PlatformProduct::query()
                        ->with(['skus', 'logisticsChannel', 'skus.applyMapping'])
                        ->where(['shop_type' => $data['platform']])
                        ->whereHas('skus.applyMapping', function ($query) use ($item) {
                            $query->where('platform_variant_id', $item['variant_id'])
                                ->where('status', ProductQuoteApplyItem::QUOTE_STATUS_QUOTED);
                        })
                        ->first();

                    if (empty($platformProduct)) {
                        unset($data['line_items'][$index]);
                    }
                }

                if (count($data['line_items']) <= 0) {
                    return false;
                }
            }

            // 如果订单已发货、已完成、已取消则订单自动进入不发货
            $data['order_status'] = in_array($data['platform_status'], [Order::PLATFORM_COMPLETED, Order::PLATFORM_SHIPPING]) ? Order::STATUS_NOT_SHIPPING : Order::STATUS_WAIT;

            $orderData = Order::init($data, $shop);

            $orderData['custom_order_id'] = generateOrderId($shop->customer_id ?? 0);

            $order = Order::query()->create($orderData);
            foreach ($data['line_items'] as $item) {
                $this->addOrderItem($order->id, $data['platform'], $item);
            }

            if (!empty($data['address'])) {
                $addressData = OrderShippingAddress::init($data['address'], $order->id);
                OrderShippingAddress::query()->create($addressData);
            }

            if (!empty($data['fulfillments'])) {
                $this->createOrUpdateFulfillments($order, $data['fulfillments']);
            }

            $logContent = match ($source) {
                Order::ORDER_SOURCE_AUTO_PULL => '系统自动同步并创建订单',
                Order::ORDER_SOURCE_MANUAL_SYNC => '客户手动同步并创建订单',
                Order::ORDER_SOURCE_WEBHOOK => '平台webhook推送并创建订单',
                default => "从平台同步并创建订单"
            };
            ShopOrderLogs::addLog([
                'order_id' => $order->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
                'content' => $logContent,
                'operator_id' => 0,
            ]);

            return $order;
        });

        if ($isExists) {
            return true;
        }

        if (empty($order)) {
            return false;
        }

        DB::beginTransaction();
        try {
            //开启订单自动报价模式
            $autoQuote = SystemConfig::query()->where('config_key', SystemConfig::IS_AUTO_ORDER_QUOTE)->value('config_value');
            $confirmQuotation = (bool)$autoQuote;

            //加载items跟收件地址，一客一价需要使用
            $order->load(['lineItems.mapping.goodsSku', 'shippingAddress']);

            $this->autoOrderQuote($order, $confirmQuotation);
            DB::commit();
        } catch (Exception $e) {
            info('autoOrderQuote错误日志', [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'msg' => $e->getMessage(),
            ]);
            DB::rollBack();
            return false;
        }

        return true;
    }

    /**
     * @param $order
     * @param $confirmQuotation
     * @return void
     * @throws Exception
     */
    public function autoOrderQuote($order, $confirmQuotation = false)
    {
        $params = [
            'mapping_list' => [],
            'express_line_id' => 0,
            'use_customer_stock' => 0,
            'custom_logistic_price' => 0,
            'custom_favourable_price' => 0,
            'other_supplement_price' => 0,
            'order_one_price' => 0
        ];
        if (empty($params['mapping_list'])) {
            $mapping_list = OrderLineItem::query()
                ->with(['mapping' => function($query) {
                    $query->select('id', 'goods_sku_id', 'platform_variant_id');
                }])
                ->where('order_id', $order->id)
                ->get()
                ->map(function($item) {
                    return [
                        'line_item_id' => $item->id,
                        'sku_id' => $item->mapping?->goods_sku_id ?? $item->goods_sku_id
                    ];
                })
                ->toArray();
            $params['mapping_list'] = $mapping_list;
        }
        info("{$order->order_id}自动报价参数", $params);
        $params['goods_once_price'] = Custom::query()->where('id', $order->customer_id)->value('goods_once_price');
        $params['customer_id'] = (int) $order->customer_id;
        if ($order->exchange_rates > 0) {
            $params['exchange_rates'] = $order->exchange_rates;
        } else {
            $exchange_rates = ExchangeRateModel::query()->where('currency_code', 'CNY')
                ->value('custom_exchange_rate');
            $params['exchange_rates'] = $exchange_rates;
        }
        if ($order->order_status > 1) {
            // 客户产品利润
            $params['product_quote_default_profit_rate'] = $order->product_profit;
            // 客户物流利润
            $params['freight_quote_default_profit_rate'] = $order->freight_profit;
        } else {
            $customsQuoteConfig = CustomsQuoteConfig::query()->where('customer_id', $order->customer_id)
                ->select('product_quote_default_profit_rate', 'freight_quote_default_profit_rate')->first();
            // 客户产品利润
            $params['product_quote_default_profit_rate'] = $customsQuoteConfig->product_quote_default_profit_rate;
            // 客户物流利润
            $params['freight_quote_default_profit_rate'] = $customsQuoteConfig->freight_quote_default_profit_rate;
        }
        $quoteService = new OrderQuoteService($order);
        $params['quote_id'] = $order->quote_id;
        $params['channel_name'] = $order->channel_name;
        $params['express_line_id'] = $order->express_line_id;
        $quoteData = $quoteService->setAutoPrice()->setSavePriceQuote()->orderQuote($params);

        $quoteService->saveQuoteData($quoteData, $params);

        // 商品映射关系
        $mappingData = [];
        foreach ($quoteData['goods_price_detail'] as $goodsPrice) {
            $unit_price = $goodsPrice['unit_price'] ?? 0;
            if ($unit_price > 0) {
                $unit_price = round(bcdiv($unit_price, $params['exchange_rates'], 4), 2);
            }
            $mappingData[] = [
                'line_item_id' => $goodsPrice['line_item_id'],
                'sku_id' => $goodsPrice['mappingGoodsSku']['id'] ?? 0,
                'quote_price' => $unit_price,
                'purchase_price' => $goodsPrice['purchase_price'] ?? 0,
                'profit_price' => $goodsPrice['profit_price'] ?? 0
            ];
        }

        // 保存价格
        $orderService = new OrderService(new Order());
        $orderService->checkAndSaveOrderMapping($order, $mappingData);

        // 保存物流渠道
        $order->logistics_provider = $quoteData['logistics_provider'];
        $order->logistics_provider_code = $quoteData['logistics_provider_code'];
        $order->express_line_id = $quoteData['express_line_id'];

        //物流成本、物流利润
        $order->freight_quote_calculate_method = $quoteData['freight_quote_calculate_method'];
        $order->logistics_cost = $quoteData['logistics_cost'];
        $order->logistics_profit = $quoteData['logistics_profit'];

        //提交报价
        if($confirmQuotation) {
            $order->order_status = Order::STATUS_QUOTED;
        }

        $order->save();

        $orderService->declaration($order->id);

        $logContent = "订单自动报价：商品报价({$quoteData['goods_price']}) + 物流报价({$quoteData['logistics_fee']}) + 其他补价({$quoteData['other_supplement_price']}) - 优惠价格({$quoteData['favourable_price']}) = {$quoteData['total_price']}";
        ShopOrderLogs::addLog([
            'order_id' => $order->id,
            'operator_type' => ShopOrderLogs::OPERATOR_TYPE_VENDOR_PRICE,
            'content' => $logContent,
        ]);

        $logContent2 = "订单自动报价：物流成本({$quoteData['logistics_cost']} ¥)、物流利润({$quoteData['logistics_profit']} ¥)";
        ShopOrderLogs::addLog([
            'order_id' => $order->id,
            'operator_type' => ShopOrderLogs::OPERATOR_TYPE_VENDOR_PRICE,
            'content' => $logContent2,
        ]);
    }

    protected function addOrderItem($orderId, $platform, $item)
    {
        $itemData = OrderLineItem::init($orderId, $item);
        $item = OrderLineItem::query()->create($itemData);
        $platformVirtualSku = PlatformVirtualSku::query()->where('platform', $platform)->where('platform_variant_id', $item->variant_id)->first();
        if (!empty($platformVirtualSku)) {
            return $item->delete();
        }
        //关联sku
        $goodsSkuId = $item->mapping->goodsSku->id ?? '';
        if (!empty($goodsSkuId)) {
            $item->update(['goods_sku_id' => $goodsSkuId]);
        } else {
            //由热销产品库刊登的商品可以根据平台sku查询关联关系
            $goodsSkuId = GoodsSku::query()->where('sku_id', $item->sku)->value('id');
            if (!empty($goodsSkuId)) {
                $mappingData = [
                    'platform' => $platform,
                    'platform_variant_id' => $item->variant_id,
                    'goods_sku_id' => $goodsSkuId,
                ];
                OrderItemMapping::query()->create($mappingData);
            }
        }
    }

    protected function createOrUpdateFulfillments($order, $fulfillments)
    {
        $trackingNumberList = [];
        foreach ($fulfillments as $fulfillment) {
            if (empty($fulfillment['tracking_number'])) continue;
            $trackingNumberList[] = $fulfillment['tracking_number'];
            ShopOrderFulfillments::query()->updateOrCreate([
                'tracking_number' => $fulfillment['tracking_number'],
                'order_id' => $order->id,
            ], [
                'name' => $order->name,
                'status' => $fulfillment['status'] ?? '',
                'tracking_url' => $fulfillment['tracking_url'] ?? '',
                'fulfillment_shopify_id' => $fulfillment['fulfillment_shopify_id'] ?? '',
                'tracking_company' => $fulfillment['tracking_company'] ?? '',
                'fulfillment_at' => $fulfillment['fulfillment_at'] ? Carbon::parse($fulfillment['fulfillment_at'])->format('Y-m-d H:i:s') : ''
            ]);
        }
        ShopOrderFulfillments::query()->where('order_id', $order->id)->whereNotIn('tracking_number', $trackingNumberList)->delete();
    }

}
