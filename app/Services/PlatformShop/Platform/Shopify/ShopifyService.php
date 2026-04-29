<?php

namespace App\Services\PlatformShop\Platform\Shopify;

use App\Lib\Code;
use App\Lib\Platform;
use App\Models\ClientGoods;
use App\Models\ClientGoodsPublishLog;
use App\Models\ExchangeRateModel;
use App\Models\ExpressOrderModel;
use App\Models\FulfillmentOrderModel;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\PlatformProduct;
use App\Models\ShopifyFulfillment;
use App\Models\ShopModel;
use App\Models\ShopOrderAbnormal;
use App\Models\ShopPlatformConfig;
use App\Models\ShopSetting;
use App\Services\Base\OrderBaseService;
use App\Services\PlatformShop\DataService\OrderDataService;
use App\Services\PlatformShop\DataService\ProductDataService;
use App\Services\PlatformShop\DataService\ShopDataService;
use App\Services\PlatformShop\Exceptions\PlatformShopException;
use App\Services\PlatformShop\PlatformShopAbstract;
use App\Services\PlatformShop\PlatformShopInterface;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\AccidentException;

class ShopifyService extends PlatformShopAbstract implements PlatformShopInterface
{


    protected string $platform = Platform::SHOPIFY;

    protected RequestApi $request;
    protected GraphQLApi $graphQLApi;
    protected ShopDataService $shopDataService;

    public function __construct($shop)
    {
        parent::__construct($shop);

        $this->request = new RequestApi($shop);
        $this->graphQLApi = new GraphQLApi($shop);
        $this->shopDataService = new ShopDataService($this->platform);
    }

    public function getAuthUrl($params)
    {
        // TODO: Implement getAuthUrl() method.
    }

    public function authorize($params)
    {
        // TODO: Implement authorize() method.
    }

    /** 同步产品列表
     * @return void
     * @throws Exception
     */
    public function syncProductList()
    {
        $count = 1;
        $productIds = [];
        $cursor = '';
        do {
            if ($count === 1) {
                $list = $this->graphQLApi->getProductList([]);
            } else {
                $list = $this->graphQLApi->getProductList([
                    'after' => $cursor
                ]);
            }
            $cursor = $list['cursor'] ?? '';
            if (empty($list['products'])) {
                break;
            }
            info('product list', $list);
            foreach ($list['products'] as $product) {
                $this->saveProduct($product);
            }
            $productIds = array_merge(array_column($list['products'], 'id'), $productIds);
            $count ++;
        } while (!empty($cursor) && $count < 5);
        $count = 1;
        $productIds = [];
        $cursor = '';
        do {
            if ($count === 1) {
                $list = $this->graphQLApi->getProductList([]);
            } else {
                $list = $this->graphQLApi->getProductList([
                    'after' => $cursor
                ]);
            }
            $cursor = $list['cursor'] ?? '';
            if (empty($list['products'])) {
                break;
            }
            info('product list', $list);
            foreach ($list['products'] as $product) {
                $this->saveProduct($product);
            }
            $productIds = array_merge(array_column($list['products'], 'id'), $productIds);
            $count ++;
        } while (!empty($cursor) && $count < 5);
        // 删除已经不存在的产品
        PlatformProduct::query()
            ->where('shop_id', $this->shop->id)
            ->whereNotIn('product_id', $productIds)
            ->delete();
    }

    /** 同步产品列表
     * @return void
     * @throws Exception
     */
    /*public function syncProductListOld()
    {
        $count = 1;
        $paginate = [];
        $existsList = [];

        do {
            $params = [
                'limit'  => '50',//返回数量
            ];
            if ($count > 1) {
                $query = parse_url($paginate['next'],  PHP_URL_QUERY);
                parse_str($query, $params);
            }

            $data = $this->request->getProductList($params);
            $list = $data['products'];
            $paginate = $data['paginate'];

//            info("同步产品数据", $list);
            foreach ($list as $product) {
                $this->saveProduct($product);
            }

            $existsList = array_merge($list, $existsList);

            $count++;
        } while (!empty($paginate['next']));


        $productIds = array_column($existsList, 'id');
        // 删除已经不存在的产品
        PlatformProduct::query()
            ->where('shop_id', $this->shop->id)
            ->whereNotIn('product_id', $productIds)
            ->delete();
    }*/

    /**
     * 同步产品详情
     * @param $productId
     * @return void
     * @throws Exception
     */
    public function syncProductDetail($productId)
    {
        $productDetail = $this->graphQLApi->getProductDetail($productId);
        $this->saveProduct($productDetail);
    }

    /**
     * 产品刊登
     * @param $clientGoods
     * @param $params
     * @return true
     * @throws Exception
     */
    /*    public function productPublishOld($clientGoods, $params)
        {
            // 转换数据格式
            $product = $this->transformClientGoods($clientGoods);
            $data['product'] = $product;
            // 刊登
            $result = $this->request->publishProduct($data);
            if (!empty($result['error'])) {
                throw new AccidentException('刊登失败' . $result['error']['title'] ?? '未知原因', Code::OPERATE_FAIL);
            }
            $platformProduct = $result['product'];

        // 上传变种图片和库存
        $variant = collect($platformProduct['variants'])->keyBy('sku');
        $uploadImages = [];
        foreach ($clientGoods->skus as $sku) {
            if (empty($variant[$sku->sku_id])) continue;
            if (!empty($sku->images[0])) {
                // 合并相同图片的变种id
                if (isset($uploadImages[$sku['images'][0]])) {
                    $uploadImages[$sku->images[0]][] = $variant[$sku->sku_id]['id'];
                } else {
                    $uploadImages[$sku->images[0]] = [$variant[$sku->sku_id]['id']];
                }
            }
            // 追踪库存
            if (!$sku->quantity) continue;
            $this->request->setInventoryItems($variant[$sku->sku_id]['inventory_item_id'], ['tracked' => true]);
            $this->setInventoryQuantity($variant[$sku->sku_id]['inventory_item_id'], $sku->quantity);
        }
        //上传变种图片
        foreach ($uploadImages as $src => $variantIds) {
            $this->request->uploadProductImages($platformProduct['id'], [
                'variant_ids' => $variantIds,
                'src' => $src
            ]);
        }
        // 更新推送状态
        $clientGoods->status = ClientGoods::STATUS_PUBLISHED;
        $clientGoods->save();
        return $platformProduct;
    }*/

    /**
     * 产品刊登
     * @param $clientGoods
     * @param $params
     * @return mixed
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/18 15:11
     */
    public function productPublish($clientGoods, $params = [])
    {
        // 转换数据格式
        $product = $this->transformClientGoodsToGraphQL($clientGoods);
        $images = $this->clientGoodsImagesToGraphQL($clientGoods);
        // 刊登
        $productRes = $this->graphQLApi->publishProduct($product, $images);

        if (empty($productRes['id']))  throw new AccidentException('刊登失败', Code::OPERATE_FAIL);
        // 添加变种
        if (!empty($clientGoods->options)) {
            $variants = $this->clientGoodsVariantsToGraphQL($clientGoods);
            $uploadVariants = [];
            foreach ($variants as $variant) {
                // 上传变种图片
                $imagesRes = $this->graphQLApi->uploadProductImages($productRes['id'], $variant['media']);
                foreach ($variant['variants'] as &$item) {
                    $item['mediaId'] = $imagesRes['id'] ?? 0;
                    $uploadVariants[] = $item;
                }
            }
            // 上传变种信息
            $this->graphQLApi->addProductVariants($productRes['id'], $uploadVariants);
        }
        $productRes['id'] = last(explode('/', $productRes['id']));
        return $productRes;
    }

    /**
     * 同步订单列表
     */
    public function syncOrderList($filterParams = [])
    {
        $page = 1;
        $paginate = [];
        $count = $addOrderCount = 0;

        $syncType = $filterParams['sync_type'] ?? Order::SYNC_TYPE_AUTOMATIC;

        //手动拉单计算相差天数来循环, 最大拉取天数为60天
        if ($syncType == Order::SYNC_TYPE_MANUAL) {
            // $times = $filterParams['diff_days'] ?? Order::MAX_PULL_ORDER_RANGE_DAYS;


            //根据平台编号同步订单
            $name = $filterParams['name'] ?? [];
            if (!empty($name)) {
                return $this->syncOrderByField('name', $name);
            }
        }

        do {
            if ($page === 1) {
                if ($syncType == Order::SYNC_TYPE_MANUAL) {
                    //手动拉取订单查询指定日期范围内的订单
                    $params = [
                        'limit'                 => '250',//返回数量
                        'status'                => $filterParams['platform_order_status'], //订单状态
                        'financial_status'      => $filterParams['platform_payment_status'], //付款状态
                        'fulfillment_status'    => $filterParams['platform_fulfillment_status'], //发货状态
                        'ids'                   => !empty($filterParams['order_ids'] ?? []) ? implode(',', $filterParams['order_ids']) : '', //查询指定的订单号
                        'created_at_min'        => date('c', strtotime($filterParams['begin_date'] . ' 00:00:00')), //显示最后更新日期或之后的订单 ISO 8601格式
                        'created_at_max'        => date('c', strtotime($filterParams['end_date'] . ' 23:59:59')), //显示最后更新日期或之前的订单 ISO 8601格式
                    ];
                } else {
                    $params = [
                        'status'            => 'any',
                        'limit'             => '250',//返回数量
                        'updated_at_min'    => date('c', strtotime('-1 month')),//一个月内更新过的订单 ISO 8601格式
                    ];
                }

            } else {
                $params = [];
                $query = parse_url($paginate['next'], PHP_URL_QUERY);
                parse_str($query, $params);
            }

            $orderRes = $this->request->getOrderList($params);

            $orderList = $orderRes['data'];
            $paginate = $orderRes['paginate'];
//             info('shopify orders', $orderList['orders']);
            foreach ($orderList['orders'] as $order) {
                //记录查询到的订单数量
                $count++;

                // 列表返回了所有状态的单 这里过滤掉未付款的单
                try {
                    $res = $this->saveOrder($order, syncType: $syncType);

                    //记录新增订单的数量
                    if ($res) $addOrderCount++;
                } catch (\Exception $e) {
                    info('shopify-syncOrderList-saveOrder-error', [
                        'orderId' => $order['id'] ?? 0,
                        'line'    => $e->getLine(),
                        'file'    => $e->getFile(),
                        'msg'     => $e->getMessage(),
                    ]);
                }
            }
            $page ++;
        } while (!empty($paginate['next']) || $page > 50); //最大拉取50*250单

        info('同步成功数量', ['count' => $count, 'add_order_count' => $addOrderCount]);

        return [
            'count' => $count,
            'add_order_count' => $addOrderCount,
        ];
    }

    /**
     * @desc 根据指定字段同步订单
     * @param $field
     * @param $data
     */
    public function syncOrderByField($field, $data)
    {
        info('syncOrderByField', [$field, $data]);
        $count = $addOrderCount = 0;


        $successCount = 0;
        foreach ($data as $value) {
            $params = [
                'status' => 'any',
                $field => $value
            ];
            $orderRes = $this->request->getOrderList($params);

            info('syncOrderByField $orderRes', [$orderRes]);

            $orderList = $orderRes['data'];

            //记录查询到的订单数量
            $count += count($orderList['orders']);
            foreach ($orderList['orders'] as $order) {
                try {
                    $res = $this->saveOrder($order, syncType: Order::SYNC_TYPE_MANUAL);

                    //新增的订单计算成功数量
                    if ($res) $addOrderCount++;
                } catch (\Exception $e) {
                    info('shopify-syncOrderList-saveOrder-error', [
                        'orderId' => $order['id'] ?? 0,
                        'line'    => $e->getLine(),
                        'file'    => $e->getFile(),
                        'msg'     => $e->getMessage(),
                    ]);
                }
            }
        }

        info('同步成功数量', ['count' => $count, 'add_order_count' => $addOrderCount]);

        return [
            'count' => $count,
            'add_order_count' => $addOrderCount,
        ];
    }

    /** 同步订单详情
     * @param $orderId
     * @return void
     */
    public function syncOrderDetail($orderId)
    {
        $orderDetail = $this->request->getOrderDetail($orderId);
        $this->saveOrder($orderDetail);
    }


    public function orderShipment($shopOrder, $params = [])
    {
    }


    /** 包裹交运
     * @param $package
     * @param $order
     * @param array $params
     * @return false
     * @throws AccidentException
     * @throws PlatformShopException
     */
    public function packageShipment($package, $order, $params = [])
    {
        $trackNumber = $params['tracking_number'] ?? '';
        if (empty($trackNumber)) return false;

        $notifyEmail = $params['notify_email'] ?? 0;

        $updateNum = 0;
        $createNum = 0;
        $fulfillments = $this->request->getOrderFulfillments($order->platform_order_id);
        foreach ($fulfillments as $fulfillment) {
            // 通过包裹id和订单id找到之前交运的履约项，修改运单号
            $shopifyFulfillments = ShopifyFulfillment::query()->where('package_id', $package->id)
                ->where('order_id', $order->id)
                ->where('fulfillment_id', $fulfillment['id'])
                ->first();
            if (!empty($shopifyFulfillments)) {
                // 修改之前履约项的运单
                $data = [
                    "fulfillment" => [
                        'tracking_info' => [
                            'notify_customer' => $notifyEmail,
                            "number" => $trackNumber,
                            "url"    => "https://t.17track.net/en#nums=" . $trackNumber
                        ],
                    ]
                ];
                $this->request->updateFulfillmentTracking($fulfillment['id'], $data);
                $updateNum ++;
            } else {
                // 取消之前的履约项
                $this->request->cancelFulfillment($fulfillment['id']);
            }
        }

        // 查找所有的履约订单，为未履约的商品创建履约项
        $fulfillmentOrders = $this->request->getFulfillmentOrders($order->platform_order_id);
        foreach ($fulfillmentOrders['fulfillment_orders'] as $fulfillmentOrder) {
            if ($fulfillmentOrder['status'] === 'closed') continue;
            // hold搁置的履约订单取消搁置
            if ($fulfillmentOrder['status'] === 'on_hold') {
                $this->request->releaseHoldFulfillmentOrder($fulfillmentOrder['id']);
            }
            $fulfillmentItem = [
                'fulfillment_order_id'         => $fulfillmentOrder['id'],
                'fulfillment_order_line_items' => []
            ];
            foreach ($fulfillmentOrder['line_items'] as $item) {
                if ($item['fulfillable_quantity'] === 0) continue;
                foreach ($package->items as $packageItem) {
                    if ($packageItem->lineItem->line_item_id == $item['line_item_id']) { // 商品同时存在履约单和当前交运包裹中
                        $fulfillmentItem['fulfillment_order_line_items'][] = [
                            'id'       => $item['id'],
                            'quantity' => min($packageItem->quantity, $item['fulfillable_quantity']) // 包裹商品数量和履约单的商品数量取小
                        ];
                    }
                }
            }
            // 没有可以发货的商品
            if (empty($fulfillmentItem['fulfillment_order_line_items'])) {
                continue;
            }

            // 创建履约项
            $data = [
                'fulfillment' => [
                    'notify_customer'                 => $notifyEmail,
                    'line_items_by_fulfillment_order' => [$fulfillmentItem],
                    'tracking_info'                   => [
                        "number" => $trackNumber,
                        "url"    => "https://t.17track.net/en#nums=" . $trackNumber
                    ],
                ]
            ];
            $fulfillment = $this->request->createFulfillment($data);
            // 保存履约项到本地
            ShopifyFulfillment::query()->create([
                'package_id' => $package->id,
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment['fulfillment']['id']
            ]);
            $createNum ++ ;
        }

        // 既没有更新运单，也没有可以履约的商品
        if ($updateNum == 0 && $createNum == 0) {
            throw new AccidentException( "订单{$order->order_id}/{$order->name}没有可以交运的商品");
        }
    }

    public function requestFulfillment($order)
    {
        $fulfillmentOrders = $this->request->getFulfillmentOrders($order->platform_order_id);
        foreach ($fulfillmentOrders['fulfillment_orders'] as $fulfillment_order) {
            $res = $this->request->fulfillmentRequest($fulfillment_order['id']);
            info('履约请求', [$res]);
        }
        $order->fulfillment_request_status = 1;
        $order->save();
        return true;
    }


    public function setSkuInventory($clientGoods, $productVariants)
    {
        //sku库存
        $skuQuantity = $clientGoods->skus->pluck('quantity', 'sku_id')->toArray();

        foreach ($productVariants as $variant) {
            $sku = $variant['barcode'] ?? '';
            $quantity = $skuQuantity[$sku] ?? 0;

            $gid = $variant['inventoryItem']['id'] ?? ''; // gid://shopify/InventoryItem/48223320932580
            $inventoryItemId = filter_var($gid, FILTER_SANITIZE_NUMBER_INT); // 48223320932580

            if (!empty($inventoryItemId) && $quantity > 0) {
                $this->request->setInventoryItems($inventoryItemId, ['tracked' => true]);
                $this->setInventoryQuantity($inventoryItemId, $quantity);
            }
        }

    }




    /*******************************************protected 公共方法 ******************************************/

    protected function saveProduct($productDetail)
    {
        $productData = $this->transformProduct($productDetail);
        $productDataService = new ProductDataService();
        $productDataService->saveProduct($this->shop, $productData);
    }

    protected function transformProduct($productDetail)
    {

        $skuList = [];
        foreach ($productDetail['variants'] as $sku) {
            $skuList[] = [
                'platform_sku_id' => $sku['id'],
                'sku' => $sku['sku'],
                'barcode' => $sku['barcode'],
                'title' => $sku['title'],
                'option' => $sku['option1'] ?? '',
                'price' => $sku['price'] ?? 0,
                'inventory_quantity' => 0,
                'images' => $sku['images'] ?? '',
            ];
        }
        return [
            'product_id' => $productDetail['id'],
            'product_name' => $productDetail['title'],
            'tags' => $productDetail['tags'],
            'status' => $productDetail['status'],
            'options' => $productDetail['options'],
            'images' => array_column($productDetail['images'], 'src'),
            'detail' => $productDetail['body_html'],
            'published_at' => Carbon::parse($productDetail['created_at'])->toDateTimeString(),
            'skus' => $skuList
        ];
    }


    /**
     * @param $clientGoods
     * @return array
     */
    protected function transformClientGoods($clientGoods)
    {
        $options = [];
        foreach ($clientGoods->options as $option) {
            $specs = array_column($option['specs'], 'name');
            $options[] = [
                'name' => $option['name'],
                'values' => $specs
            ];
        }
        $variants = [];
        foreach ($clientGoods->skus as $sku) {
            $skuData = [
                'sku' => $sku->sku_id,
                'price' => $sku->sale_price,
                'compare_at_price' => $sku->original_price,
                'cost' => $sku->cost_price,
//                 'inventory_quantity' => $sku->quantity ?? 0,
                'image_id' => null
            ];
            foreach ($sku->spec_info as $key => $skuSpec) {
                $skuData['option' . ($key + 1)] = $skuSpec['value'];
            }
            $variants[] = $skuData;
        }
        $images = array_map(function ($item) {
            return ['src' => $item];
        }, $clientGoods->main_images);
        return [
            'title' => $clientGoods->goods_name,
            'body_html' => $clientGoods->detail,
            'vendor' => 'DropShipping',
            'product_type' => $clientGoods->category_name,
            'status' => 'active',
            'images' => $images,
            'options' => $options,
            'variants' => $variants,
        ];
    }

    /**
     * 转换店铺产品数据格式(GraphQL格式)
     * @param $clientGoods
     * @return array
     */
    protected function transformClientGoodsToGraphQL($clientGoods)
    {
        $options = [];
        foreach ($clientGoods->options as $option) {
            $specs =  [];
            foreach ($option['specs'] as $spec) {
                if (empty($spec['name'])) continue;
                $specs[] = ["name" => $spec['name']];
            }
            $options[] = [
                'name' => $option['name'],
                'values' => $specs
            ];
        }
        return [
            'title' => $clientGoods->goods_name,
            'descriptionHtml' => $clientGoods->detail,
            'vendor' => 'DropShipping',
            'productType' => $clientGoods->category_name,
            'status' => 'ACTIVE',
            'productOptions' => $options,
        ];
    }

    /**
     * 转换店铺产品图片格式(GraphQL格式)
     * @param $clientGoods
     * @return array|array[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/18 15:18
     */
    public function clientGoodsImagesToGraphQL($clientGoods)
    {
        return array_map(function ($item) {
            return [
                'mediaContentType' => 'IMAGE',
                'originalSource' => $item
            ];
        }, $clientGoods->main_images);
    }

    /**
     * 转换店铺产品变体数据(GraphQL))
     * @param $clientGoods
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/18 15:19
     */
    public function clientGoodsVariantsToGraphQL($clientGoods)
    {
        $locationInfo = $this->getLocationInfo();
        $locationId = empty($locationInfo) ? 0 : $locationInfo['admin_graphql_api_id'];
        $variants = [];
        $optionsQty = count($clientGoods->options);
        foreach ($clientGoods->skus as $sku) {
            $specInfo = [];
            foreach ($sku->spec_info as $spec) {
                $specInfo[] = [
                    'name' => $spec['value'],
                    'optionName' => $spec['name']
                ];
            }

            $image = $sku->images[0] ?? '';
            if($optionsQty == 1){
                $image = $clientGoods->cover_image ?? '';
            }

            if(empty($image) || empty($sku->sku_id)){
                continue;
            }

            $skuData = [
                'barcode' => $sku->sku_id,
                'price' => $sku->sale_price,
                'compareAtPrice' => $sku->original_price,
                'inventoryItem' => [
                    'cost' => $sku->cost_price,
                    'sku' => $sku->sku_id
                ],
                'inventoryQuantities' => [
                    'availableQuantity' => $sku->quantity,
                    'locationId' => $locationId
                ],
                'optionValues' => $specInfo,
            ];

            if (empty($variants[$image])) {
                $variants[$image] = [
                    'media' => [[
                        'mediaContentType' => 'IMAGE',
                        'originalSource' => $image
                    ]],
                    'variants' => [$skuData]
                ];
            } else {
                $variants[$image]['variants'][] = $skuData;
            }
        }
        return array_values($variants);
    }

    /**
     * @param $clientGoods
     * @param $status
     * @param $info
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    protected function addPublishLog($clientGoods, $status, $info, $data = [])
    {
        $log = [
            'goods_id' => $clientGoods->id,
            'platform' => 'shopify',
            'info' => $info,
            'status' => $status,
            'ext' => $data
        ];
        return ClientGoodsPublishLog::query()->create($log);
    }

    /**
     * @param $inventoryItemId
     * @param $quantity
     * @return void
     * @throws Exception
     */
    protected function setInventoryQuantity($inventoryItemId, $quantity)
    {
        $locationList = $this->request->getInventoryLocations();
        $locationInfo = null;
        $applicationName = ShopPlatformConfig::getPlatformApplicationName('shopify');
        foreach ($locationList['locations'] as $location) {
            if ($location['name'] === $applicationName) {
                $locationInfo = $location;
                break;
            }
        }
        if (empty($locationInfo)) return;
        $this->request->connectInventory($locationInfo['id'], $inventoryItemId);
        $this->request->setInventoryLevelAdjust($locationInfo['id'], $inventoryItemId, $quantity);
    }


    protected function getLocationInfo()
    {

        $locationList = $this->request->getInventoryLocations();
        $locationInfo = null;
        $applicationName = ShopPlatformConfig::getPlatformApplicationName('shopify');
        foreach ($locationList['locations'] as $location) {
            if ($location['name'] === $applicationName) {
                $locationInfo = $location;
                break;
            }
        }

        return $locationInfo;
    }


    /**
     * 保存订单
     * @param $orderDetail
     * @param int $source
     * @param int $syncType
     * @return bool
     */
    public function saveOrder($orderDetail, int $source = Order::ORDER_SOURCE_MANUAL_SYNC, int $syncType = Order::SYNC_TYPE_AUTOMATIC)
    {
        $orderDetail = $orderDetail['order'] ?? $orderDetail;
        if (empty($orderDetail['id'])) return false;

        $shopOrder = Order::query()->where('platform_order_id', (string)$orderDetail['id'])
            ->where('shop_id', $this->shop->id)
            ->where('platform', $this->shop->platform)
            ->where('customer_id', $this->shop->customer_id)
            ->first();
        $orderData = $this->transformOrder($orderDetail, $shopOrder, $syncType);

        // 如果是新增订单且状态不是open的跳过(自动拉取订单类型)
        if (empty($orderData)) return false;
        if (empty($shopOrder) && $orderData['platform_order_status'] != 'open' && $syncType == Order::SYNC_TYPE_AUTOMATIC) return false;

        //同步订单类型
        $orderData['sync_type'] = $syncType;

        $orderDataService = new OrderDataService();
        $orderDataService->createOrder($orderData, $this->shop, $source);
        return !$shopOrder;
    }

    /**
     * @param $orderDetail
     * @param $shopOrder
     * @param $syncType
     * @return array
     */
    protected function transformOrder($orderDetail, $shopOrder, $syncType)
    {
        $exchange_rates = ExchangeRateModel::query()->where('currency_code', 'CNY')
            ->value('custom_exchange_rate');
        $platformStatus = [
            'platform_order_status' => $this->getPlatformStatus($orderDetail, 'order'), //平台订单状态
            'platform_payment_status' => $this->getPlatformStatus($orderDetail, 'financial'), //平台支付状态
            'platform_fulfillment_status' => $this->getPlatformStatus($orderDetail, 'fulfillment'), //平台发货状态
        ];
        // 平台抽象状态
        $platformAbstractStatus = $this->getPlatformAbstractStatus($platformStatus);

        if (empty($shopOrder) && ($platformAbstractStatus === Order::PLATFORM_PENDING_PAYMENT && $platformStatus['platform_payment_status'] != 'authorized') && $syncType == Order::SYNC_TYPE_AUTOMATIC) return null;

        $lienItem = [];
        foreach ($orderDetail['line_items'] as $item) {
            if (!empty($shopOrder)) {
                $orderLineItem = OrderLineItem::query()->where('order_id', $shopOrder->id)->where('variant_id', $item['variant_id'])->first();
            }
            if (empty($orderLineItem)) {
                $itemProduct = $this->getOrderItemProduct($item);
                $images = $itemProduct['images'] ?? [];
                $productHandle = $itemProduct['handle'] ?? '';
                $productUrl = $itemProduct['handle'] ? 'https://' . str_replace('https://', '', $this->shop->shop_url) . '/products/' . $productHandle : '';
            } else {
                $images = $orderLineItem->imgs;
                $productUrl = $orderLineItem->product_url;
            }
            // 应对没有variant_id的特殊情况，可能是shopify产品被删除了
            if (empty($item['variant_id'])) {
                $item['variant_id'] = md5($this->shop->id . '_' . $item['name']);
            }

//            // 如果shopify平台有自定义属性，拼接自定义属性生成唯一variant_id
//            if (!empty($item['properties'])) {
//                ksort($item['properties']);
//                $propertiesString = json_encode($item['properties']);
//                $item['variant_id'] = md5($item['variant_id'] . '_' . $propertiesString);
//            }

            $lienItem[] = [
                'line_item_id' => $item['id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'product_id' => $item['product_id'],
                'quantity' => $item['current_quantity'] ?: $item['quantity'],//quantity 店铺减少数量时此字段值不会变化，所以具体的sku数量应该取current_quantity
                'sku' => $item['sku'],
                'title' => $item['title'],
                'total_discount' => $item['total_discount'],
                'variant_id' => $item['variant_id'],
                'variant_title' => $item['variant_title'],
                'imgs' => $images,
                'product_url' => $productUrl,
//                'properties' => $item['properties'] ?? [],
                'properties' => [],
            ];
        }
        $address = [];
        if (!empty($orderDetail['shipping_address'])) {
            $orderAddress = $orderDetail['shipping_address'];
            $orderAddress['email'] = $orderDetail['email'] ?? '';
            $orderAddress['is_billing_address'] = 0;
        } else {
            $orderAddress = $orderDetail['billing_address'];
            $orderAddress['email'] = $orderDetail['email'] ?? '';
            $orderAddress['is_billing_address'] = 1;
        }
        if (!empty($orderAddress)) {
            $address = [
                'first_name' => $orderAddress['first_name'] ?? '',
                'last_name' => $orderAddress['last_name'] ?? '',
                'address1' => $orderAddress['address1'] ?? '',
                'address2' => $orderAddress['address2'] ?? '',
                'phone' => $orderAddress['phone'] ?? '',
                'city' => $orderAddress['city'] ?? '',
                'province' => $orderAddress['province'] ?? '',
                'country' => $orderAddress['country'] ?? '',
                'company' => $orderAddress['company'] ?? '',
                'name' => $orderAddress['name'] ?? '',
                'country_code' => $orderAddress['country_code'] ?? '',
                'province_code' => $orderAddress['province_code'] ?? '',
                'postal_code' => $orderAddress['postal_code'] ?? '',
                'zip' => $orderAddress['zip'] ?? '',
                'email' => $orderAddress['email'] ?? '',
                'is_billing_address' => $orderAddress['is_billing_address']
            ];
            if (empty($address['country_code'])) $address['country_code'] = $address['country'];
        }

        return [
            'platform' => $this->platform,
            'order_id' => $orderDetail['id'] . '_mate',  //  mate 单号加后缀
            'platform_order_id' => $orderDetail['id'],
            'cancel_reason' => $orderDetail['cancel_reason'] ?? '',
            'cancelled_at' => $orderDetail['cancelled_at'] ?? null,
            'currency' => $orderDetail['currency'] ?? '',
            'subtotal_price' => $orderDetail['subtotal_price'],
            'current_total_price' => $orderDetail['current_total_price'],
            'vendor_price' => null,
            'name' => $orderDetail['name'] ?? '',
            'payment_info' => [
                'total_discounts' => $orderDetail['total_discounts'],
                'total_line_items_price' => $orderDetail['total_line_items_price'],
                'total_outstanding' => $orderDetail['total_outstanding'],
                'total_price' => $orderDetail['total_price'],
                'total_tax' => $orderDetail['total_tax'],
                'total_tip_received' => $orderDetail['total_tip_received'],
                'current_subtotal_price' => $orderDetail['current_subtotal_price'],
                'current_total_discounts' => $orderDetail['current_total_discounts'],
                'current_total_price' => $orderDetail['current_total_price'],
                'current_total_tax' => $orderDetail['current_total_tax'],
            ],
            'created_at' => $orderDetail['created_at'] ?? null,
            'updated_at' => $orderDetail['updated_at'] ?? null,
            'line_items' => $lienItem,
            'address' => $address,
            'remark' => $orderDetail['note'] ?? '', //订单备注
            'refunds' => $orderDetail['refunds'] ?? [],
            'platform_order_status' => $platformStatus['platform_order_status'], //平台订单状态
            'platform_payment_status' => $platformStatus['platform_payment_status'], //平台支付状态
            'platform_fulfillment_status' => $platformStatus['platform_fulfillment_status'], //平台发货状态
            'is_cancel' => $platformStatus['platform_order_status'] == 'cancel' ? 1 : 0,
            'platform_status' => $platformAbstractStatus,
            'platform_order_url' => $orderDetail['order_status_url'] ?? '',
            'fulfillments' => $this->transformFulfillments($orderDetail['fulfillments']),
            'exchange_rates' => $exchange_rates,
        ];
    }

    public function getOrderProductInfo($item)
    {
        if (empty($item['variant_id'] ?? '')) {
            return [
                'handle' => '',
                'images' => []
            ];
        }
        try {
            $productInfo = $this->graphQLApi->getProductDetail($item['product_id']);

            if (empty($productInfo)) return [
                'handle' => '',
                'images' => []
            ];

            $images = $productInfo['images'];
            // 通过position排序，如果规格没有图片，则使用产品position为1的图片也就是主图
//            usort($images, function($a, $b) {
//                return $a['position'] - $b['position'];
//            });
            $skuImage = '';
            foreach ($productInfo['variants'] as $variant) {
                if ($item['variant_id'] == $variant['id']) {
                    $skuImage = $variant['images'][0] ?? '';
                    break;
                }
            }
            return [
                'handle' => $productInfo['handle'],
                'images' => !empty($skuImage) ? [$skuImage] : (!empty($images[0]['src']) ? [$images[0]['src']] : [])
            ];
        } catch (\Exception $e) {
            info('getOrderImages error', [$e->getMessage() . $e->getFile() . $e->getLine(), 'item' => $item]);
            return [];
        }
    }

    public function getPlatformStatus($order, $type): string
    {
        //订单状态
        $orderStatusMap = [
            'open'   => 'open',//开启
            'close'  => 'close',//归档
            'cancel' => 'cancel',//取消
        ];

        //付款状态
        $financialStatusMap = [
            'pending'            => 'pending', //待处理
            'authorized'         => 'authorized', //授权
            'partially_paid'     => 'partially_paid', //部分支付
            'paid'               => 'paid', //已支付
            'partially_refunded' => 'partially_refunded', //部分退款
            'refunded'           => 'refunded', //已退款
            'voided'             => 'voided', //已作废
        ];

        //发货状态
        $fulfillmentStatusMap = [
            'fulfilled' => 'fulfilled', //已发货
            'null'      => 'unshipped', //未发货
            'partial'   => 'partial', //部分发货
            'restocked' => 'restocked', //已补货
        ];

        switch ($type) {
            case 'order':
                $status = $orderStatusMap['open'];
                if (!empty($order['closed_at'])) {
                    $status = $orderStatusMap['close'];
                }
                if (!empty($order['cancelled_at'])) {
                    $status = $orderStatusMap['cancel'];
                }
                break;
            case 'financial':
                $status = $financialStatusMap[$order['financial_status']] ?? 'pending';
                break;
            case 'fulfillment':
                $status = $fulfillmentStatusMap[$order['fulfillment_status']] ?? 'unshipped';
                break;
            default:
                $status = '';
        }

        return $status;
    }


    public function getOrderItemImages($item)
    {
        // 先使用历史订单图片，如果没有再去请求接口
//        $images = Cache::get('shopify_' . $item['variant_id']);
//        $images = json_decode($images, true);
//        if (empty($images[0])) {
//            $images = $this->getOrderImages($item);
//            Cache::set('shopify_' . $item['variant_id'], json_encode($images),3600 * 3);
//        }
//        return $images;
        $product = $this->getOrderItemProduct($item);
        return $product['images'] ?? [];
    }

    public function getOrderItemProduct($item)
    {
        // 先使用历史订单图片，如果没有再去请求接口
        $product = Cache::get('shopify_order_product_' . $item['variant_id']);
        $product = json_decode($product, true);
        if (empty($product['images'])) {
            $product = $this->getOrderProductInfo($item);
            if (!empty($product['handle'])) {
                Cache::set('shopify_order_product_' . $item['variant_id'], json_encode($product),3600 * 3);
            }
        }
        return $product;
    }

    /** 获取平台抽象状态
     * @param $platformStatus
     * @return string
     */
    public function getPlatformAbstractStatus($platformStatus)
    {
        if ($platformStatus['platform_order_status'] === 'cancel') {
            return Order::PLATFORM_CANCELLED;
        }
        if ($platformStatus['platform_order_status'] === 'close') {
            return Order::PLATFORM_COMPLETED;
        }
        if (in_array($platformStatus['platform_payment_status'], ['pending', 'authorized', 'voided']) ) {
            return Order::PLATFORM_PENDING_PAYMENT;
        }
        if ($platformStatus['platform_fulfillment_status'] === 'partial') {
            return Order::PLATFORM_PARTIALLY_SHIPPING;
        }
        if ($platformStatus['platform_fulfillment_status'] === 'fulfilled') {
            return Order::PLATFORM_SHIPPING;
        }
        return Order::PLATFORM_ON_HOLD;
    }

    protected function transformFulfillments($fulfillments)
    {
        $data = [];
        foreach ($fulfillments as $fulfillment) {
            $data[] = [
                'fulfillment_shopify_id' => $fulfillment['id'],
                'status' => $fulfillment['shipment_status'],
                'tracking_number' => $fulfillment['tracking_number'],
                'tracking_url' => $fulfillment['tracking_url'],
                'tracking_company' => $fulfillment['tracking_company'],
                'fulfillment_service' => $fulfillment['service'],
                'fulfillment_at' => $fulfillment['created_at'],
            ];
        }
        return $data;
    }

}
