<?php

namespace App\Services\Shopify;

use App\Lib\Code;
use App\Models\FulfillmentOrderModel;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\Order;
use App\Models\OrderItemMapping;
use App\Models\OrderLineItem;
use App\Models\OrderShippingAddress;
use App\Models\ShopModel;
use App\Models\ShopOrderLogs;
use App\Services\Admin\OrderService as PlatformOrder;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class OrderService
{

    public function pullPlatformOrders($customer_id)
    {
        $shops = ShopModel::where(['customer_id' => $customer_id, 'status' => ShopModel::STATUS_AUTH])->select('id', 'customer_id', 'shop_url', 'access_token')->get();

        $http = new \GuzzleHttp\Client;
        $shops->each(function ($shop) use ($http, $customer_id) {
            logger('shopify URL：' . $shop->shop_url);
            logger('shopify店铺token：' . $shop->access_token);
            try {
                $result = $http->request('GET', 'https://' . $shop->shop_url . '/admin/api/2023-10/orders.json?status=unshipped', [
                    'headers' => [
                        'X-Shopify-Access-Token' => $shop->access_token
                    ]
                ])->getBody()->getContents();
            } catch (\Exception $e) {
                logger('请求shopify报错：' . $e->getMessage());
                throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
            }

            $this->orders($result, $shop);
        });

        return true;
    }

    /**
     * 获取 shopify order
     * @return void
     */
    public function orders($data, $shop)
    {
        $data = json_decode($data, true);
        // info('拉取shopify平台订单返回的结果', $data);
        $orderService = new PlatformOrder(new Order);
        DB::beginTransaction();
        try {
            foreach ($data['orders'] as $item) {
                $orderData = Order::init($item, $shop);

                $orderRes = Order::where('order_id', $item['id'])->first();

                // 判断订单是否存在，已存在则更新，不存在则新增
                if (empty($orderRes)) {
                    $orderData['custom_order_id'] = generateOrderId($shop->customer_id ?? 0);
                    $orderRes      = Order::create($orderData);
                    $order_id = $orderRes->id;
                } else {
                    Order::where('id', $orderRes['id'])->update($orderData);
                    $order_id = $orderRes['id'];
                }

                foreach ($item['line_items'] as $val) {
                    $itemRes      = OrderLineItem::where('line_item_id', $val['id'])->first();
                    $lineItemData = OrderLineItem::init($order_id, $val);

                    $lineItemData['imgs'] = $this->getProductImg($lineItemData['product_id'], $shop);
                    if (empty($itemRes)) {
                        $itemRes = OrderLineItem::create($lineItemData);
                    } else {
                        OrderLineItem::where('id', $itemRes['id'])->update($lineItemData);
                    }
                }

                $orderService->declaration($orderRes->id);

                if (!empty($item['fulfillments'])) {
                    foreach ($item['fulfillments'] as $fulfillment) {
                        $fulfillment_order = FulfillmentOrderModel::where('fulfillment_order_id', $fulfillment['id'])->first();

                        if ($fulfillment_order) {
                            continue;
                        }
                        $data = [
                            'order_id'                       => $fulfillment['order_id'] ?? 0,
                            // 'request_status'                 => $fulfillment['request_status'],
                            'status'                         => $fulfillment['status'] ?? '',
                            'fulfillment_order_id'           => $fulfillment['id'] ?? 0,
                            'fulfillment_order_line_item_id' => $fulfillment['line_items'][0]['id'] ?? 0,
                            'created_at'                     => now(),
                            'updated_at'                     => now(),
                        ];

                        FulfillmentOrderModel::insert($data);
                    }
                }

                if (!empty($item['shipping_address'])) {
                    info('订单收件人地址', $item['shipping_address']);
                    $orderRes = OrderShippingAddress::where('order_id', $order_id)->first();

                    $addressData = OrderShippingAddress::init($item['shipping_address'], $order_id);
                    if (empty($orderRes)) {
                        OrderShippingAddress::create($addressData);
                    } else {
                        OrderShippingAddress::where('order_id', $order_id)->update($addressData);
                    }
                }

                ShopOrderLogs::addLog([
                    'order_id' => $order_id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
                    'content' => '系统自动同步并创建订单',
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            info("拉取shopify订单失败", [$e->getMessage(), $e->getFile(), $e->getLine()]);

            throw new AccidentException("拉取shopify订单失败", Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 获取商品图片
     * @param $product_id
     * @return array
     */
    public function getProductImg($product_id, $shop)
    {
        if (empty($product_id)) {
            return [];
        }
        $res = $this->request($shop, "/admin/api/2023-10/products/$product_id/images.json");

        return array_column($res['images'], 'src');
    }

    public function auth()
    {
        // TODO: Implement auth() method.
    }

    public function getOrderList($shopId = 0)
    {
        Artisan::call('shopify:order');
    }

    /**
     * 获取订单列表
     * @return void
     */
    public function getOrders()
    {
        $params = request()->all();
        $size   = 15;

        if (isset($params['size']) && !empty($params['size'])) {
            $size = $params['size'];
        }

        $model = Order::with(['lineItems', 'order', 'shop' => function ($query) {
            $query->with(['warehouse' => function ($q) {
                $q->select('id', 'warehouse_name');
            }])->select('id', 'warehouse_id');
        }]);

        // 根据仓库筛选
        if (isset($params['warehouse_id']) && !empty($params['warehouse_id'])) {
            $model = $model->whereHas('shop', function ($query) use ($params) {
                $query->where('warehouse_id', $params['warehouse_id']);
            });
        }

        if (isset($params['begin_date']) && isset($params['end_date']) && !empty($params['begin_date']) && !empty($params['end_date'])) {
            $model = $model->whereBetween('created_at', [$params['begin_date'] . ' 00:00:00', $params['end_date'] . ' 23:59:59']);
        }

        $result = $model->where('company_id', auth('index')->user()->company_id)->paginate($size)->toArray();

        $res = $result['data'];
        unset($result['data']);
        $meta     = $result;
        $sku_type = ['单sku单件', '单sku多件', '多sku多件'];
        $data     = [];
        foreach ($res as $item) {
            $line_items_count   = count($item['line_items']);
            $line_items_qty     = array_column($item['line_items'], 'quantity');
            $line_items_sku_sum = array_sum($line_items_qty);
            $index              = 0;
            if ($line_items_count === 1 && $line_items_sku_sum > 1) {
                $index = 1;
            } elseif ($line_items_count > 1) {
                $index = 2;
            }

            if (!isset($item['order'])) {
                $item['order']['order_sn'] = '';
            }
            $tmp = [
                'id'                 => $item['id'],
                'shop_id'            => $item['shop_id'],
                'order_sn'           => $item['order']['order_sn'],
                'reference_order_sn' => $item['order_id'],
                'warehouse_name'     => $item['shop']['warehouse']['warehouse_name'],
                'status'             => $item['status'],
                'sku_type_name'      => $sku_type[$index],
                'quantity'           => $line_items_sku_sum,
                'ordering_goods_fee' => $item['current_total_price'],
                'file_list'          => [],
                'created_at'         => $item['created_at'],
                'output_time'        => '',
                'platform_code'      => 'shopify',
                'remark'             => $item['note'],
            ];

            foreach ($item['line_items'] as $val) {
                $val_tmp = [
                    'img'        => '',
                    'goods_name' => $val['name'],
                    'sku'        => $val['sku'],
                    'quantity'   => $val['quantity'],
                    'stock_num'  => '',
                    'sale_price' => $val['price'],
                    'remark'     => ''
                ];

                $tmp['skus'][] = $val_tmp;
            }


            $data[] = $tmp;
        }

        return ['data' => $data, 'meta' => $meta];
    }

    /**
     * 订单审核
     * @return void
     */
    public function review($id)
    {
        $params          = request()->all();
        $address         = $params['address'];
        $address['name'] = $address['receiver_name'];

        unset($address['receiver_name']);

        if ($params['is_review']) {
            $status = Order::where('id', $id)->value('status');
            if ($status == 0) {
                $address['address1'] = $address['address'];
                Order::where('id', $id)->update([
                    'status'          => 1,
                    'billing_address' => json_encode($address)
                    // 'billing_address->city' => $address['city'],
                    // 'billing_address->name' => $address['name'],
                    // 'billing_address->phone' => $address['phone'],
                    // 'billing_address->country' => $address['country'],
                    // 'billing_address->address1' => $address['address'],
                    // 'billing_address->province' => $address['province'],
                ]);
            } else {
                throw new AccidentException('当前订单状态非待审核状态', Code::OPERATE_FAIL);
            }
        } else {
            $status = Order::where('id', $id)->value('status');
            if ($status == 0) {
                Order::where('id', $id)->update([
                    'status' => 5,
                ]);
            } else {
                throw new AccidentException('当前订单状态非待审核状态', Code::OPERATE_FAIL);
            }
        }
        return true;
    }

    /**
     * 请求平台url
     * @param $shop
     * @param $uri
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($shop, $uri)
    {
        info("请求shopify平台url", ['https://' . $shop->shop_url . $uri]);
        $http = new \GuzzleHttp\Client;
        try {
            $result = $http->request('GET', 'https://' . $shop->shop_url . $uri, [
                'headers' => [
                    'X-Shopify-Access-Token' => $shop->access_token
                ]
            ])->getBody()->getContents();

            $res = json_decode($result, true);
            info("请求shopify平台返回结果", $res);
            return $res;
        } catch (Exception $e) {
            info('操作失败', [$e->getMessage(), $uri]);
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    public function requestPost($shop, $uri, $data)
    {
        $http = new \GuzzleHttp\Client;
        try {
            $result = $http->request('POST', 'https://' . $shop->shop_url . $uri, [
                'headers' => [
                    'X-Shopify-Access-Token' => $shop->access_token
                ],
                'json'    => $data
            ])->getBody()->getContents();
            info("请求shopify平台返回结果", [$result]);
        } catch (Exception $e) {
            info('请求shopify平台返回结果-出现异常', [$e->getMessage(), $uri]);
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }


        return json_decode($result, true);
    }

    public function requestDel($shop, $uri, $data)
    {
        $http = new \GuzzleHttp\Client;
        try {
            $result = $http->request('DELETE', 'https://' . $shop->shop_url . $uri, [
                'headers' => [
                    'X-Shopify-Access-Token' => $shop->access_token
                ],
                'json'    => $data
            ])->getBody()->getContents();
        } catch (Exception $e) {
            throw new AccidentException('操作失败：' . $e->getMessage(), Code::OPERATE_FAIL);
        }


        return json_decode($result, true);
    }

    /**
     * 检索支持的履行订单
     * @return void
     */
    public function retrievesFulfillment($id)
    {
        $order = Order::with(['shop', 'logisticsApply'])->where('id', $id)->first(['id', 'order_id', 'shop_id']);

        $uri = '/admin/api/2023-10/orders/' . $order->order_id . '/fulfillment_orders.json';

        $res = $this->request($order->shop, $uri);

        if(!$res) {
            Order::where('id', $id)->update(['order_status' => Order::STATUS_DELIVERY_FAILURE]);
            return false;
        }

        $fulfillment_order_ids = [];
        $fulfillment_data      = [];
        foreach ($res['fulfillment_orders'] as $fulfillment_order) {
            // foreach($fulfillment_order['line_items'] as $line_item) {
            $fulfillment_data[]      = [
                'order_id'                       => $fulfillment_order['order_id'],
                'request_status'                 => $fulfillment_order['request_status'],
                'status'                         => $fulfillment_order['status'],
                'fulfillment_order_id'           => $fulfillment_order['line_items'][0]['fulfillment_order_id'],
                'fulfillment_order_line_item_id' => $fulfillment_order['line_items'][0]['id'],
                'created_at'                     => now(),
                'updated_at'                     => now(),
            ];
            $fulfillment_order_ids[] = [
                'fulfillment_order_id' => $fulfillment_order['line_items'][0]['fulfillment_order_id'],
            ];
            // }
        }

        try {
            FulfillmentOrderModel::insert($fulfillment_data);
            info("订单发货", $fulfillment_order_ids);
            $data = [
                'fulfillment' => [
                    'line_items_by_fulfillment_order' => $fulfillment_order_ids,
                    'tracking_info'                   => [
                        "number" => $order->logisticsApply->way_bill_number,
                        "url"    => "https://t.17track.net/en#nums=" . $order->logisticsApply->way_bill_number
                    ]
                ]];

            $fulfillmentRes = $this->createFulfillment($order->shop, $data);

            FulfillmentOrderModel::where('order_id', $order->order_id)->update(['fulfillment' => $fulfillmentRes['fulfillment']]);
            Order::where('id', $order->id)->update(['order_status' => Order::STATUS_DELIVERY_SUCCESS, 'deliver_time' => now()]);
        } catch (Exception $e) {
            logger('履行订单失败: ' . $e->getMessage());
            Order::where('id', $order->id)->update([
                'order_status'     => Order::STATUS_DELIVERY_FAILURE,
                'send_fail_reason' => $e->getMessage()
            ]);
        }

        return true;
    }

    /**
     * 创建履行订单
     * @return void
     */
    public function createFulfillment($shop, $data)
    {
        info('创建履行订单请求数据', $data);
        $uri = '/admin/api/2023-10/fulfillments.json';

        $res = $this->requestPost($shop, $uri, $data);
        info('创建履行订单', $res);
        return $res;
    }

    /**
     * 将履行订单标记为未完成
     * @param $shop
     * @param $order_id
     * @return mixed
     * @throws Exception
     */
    public function openFulfillment($shop, $order_id)
    {
        $uri = '/admin/api/2023-10/fulfillment_orders/' . $order_id . '/open.json';
        $res = $this->requestPost($shop, $uri, []);

        info('将履行订单标记为未完成', $res);
        return $res;
    }

    /**
     * 请求履单
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function requestFulfillment($id)
    {
        $order = Order::with(['shop', 'logisticsApply', ])->where('id', $id)->first(['id', 'order_id', 'shop_id']);
        $uri = '/admin/api/2023-10/orders/' . $order->order_id . '/fulfillment_orders.json';

        $res = $this->request($order->shop, $uri);
        info('可履约订单', [$res]);
        //https://mycms-test.myshopify.com/admin/api/2023-10/fulfillment_orders/5522893439204/fulfillment_request.json
        try {
            foreach ($res['fulfillment_orders'] as $fulfillment_order) {
                $uri = "/admin/api/2023-10/fulfillment_orders/{$fulfillment_order['id']}/fulfillment_request.json";
                $res = $this->requestPost($order->shop, $uri, []);
                info('履约请求', [$res]);
            }
        } catch (Exception $e) {
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
        $order->fulfillment_request_status = 1;
        $order->save();
        return $res;
    }

    /**
     * 重新打开已关闭的订单
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function openOrder($order)
    {
        $uri = '/admin/api/2023-10/orders/'.$order->order_id.'/open.json';

        $data = [];
        $this->requestPost($order->shop, $uri, $data);
    }

    /**
     * 更新履行的跟踪信息
     * @return void
     */
    public function updateTracking($id)
    {
        $order = Order::with(['shop', 'logisticsApplyChange', 'fulfillmentOrder'])->where('id', $id)->first();
        try {
            // 重新打开订单
            $this->openOrder($order);

            $uri = '/admin/api/2023-10/fulfillments/' . $order->fulfillmentOrder->fulfillment_order_id . '/update_tracking.json';

            $data = [
                "fulfillment" => [
                    "notify_customer" => true,
                    "tracking_info"   => [
                        "url"    => "https://t.17track.net/en#nums=" . $order->logisticsApplyChange->way_bill_number,
                        "number" => $order->logisticsApplyChange->way_bill_number
                    ]
                ]
            ];

            $res = $this->requestPost($order->shop, $uri, $data);
            info('更新履行的跟踪信息', $res);
            Order::where('id', $id)->update(['logistics_provider' => $order->change_logistics_provider, 'change_status' => Order::STATUS_DELIVERY_SUCCESS_CHANGE]);
        } catch (Exception $e) {
            info('更新失败', [$e->getMessage()]);
            Order::where('id', $id)->update(['change_status' => Order::STATUS_DELIVERY_FAILURE_CHANGE]);
        }

        return true;
    }
}
