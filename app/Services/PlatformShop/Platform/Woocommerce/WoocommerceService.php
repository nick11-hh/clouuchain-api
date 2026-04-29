<?php

namespace App\Services\PlatformShop\Platform\Woocommerce;

use App\Lib\Code;
use App\Models\ClientGoods;
use App\Models\ExchangeRateModel;
use App\Models\Order;
use App\Services\PlatformShop\PlatformShopAbstract;
use App\Services\PlatformShop\PlatformShopInterface;
use App\Services\PlatformShop\DataService\ProductDataService;
use App\Services\PlatformShop\DataService\OrderDataService;
use App\Models\ShopModel;
use App\Services\PlatformShop\RequestFailTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use App\Exceptions\AccidentException;

class WoocommerceService extends PlatformShopAbstract implements PlatformShopInterface
{
    use RequestFailTrait;
    protected ShopModel $shop;

    protected $publishGoodsUrl;

    protected $consumerKey;
    protected $consumerSecret;

    public function __construct(ShopModel $shop) {
        $this->shop = $shop;
        $this->consumerKey = $shop->access_key;//woocoomerce Key
        $this->consumerSecret = $shop->access_token;//woocoomerce secret
        $this->publishGoodsUrl = trim($shop->shop_url, '/').'/wp-json/wc/v3/products';//产品api url  包含 发布产品 发布单个产品 发布变体等用到
        $this->syncOrderUrl = trim($shop->shop_url, '/').'/wp-json/wc/v3/orders';//订单操作api url
        $this->attributesUrl = trim($shop->shop_url, '/').'/wp-json/wc/v3/products/attributes';//属性操作api url
    }
    // 获取店铺授权链接
    public function getAuthUrl($params){

    }

    // 检测授权信息是否正确
    public function authorize($params){
        $response = Http::withBasicAuth($params['access_key'], $params['access_token'])->get(trim($params['shop_url'], '/') . '/wp-json/wc/v3/orders');
        $status = 200;
        if ($response->failed()) {
            $status = $response->status();
        }
        return $status;
    }

    // 同步平台产品
    public function syncProductList(){
        $productDataService = new ProductDataService();
        //发送请求
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($this->publishGoodsUrl.'?per_page=10');

        if ($response->successful()) {
            //解析返回数据
            $wooProducts = $response->json();

            foreach ($wooProducts as $wooProduct) {

                $this->saveProduct($wooProduct);
            }
        } else {
            // 处理错误情况
            info('同步woocommerce平台商品失败', ['status' => $response->status(), 'response-body' => $response->body()]);
        }
    }

    // 同步单个产品详情
    public function syncProductDetail($productId){
        $productDataService = new ProductDataService();
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($this->publishGoodsUrl.'/'.$productId);
        if ($response->successful()) {
            $wooProduct = $response->json();
            $this->saveProduct($wooProduct);
        } else {
            // 处理错误情况
            echo $response->status();
            exit('error');
        }

        return true;
    }

    // 产品刊登发布
    public function productPublish($clientGoods, $params){
        $product = [];
        $product['name'] = $clientGoods->goods_name;
        $product['type'] = $clientGoods->options?'variable':'simple';
        //根据不同类型的产品 产生不同的数据

        $product['description'] = $clientGoods->detail;
        $product['images'] =[];
        if(isset($clientGoods->main_images) && $clientGoods->main_images){

            foreach($clientGoods->main_images as $ik=> $image){
                $imageVal = [];
                $imageVal['src'] = $image;
                $imageVal['position'] = $ik+1;
                $product['images'][] = $imageVal;
            }
        }

        if($product['type']=='simple')//普通产品
        {
            $product['regular_price'] = $clientGoods[0]->sale_price;
            $product['sku'] = $clientGoods->sku_id;

        } else{//多变体产品
            foreach($clientGoods->options as $key=>$v){
                $product['attributes'][$key]['variation'] = true;
                $product['attributes'][$key]['visible']  = true;
                $attrValues = [];
                foreach($v['specs'] as $vk=> $attrV){
                    if (empty($attrV['name'])) continue;
                    $attrValues[] = $attrV['name'];
                }
                $product['attributes'][$key]['options'] = $attrValues;
                $product['attributes'][$key]['name'] = $v['name'];
            }
        }
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($this->publishGoodsUrl, $product);
        if ($response->successful()) {
            $productData = $response->json();
            if(isset($productData['id']) && $productData['id']){
                //创建属性
                //获取当前属性列表
                $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($this->attributesUrl);
                if ($response->successful()) {
                    $nowAttrs = $response->json();
                    $names = [];
                    foreach($nowAttrs as $ak=>$nattr){
                        //把属性name值转入数组 做判断
                        $names[] = $nattr['name'];
                    }
                    if($names && $clientGoods->options){
                        //获取本地属性
                        $extraAttr = [];
                        foreach($clientGoods->options as $key=>$v){
                            if(!in_array($v['name'],$names)){//如果本地属性名称 不在平台方 进行批量创建
                                $nameVal = [];
                                $nameVal['name'] = $v['name'];
                                $extraAttr[] = $nameVal;
                            }
                        }
                        //发起批量创建请求
                        if($extraAttr){
                            $httpResponse = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                                ->withHeaders(['Content-Type' => 'application/json'])
                                ->post($this->attributesUrl.'/batch',['create'=>$extraAttr]);
                            $message = $httpResponse->json();
                            // file_put_contents('attrsSyn.txt',print_r($message,true).PHP_EOL,FILE_APPEND);
                            logger('批量：'.$message);
                        }
                    }
                }
                //创建变体
                if($clientGoods->skus)
                {
                    foreach($clientGoods->skus as  $cv){
                        $variationsVal = [];
                        $variationsVal['regular_price'] = $cv['sale_price'];
                        if($cv['spec_info'] && is_array($cv['spec_info'])) {
                            $specs = $cv['spec_info'];
                        } else {
                            $specs = json_decode($cv['spec_info'],true);
                        }
                        $detailSpecs = [];
                        foreach($specs as $sk=>$sv){
                            $detailSpecs[$sk]['name'] = $sv['name'];
                            $detailSpecs[$sk]['option'] = $sv['value'];
                        }
                        $variationsVal['attributes'] = $detailSpecs;
                        $variationsVal['sku'] =$cv['sku_id'];
                        $variationsVal['stock_quantity'] =$cv['quantity'];
                        $variations[] = $variationsVal;
                    }

                    if(!empty($variations)){
                        $httpResponse = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                                ->withHeaders(['Content-Type' => 'application/json'])
                                ->post($this->publishGoodsUrl.'/'.$productData['id'].'/variations/batch',['create'=>$variations]);
                        $message = $httpResponse->json();
                        // file_put_contents('skuSyn.txt',print_r($message,true).PHP_EOL,FILE_APPEND);
                        info('变体：', $message);
                    }
                }
            }
            // 更新推送状态
            $clientGoods->status = ClientGoods::STATUS_PUBLISHED;
            $clientGoods->save();
            info('woocommerce 产品刊登成功', ['message' => '成功，产品id：'.$productData['id']]);

            return $productData;
        } elseif ($response->failed()) {
            $errorData = $response->json();
            info('woocommerce 产品刊登失败', $errorData);

            throw new AccidentException('woocommerce 产品刊登失败', Code::OPERATE_FAIL);
        }
    }

    // 同步平台订单

    /**
     * @throws Exception
     */
    public function syncOrderList($filterParams = []){
        $params = [
            'per_page' => 100,
            'status' => 'processing,on-hold,completed,cancelled',
            'after' => Carbon::now()->subMonths(2)->toDateTimeString(),
        ];
        $page = 1;
        do {
            $params['page'] = $page;
            $queryString = http_build_query($params);
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($this->syncOrderUrl.'?' . $queryString);
            if ($response->successful()) {
                $orders = $response->json();
                foreach ($orders as $orderData) {
                    $this->saveOrder($orderData);
                }
            } else {
                // 处理错误
                $this->shopFail($response->status());
                $error = $response->body();
                $error = json_decode($error, true);
                throw new AccidentException('同步woocommerce订单失败' . ($error['message'] ?? ''), Code::OPERATE_FAIL);
            }
            $page ++ ;
        } while (count($orders) > 0 && $page < 10);
        return true;
    }

    /** 同步单个订单详情
     * @throws Exception
     */
    public function syncOrderDetail($orderId){
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($this->syncOrderUrl.'/'.$orderId);

        if ($response->successful()) {
            $orderData = $response->json();
            $this->saveOrder($orderData);
        } else {
            // 处理错误
            $this->shopFail($response->status());
            $error = $response->body();
            $error = json_decode($error, true);
            throw new AccidentException('同步woocommerce订单失败' . ($error['message'] ?? ''), Code::OPERATE_FAIL);
        }

        return true;
    }

    // 订单履约发货

    /**
     * @param $shopOrder
     * @param $params
     * @return bool
     * @throws Exception
     */
    public function orderShipment($shopOrder, $params){
        $trackNumber = $shopOrder->logisticsApply->way_bill_number ?? '';
        if (empty($trackNumber)) return false;

        // 更新订单状态
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->put($this->syncOrderUrl.'/'. $shopOrder->platform_order_id, [
                    'status' => 'completed'
                ]);

        if ($response->successful()) {
            // 上传运单号
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->post($this->syncOrderUrl . '/' . $shopOrder->platform_order_id . '/notes' , [
                    'note' => "Your order has been shipped by {$shopOrder->logistics_provider_code}. The tracking number is {$trackNumber}.",
                ]);
            // 处理成功的响应
            if (!$response->successful()) {
                $error = $response->body();
                $error = json_decode($error, true);
                throw new AccidentException('woocommerce交运失败' . ($error['message'] ?? ''), Code::OPERATE_FAIL);
            }
            return true;
        } else {
            // 处理失败的响应
            $error = $response->body();
            $error = json_decode($error, true);
            throw new AccidentException('woocommerce交运失败' . ($error['message'] ?? ''), Code::OPERATE_FAIL);
        }
    }

    public function packageShipment($package, $order, $params)
    {
        $trackNumber = $params['tracking_number'] ?? '';
        if (empty($trackNumber)) return false;
            // 更新订单状态
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->put($this->syncOrderUrl.'/'. $order->platform_order_id, [
                'status' => 'completed'
            ]);

        if ($response->successful()) {
            // 上传运单号
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->post($this->syncOrderUrl . '/' . $order->platform_order_id . '/notes' , [
                    'note' => "Your order has been shipped by {$order->logistics_provider_code}. The tracking number is {$trackNumber}.",
                ]);
            // 处理成功的响应
            if (!$response->successful()) {
                $error = $response->body();
                $error = json_decode($error, true);
                throw new AccidentException('woocommerce交运失败' . ($error['message'] ?? ''), Code::OPERATE_FAIL);
            }
            return true;
        } else {
            // 处理失败的响应
            $error = $response->body();
            $error = json_decode($error, true);
            throw new AccidentException('woocommerce交运失败' . ($error['message'] ?? ''), Code::OPERATE_FAIL);
        }
    }

    public function saveOrder($orderData)
    {
        $orderDataService = new OrderDataService();
        $orderId = $this->shop->id . '-' .$orderData['id'] . '-mate';
        $shopOrder = Order::query()->where('platform_order_id', $orderData['id'])
            ->where('shop_id', $this->shop->id)
            ->first();
        $exchange_rates = ExchangeRateModel::query()->where('currency_code', 'CNY')
            ->value('custom_exchange_rate');

        // 平台抽象状态
        $platformAbstractStatus = $this->getPlatformAbstractStatus($orderData['status']);

        if (empty($shopOrder) && in_array($platformAbstractStatus, [Order::PLATFORM_PENDING_PAYMENT, Order::PLATFORM_CANCELLED])) return null;
        $order['customer_id'] = $this->shop->customer_id;
        $order['platform_order_id'] = $orderData['id'];
        $order['order_id'] = $orderId;
        $order['name'] = $orderData['number'];
        $order['payment_info'] = [];
        $order['platform'] = $this->shop->platform;
        $order['created_at'] = date('Y-m-d H:i:s', strtotime($orderData['date_created']));
        $order['currency'] = $orderData['currency'];
        $order['current_subtotal_price'] = $orderData['total'];
        $order['current_total_discounts'] = $orderData['discount_total'];
        $order['current_total_price'] = $orderData['total'];
        $order['current_total_tax'] = $orderData['total_tax'];
        $order['subtotal_price'] = $orderData['total'];
        $order['updated_at'] = date('Y-m-d H:i:s', strtotime($orderData['date_modified']));
        $order['shop_id'] = $this->shop->id;
        $order['platform_status'] = $platformAbstractStatus;
        $order['platform_order_status'] = $orderData['status'];
        $order['logistics_provider'] = $orderData['shipping_lines'][0]['method_title'] ?? '';
        $order['sku_status'] = 0;
        $order['logistics_fee'] = $orderData['shipping_lines'][0]['total'] ?? 0;
        $order['logistics_provider_code'] = $orderData['shipping_lines'][0]['instance_id'] ?? '';
        $order['paymented_at'] = !empty($orderData['date_paid']) ? date('Y-m-d H:i:s', strtotime($orderData['date_paid'])) : NULL;
        $order['exchange_rates'] = $exchange_rates;

        //订单 子项
        foreach($orderData['line_items'] as $item){
            $variantArray = array_column($item['meta_data'], 'display_value');
            $variantArray = array_filter($variantArray, function ($item) {
                return is_string($item);
            });
            $variantTitle = implode(' / ', $variantArray);
//            $variationId = $item['variation_id'];
//            if (empty($variationId)) {
                $variationId = $item['product_id'] . '_' . $variantTitle;
//            }
            $orderItem = [];
            $orderItem['name'] = $item['name'];
            $orderItem['price'] = $item['price'];
            $orderItem['product_id'] = $item['product_id'];
            $orderItem['quantity'] = $item['quantity'];
            $orderItem['sku'] = $item['sku'];
            $orderItem['total_discount'] = 0.00;
            $orderItem['variant_id'] = $this->shop->id . '_' . $variationId;
            $orderItem['title'] = $item['name'];
            $orderItem['variant_title'] = $variantTitle;
            $orderItem['line_item_id'] = $item['id'];
            $orderItem['imgs'] = isset($item['image']['src'])?[$item['image']['src']]:[];
            $order['line_items'][] = $orderItem;
        }
        //收货地址
        $order['address']['customer_id'] = $this->shop->customer_id;
        $order['address']['first_name'] = $orderData['shipping']['first_name'];
        $order['address']['last_name'] = $orderData['shipping']['last_name'];
        $order['address']['address1'] = $orderData['shipping']['address_1'];
        $order['address']['address_2'] = $orderData['shipping']['address_2'];
        $order['address']['phone'] = $orderData['shipping']['phone'] ?: ($orderData['billing']['phone'] ?? '');
        $order['address']['email'] = $orderData['billing']['email'] ?? '';
        $order['address']['city'] = $orderData['shipping']['city'];
        $order['address']['zip'] = $orderData['shipping']['postcode'];
        $order['address']['province'] = $orderData['shipping']['state'];
        $order['address']['company'] = $orderData['shipping']['company'];
        $order['address']['country'] = $orderData['shipping']['country'];
        $order['address']['country_code'] = $orderData['shipping']['country'];
        $orderDataService->createOrder($order, $this->shop);
    }

    public function saveProduct($wooProduct)
    {
        $product =[];
        $product['product_id'] = $this->shop->id . '_' . $wooProduct['id'];
        $product['product_name'] = $wooProduct['name'];
        $product['status'] = $wooProduct['status']=='publish'?1:0;
        //属性
        $attrs =[] ;
        if(isset($wooProduct['attributes']) && $wooProduct['attributes']){
            foreach($wooProduct['attributes'] as $attrInfo){
                $value = [];
                $value['id'] = $attrInfo['id'];
                $value['name'] = $attrInfo['name'];
                $value['position'] = $attrInfo['position'];
                $value['values'] = $attrInfo['options'];
                $value['product_id'] = $wooProduct['id'];
                $attrs[] = $value;
            }
        }
        $wooProduct['options'] = json_encode($attrs);
        $product['product_type'] = !empty($attrs)?'variable':'simple';
        //图集
        $images = [];
        if(isset($wooProduct['images']) && $wooProduct['images']){
            foreach($wooProduct['images'] as $imageInfo){
                $images[] = $imageInfo['src'];
            }
        }
        $product['images'] = $images;
        $product['detail'] = $wooProduct['description'];
        $product['published_at'] = date('Y-m-d H:i:s', strtotime($wooProduct['date_created']));
        $product['created_at'] = date('Y-m-d H:i:s', strtotime($wooProduct['date_created']));
        $product['skus'] = [];
        // 如果产品有多个 SKU

        $variationsUrl = $this->shop->shop_url .'/wp-json/wc/v3/products/'.$wooProduct['id'].'/variations';
        $productVariationsResponse = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($variationsUrl);
        $productVariations = $productVariationsResponse->json();

        if($productVariations)
        {
            foreach ($productVariations as $variation) {
                $value  = [];
                $value['platform_sku_id'] = $this->shop->id . '_' . $variation['id'];
                $value['sku'] = $variation['sku'];
                $value['price'] = $variation['price']?:0;
                $value['inventory_quantity'] = $variation['stock_quantity']?:0;
                $value['created_at'] = date('Y-m-d H:i:s', strtotime($wooProduct['date_created']));
                $skuOptions = [];
                foreach($variation['attributes'] as $attribute){
                    $optionVal = [];
                    $optionVal['name'] = $attribute['name'];
                    $optionVal['option'] = $attribute['option'];
                    $skuOptions[] = $optionVal;
                }
                $value['option'] = json_encode($skuOptions);
                $product['skus'][] =$value;
            }
        }
        info('woocommerce 平台skus ', $product['skus']);
        $productDataService = new ProductDataService();
        $productDataService->saveProduct($this->shop,$product);
    }

    protected function getPlatformAbstractStatus($platformStatus)
    {
        $mapping = [
            'pending' => Order::PLATFORM_PENDING_PAYMENT,
            'processing' => Order::PLATFORM_WAITING_SHIPMENT,
            'on-hold' => Order::PLATFORM_ON_HOLD,
            'completed' => Order::PLATFORM_COMPLETED,
            'cancelled' => Order::PLATFORM_CANCELLED,
            'refunded' => Order::PLATFORM_REFUNDED,
            'failed ' => Order::PLATFORM_CANCELLED,
            'trash ' => Order::PLATFORM_CANCELLED,
        ];
        return $mapping[$platformStatus] ?? Order::PLATFORM_WAITING_SHIPMENT;
    }

    protected function shopFail($status)
    {
        $message = match ($status) {
            401 => '授权信息失效 401',
            403 => '授权信息错误 403',
            404 => '店铺地址错误 404',
            400 => '店铺接口地址异常 400',
            default => '',
        };
        info('woocommerce店铺错误', ['status' => $status, 'message' => $message]);
        if ($message) {
            $this->requestFail($message);
        }
    }

}
