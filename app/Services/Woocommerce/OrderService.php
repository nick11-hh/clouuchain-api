<?php

namespace App\Services\Woocommerce;

use App\Lib\Code;
use App\Models\Order;
use App\Models\OrderNotes;
use App\Models\ShopOrderLogs;
use App\Models\ExchangeRateModel;
use App\Models\ThirdPartyWarehouseConfig;
use App\Models\Package;
use App\Models\LogisticsApplyModel;
use App\Services\Admin\BaseService;
use Carbon\Carbon;
use App\Models\SystemConfig;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use App\Services\Base\PackageBaseService;

class OrderService extends BaseService
{
    public $filterRules = [
        'order_id,name' => ['like', 'search'],
        'customer_id'   => ['=', 'customer'],
        // 'created_at'    => ['between', ['after', 'before']],
        // 'updated_at'    => ['between', ['modified_after', 'modified_before']],
    ];

    protected $orderBy = ['id' => 'desc'];

    public function __construct(Order $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function filter()
    {

        $after = $this->formData['after']??'';
        if (!empty($after)) {
            $strtotime = strtotime($after);
            if($strtotime != false){

                $after = trim(str_replace(['T', 'Z'], ' ', $after));

                $this->query->where('created_at', '>', $after);
            }

        }

        $modifiedAfter = $this->formData['modified_after']??'';
        if (!empty($modifiedAfter)) {
            $strtotime = strtotime($modifiedAfter);
            if($strtotime != false){

                $modifiedAfter = trim(str_replace(['T', 'Z'], ' ', $modifiedAfter));

                $this->query->where('updated_at', '>', $modifiedAfter);
            }
        }

        $exclude = $this->formData['exclude']??'';
        if (!empty($exclude) && is_array($exclude)) {

            $this->query->whereNotIn('id', $exclude);
        }

        $include = $this->formData['include']??'';
        if (!empty($include) && is_array($include)) {
            
            $this->query->whereIn('id', $include);
        }

        $order = $this->formData['order']??'';
        if (!empty($order) && in_array($order, ['asc', 'desc'])) {
            
            $this->query->orderBy('id', $order);
        }


        // $status = $this->formData['status']??'';
        // if (!empty($status) && is_array($status)) {
            
        //     $this->query->whereIn('order_status', $status);
        // }

        // $product = $this->formData['product']??'';
        // if (!empty($product)) {
            
        // }

        return $this;
    }

    protected function getOrderStatus($status)
    {
        return match ($status) {
            // Order::STATUS_PENDING => 'pending',
            Order::STATUS_APPLY_NUM, Order::STATUS_PENDING, Order::STATUS_APPLY_NUM_SUCCESS, Order::STATUS_APPLY_NUM_FAILURE, Order::STATUS_WAIT_PRINT, Order::STATUS_WAIT_PRINT_IN_STOCK, Order::STATUS_WAIT_PRINT_OUT_STOCK, Order::STATUS_DELIVERY_FAILURE, Order::STATUS_STOCK_PENDING => 'processing',
            Order::STATUS_NOT_SHIPPING, Order::STATUS_SHELVE => 'on-hold',
            Order::STATUS_SHIPPED, Order::STATUS_DELIVERY_SUCCESS, Order::USER_CHECKED, Order::STATUS_ARCHIVE => 'completed',
            Order::STATUS_CANCELLED => 'cancelled',
            // Order::STATUS_PENDING => 'refunded',
            default => null,
        };
    }

    protected function makeData(Order $order, $host)
    {

        $createdTime = Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at);
        $createdGmtTime = $createdTime->setTimezone('UTC');

        $updatedTime = Carbon::createFromFormat('Y-m-d H:i:s', $order->updated_at);
        $updatedGmtTime = $updatedTime->setTimezone('UTC');

        $paymentedAt = $order->paymented_at;
        $paymentedGmtTime = null;
        if($paymentedAt){

            $paymentedTime = Carbon::createFromFormat('Y-m-d H:i:s', $paymentedAt);
            $paymentedGmtTime = $paymentedTime->setTimezone('UTC');
        }

        $commitedAt = $order->commited_at;
        $commitedGmtTime = null;
        if($commitedAt){

            $commitedTime = Carbon::createFromFormat('Y-m-d H:i:s', $commitedAt);
            $commitedGmtTime = $commitedTime->setTimezone('UTC');
        }
        
        $number = $order->order_id;
        if($order->name){
            $number .= '-' . $order->name;
        }

        if($order->shop){
            $number = $order->shop->shop_name . '-' . $number;
        }

        $data = [
            'id' => $order->id,
            'parent_id' => 0,
            'number' => $number,
            'order_key' => 'wc_order_' . $order->id,
            'created_via' => 'admin',
            'version' => '9.8.2',
            'status' => $this->getOrderStatus($order->order_status),
            'currency' => $order->currency,
            'currency_symbol' => $order->currency ? ExchangeRateModel::$currencyUnitTypeSymbols[$order->currency] : '$',
            'date_created' => $order->created_at->format('Y-m-d\TH:i:s'),
            'date_created_gmt' => $createdGmtTime->format('Y-m-d\TH:i:s'),//下单时间对不上
            'date_modified' => $order->updated_at->format('Y-m-d\TH:i:s'),
            'date_modified_gmt' => $updatedGmtTime->format('Y-m-d\TH:i:s'),
            'discount_total' => (float) $order->current_total_discounts,
            'discount_tax' => "0.00",
            'shipping_total' => (float) $order->subtotal_price,
            'shipping_tax' => (float) $order->current_total_tax,
            'cart_tax' => "0.00",
            'total' => (float) $order->subtotal_price,
            'total_tax' => (float) $order->current_total_tax,
            'prices_include_tax' => false,
            'customer_id' => $order->customer_id,
            'customer_ip_address' => '',
            'customer_user_agent' => '',
            'customer_note' => $order->remark,
            'billing' => [
                'first_name' => $order->custom?->invoiceAddress?->first_name,
                'last_name' => $order->custom?->invoiceAddress?->last_name,
                'company' => $order->custom?->invoiceAddress?->name,
                'address_1' => $order->custom?->invoiceAddress?->address_detail,
                'address_2' => '',
                'city' => $order->custom?->invoiceAddress?->city,
                'state' => $order->custom?->invoiceAddress?->province,
                'postcode' => $order->custom?->invoiceAddress?->postcode,
                'country' => $order->custom?->invoiceAddress?->country,
                'email' => $order->custom?->invoiceAddress?->email,
                'phone' => $order->shippingAddress?->phone,//$order->custom?->invoiceAddress?->phone_area_code ? '(' . $order->custom?->invoiceAddress?->phone_area_code . ')' . $order->custom?->invoiceAddress?->phone_number : $order->custom?->invoiceAddress?->phone_number,
            ],
            'shipping' => [
                'first_name' => $order->shippingAddress?->first_name,
                'last_name' => $order->shippingAddress?->last_name,
                'company' => $order->shippingAddress?->company,
                'address_1' => $order->shippingAddress?->address1,
                'address_2' => $order->shippingAddress?->address2,
                'city' => $order->shippingAddress?->city,
                'state' => $order->shippingAddress?->province,
                'postcode' => $order->shippingAddress?->zip,
                'country' => $order->shippingAddress?->country_code,
                'phone' => $order->shippingAddress?->phone,
            ],
            'payment_method' => '',
            'payment_method_title' => '',
            'transaction_id' => '',
            'date_paid' => $paymentedAt ? str_replace(' ', 'T', $paymentedAt) : '' ,
            'date_paid_gmt' => $paymentedAt ? $paymentedGmtTime->format('Y-m-d\TH:i:s') : '',
            'date_completed' => $commitedAt ? str_replace(' ', 'T', $commitedAt) : '',
            'date_completed_gmt' => $commitedAt ? $commitedGmtTime->format('Y-m-d\TH:i:s') : '',
            'cart_hash' => '',
            'meta_data' => [[
                'id' => 27,
                'key' => '_wc_order_attribution_source_type',
                'value' => 'admin',
            ]],
            'line_items' => [],
            'tax_lines' => [],
            'shipping_lines' => [],
            'fee_lines' => [],
            'coupon_lines' => [],
            'refunds' => [],
            '_links' => [
                'self' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $order->id 
                ]],
                'collection' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/orders'
                ]]
            ]

        ];

        if($order->lineItems){
            foreach ($order->lineItems as $key => $item) {

                if($item->mapping && $item->mapping->goodsSku){

                    $quotePrice = (float) $item->quote_price;

                    $lineArr = [
                        'id' => $item->id,
                        'name' => $item->mapping->goodsSku->goods?->goods_name . ' - ' . $item->mapping->goodsSku->spec_name,
                        'product_id' => $item->mapping->goodsSku->id,
                        'variation_id' => $item->mapping->goodsSku->id,
                        'quantity' => $item->quantity,
                        'tax_class' => '',
                        'subtotal' => (int) $item->quantity * $quotePrice,
                        'subtotal_tax' => '0.00',
                        'total' => (int) $item->quantity * $quotePrice,
                        'total_tax' => '0.00',
                        'taxes' => [],
                        'meta_data' => [],
                        'sku' => $item->mapping->goodsSku->sku_id,
                        'price' => $quotePrice,
                        'image' => [
                            'id' => $item->mapping->goodsSku->id,
                            'src' => $item->mapping->goodsSku->images ? $item->mapping->goodsSku->images[0] : '',
                        ],
                        'parent_name' => $item->mapping->goodsSku->goods?->goods_name,
                    ];

                    if($item->mapping->goodsSku->spec_info){
                        foreach ($item->mapping->goodsSku->spec_info as $spec) {
                            $lineArr['meta_data'][] = [
                                'id' => $item->mapping->goodsSku->id,
                                'key' => $spec['name'],
                                'value' => $spec['value'],
                                'display_key' => $spec['name'],
                                'display_value' => $spec['value'],
                            ];
                        }
                    }

                    $data['line_items'][] = $lineArr;
                }
            }
        }

        if($order->expressLine){
            $data['shipping_lines'][] = [
                'id' => $order->expressLine->id,
                'method_title' => $order->expressLine->cn_name,
                'method_id' => $order->expressLine->en_name,
            ];
        }

        return $data;
    }

    public function getOrderList()
    {

        $perPage = $this->formData['per_page']??100;
        $perPage = intval($perPage); 

        $this->setFilter()->filter()->setOrderBy();

        $statusArr = [
            Order::STATUS_PENDING,
            Order::STATUS_APPLY_NUM,
            Order::STATUS_SHIPPED,
            Order::STATUS_CANCELLED,
            Order::STATUS_SHELVE
        ];
        $data = $this->query->with(['custom.invoiceAddress', 'shippingAddress', 'lineItems.mapping.goodsSku.goods', 'expressLine', 'shop:id,shop_name'])
            // ->where('order_status', '>=', Order::STATUS_PENDING)
            ->whereIn('order_status', $statusArr)
            // ->where('order_status', '<', Order::STATUS_ARCHIVE)
            ->simplePaginate($perPage);
        if($data->isEmpty()){

            return response()->json([]);
        }

        $newData = $logArr = [];
        $host = request()->getHost();
        foreach ($data as $key => $order) {

            $newData[] = $this->makeData($order, $host);

            if($order->fulfillment_platform != ThirdPartyWarehouseConfig::PLATFORM_DIANXIAOMI){

                $order->fulfillment_platform = ThirdPartyWarehouseConfig::PLATFORM_DIANXIAOMI;
                $order->save();

                $logArr[] = [
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_DIANXIAOMI_PULL_ORDER,
                    'content' => '店小秘拉单，更新仓配信息为：' . ThirdPartyWarehouseConfig::WAREHOUSE_PLATFORM_LIST[ThirdPartyWarehouseConfig::PLATFORM_DIANXIAOMI],
                    'created_at' => now()
                ];
            }
        }

        if($logArr){
            #新增操作日志
            ShopOrderLogs::insert($logArr);
        }

        return response()->json($newData);
    }


    public function getInfoById($id)
    {
        $order = $this->model::with(['custom.invoiceAddress', 'shippingAddress', 'lineItems.mapping.goodsSku.goods', 'expressLine', 'shop:id,shop_name'])->find($id);
        if(!$order){
            return response()->json([
                'code' => "woocommerce_rest_shop_order_invalid_id",
                'message' => "无效的ID。",
                'data' => [
                    'status' => 404
                ]
            ]);
        }

        $host = request()->getHost();
        $newData = $this->makeData($order, $host);

        return response()->json($newData);
    }

    public function updateOrder($id)
    {

        $allowStatus =  ['completed'];

        if(!isset($this->formData['status']) || empty($this->formData['status'])){

            return response()->json([
                'code' => 'woocommerce_rest_shop_order_invalid_status',
                'message' => "The status field is required.",
                'data' => [
                    'status' => 400
                ]
            ]);
        }

        if(! in_array($this->formData['status'], $allowStatus)){

            return response()->json([
                'code' => 'woocommerce_rest_shop_order_invalid_status',
                'message' => "The status not allowed",
                'data' => [
                    'status' => 400
                ]
            ]);
        }

        $order = $this->model::with(['custom.invoiceAddress', 'shippingAddress', 'lineItems'])->find($id);
        if(!$order){
            return response()->json([
                'code' => 'woocommerce_rest_shop_order_invalid_id',
                'message' => "无效的ID。",
                'data' => [
                    'status' => 404
                ]
            ]);
        }

        //这里修改订单状态为：已发货
        $this->model::where('id', $order->id)->update(['order_status' => Order::STATUS_SHIPPED]);

        #新增操作日志
        ShopOrderLogs::addLog([
            'order_id'      => $order->id,
            'operator_type' => ShopOrderLogs::OPERATOR_TYPE_DIANXIAOMI_SHIPPED,
            'content'       => '店小秘设置发货',
        ]);

        $order->refresh();

        $host = request()->getHost();
        $newData = $this->makeData($order, $host);

        return response()->json($newData);
    }


    public function addNotes($id)
    {

        if(!isset($this->formData['note']) || empty($this->formData['note'])){

            return response()->json([
                'code' => 'woocommerce_rest_shop_order_invalid_note',
                'message' => "The note field is required.",
                'data' => [
                    'status' => 400
                ]
            ]);
        }

        // if(strlen($this->formData['note']) > 500){

        //     return response()->json([
        //         'code' => 'woocommerce_rest_shop_order_invalid_note',
        //         'message' => "The note must not be greater than 100 characters.",
        //         'data' => [
        //             'status' => 400
        //         ]
        //     ]);
        // }

        $order = $this->model::with(['custom.invoiceAddress', 'shippingAddress', 'lineItems', 'packages'])->find($id);
        if(!$order){
            return response()->json([
                'code' => 'woocommerce_rest_shop_order_invalid_id',
                'message' => "无效的ID。",
                'data' => [
                    'status' => 404
                ]
            ]);
        }

        //这里新增订单备注
        $orderNote = OrderNotes::create([
            'shop_order_id' => $id,
            'notes' => $this->formData['note'],
            'added_by_user' => $this->formData['added_by_user']??false,
            'customer_note' => $this->formData['customer_note']??false,
        ]);

        preg_match('/href="([^"]*?)"/', $this->formData['note'], $matches);

        $trackingNumber = '';
        if (isset($matches[1])) {
            $url = $matches[1];
    
            // 从 URL 中提取 #nums= 后面的部分
            $trackingNumber = '';
            if (preg_match("/#nums=(.+)$/", $url, $numMatches)) {
                $trackingNumber = isset($numMatches[1]) ? $numMatches[1] : '';
            }
        }

        if(!empty($trackingNumber) && $order->packages->isNotEmpty()){

        
            // @todo 后续需要实现拆单合单导入
            // 马帮发货暂时不考虑拆包合包，默认一个包裹
            $package = $order->packages[0];
            $logisticsApply = LogisticsApplyModel::query()->where('package_id', $package->id)->first();
            if (empty($logisticsApply)) {
                $logisticsApply = new LogisticsApplyModel();
                $logisticsApply->package_id = $package->id;
                $logisticsApply->remark = '';
            }

            if ($logisticsApply->way_bill_number != $trackingNumber) {

                $logisticsApply->way_bill_number = $trackingNumber;
                $logisticsApply->save();

                ShopOrderLogs::addLog([
                    'order_id'      => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_DIANXIAOMI_SHIPPED,
                    'content'       => '运单号申请成功，店小秘系统已生成运单号，自动同步运单号: ' . $logisticsApply->way_bill_number,
                    'operator_id'   => 0
                ]);

                // 通知平台订单发货
                $sync_waybill_number = SystemConfigBaseService::getConfigValue(SystemConfig::SYNC_WAYBILL_NUMBER);
                if($sync_waybill_number == 1) {
                    (new PackageBaseService($package))->packagePlatformDelivery();
                }
            }

            
            // 更新运单状态
            $order->logistics_status = Package::LOGISTICS_APPLY_SUCCESS;
            $order->save();
            $package->logistics_status = Package::LOGISTICS_APPLY_SUCCESS;
            $package->save();

        }

        $host = request()->getHost();

        $createdTime = Carbon::createFromFormat('Y-m-d H:i:s', $orderNote->created_at);
        $createdGmtTime = $createdTime->setTimezone('UTC');

        return response()->json([
            'id' => $orderNote->id,//备注信息id
            'author' => 'system',
            'date_created' => $orderNote->created_at->format('Y-m-d\TH:i:s'),
            'date_created_gmt' => $createdGmtTime->format('Y-m-d\TH:i:s'),
            'note' => $orderNote->notes,
            'customer_note' => $orderNote->customer_note,
            '_links' => [
                'self' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $order->id . '/NOTES/' . $orderNote->id
                ]],
                'collection' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $order->id . '/NOTES'
                ]],
                'up' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $order->id
                ]]
            ]
        ]);
    }


    /** 
     * 备注列表
     */
    public function notesList($orderId)
    {
        
        $perPage = $this->formData['per_page']??100;

        $data = OrderNotes::query()->where('shop_order_id', $orderId)->orderByDesc('id')->simplePaginate($perPage);
        if($data->isEmpty()){

            return response()->json([]);
        }

        $newData = [];
        $host = request()->getHost();
        foreach ($data as $key => $note) {

            $createdTime = Carbon::createFromFormat('Y-m-d H:i:s', $note->created_at);
            $createdGmtTime = $createdTime->setTimezone('UTC');

            $_data = [
                'id' => $note->id,
                'author' => 'system',
                'date_created' => $note->created_at->format('Y-m-d\TH:i:s'),
                'date_created_gmt' => $createdGmtTime->format('Y-m-d\TH:i:s'),
                'note' => $note->notes,
                'customer_note' => (bool) $note->customer_note,
                '_links' => [
                    'self' => [[
                        'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $orderId . '/NOTES/' . $note->id
                    ]],
                    'collection' => [[
                        'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $orderId . '/NOTES'
                    ]],
                    'up' => [[
                        'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $orderId
                    ]]
                ]
            ];

            $newData[] = $_data;
        }

        return response()->json($newData);
    }

    /** 
     * 备注详情
     */
    public function notesInfo($orderId, $notesId)
    {
        
        $orderNote = OrderNotes::query()->where('id', $notesId)->where('shop_order_id', $orderId)->first();
        if(!$orderNote){
            return response()->json([
                'code' => 'woocommerce_rest_shop_order_invalid_id',
                'message' => "无效的ID。",
                'data' => [
                    'status' => 404
                ]
            ]);
        }

        $host = request()->getHost();

        $createdTime = Carbon::createFromFormat('Y-m-d H:i:s', $orderNote->created_at);
        $createdGmtTime = $createdTime->setTimezone('UTC');

        $data = [
            'id' => $orderNote->id,
            'author' => 'system',
            'date_created' => $orderNote->created_at->format('Y-m-d\TH:i:s'),
            'date_created_gmt' => $createdGmtTime->format('Y-m-d\TH:i:s'),
            'note' => $orderNote->notes,
            'customer_note' => (bool) $orderNote->customer_note,
            '_links' => [
                'self' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $orderId . '/NOTES/' . $orderNote->id
                ]],
                'collection' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $orderId . '/NOTES'
                ]],
                'up' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/orders/' . $orderId
                ]]
            ]
        ];

        return response()->json($data);
    }
}