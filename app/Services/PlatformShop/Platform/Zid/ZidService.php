<?php

namespace App\Services\PlatformShop\Platform\Zid;

use App\Lib\Platform;
use App\Models\ExchangeRateModel;
use App\Models\ShopModel;
use App\Services\PlatformShop\DataService\OrderDataService;
use App\Services\PlatformShop\DataService\ProductDataService;
use App\Services\PlatformShop\DataService\ShopDataService;
use App\Services\PlatformShop\PlatformShopAbstract;
use App\Services\PlatformShop\PlatformShopInterface;
use Exception;

class ZidService extends PlatformShopAbstract implements PlatformShopInterface
{

    protected string $platform = Platform::ZID;

    protected RequestApi $request;

    protected int $productRequestNum = 0;

    protected int $orderRequestNum = 0;

    public function __construct($shop)
    {
        parent::__construct($shop);
        $this->request = new RequestApi($shop);
        $this->shopDataService = new ShopDataService($this->platform);
    }

    public function getAuthUrl($params)
    {

    }

    /**
     * @param $params
     * @return void
     * @throws \App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function authorize($params)
    {
        $result = $this->request->authorize($params['code']);
        info('authorize_result:', $result);
        $shopAuthData = $result['data'];
        $shopAuthData['seller_id'] = $shopAuthData['seller_name'];
        $shopAuthData['access_token_expire_in'] = date('Y-m-d H:i:s', $shopAuthData['access_token_expire_in']);
        $shopAuthData['refresh_token_expire_in'] = date('Y-m-d H:i:s', $shopAuthData['refresh_token_expire_in']);
        $shopAuth = $this->shopDataService->saveOrUpdateShopAuth($shopAuthData);
        $shopList = $result['shop_list'];
        foreach ($shopList as $value) {
            $shopData = [
                'shop_name' => $value['name'],
                'platform_shop_id' => $value['id'],
                'access_token' => $shopAuth->access_token,
                'shop_auth_id' => $shopAuth->id,
                'platform_shop_code' => $value['code'],
                'region' => $value['region'],
                'ext_data' => [
                    'shop_cipher' => $value['cipher']
                ],
            ];
            $this->shopDataService->saveOrUpdateShop($shopData);
        }
    }

    /** 同步产品列表
     * @return void
     * @throws
     */
    public function syncProductList()
    {
        $params = ['page_size' => 50];
        $data = $this->getProductList($params);
        foreach ($data as $product) {
            $this->syncProductDetail($product['id']);
        }
    }

    /** 同步产品详情
     * @param $productId
     * @return bool
     * @throws
     */
    public function syncProductDetail($productId): bool
    {
        $productDetail = $this->request->getProductDetail($productId);
        $productData = $this->transformProduct($productDetail);
        $productDataService = new ProductDataService();
        $productDataService->saveProduct($this->shop, $productData);
        return true;
    }

    /**
     * @param $clientGoods
     * @param array $params
     * @return bool
     * @throws \App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception
     */
    public function productPublish($clientGoods, $params)
    {
        $category = $this->request->getProductAttributes(806920);
        logger(json_encode($category));
        return true;
    }

    /**
     * @return void
     */
    public function syncOrderList($filterParams = [])
    {
        $params = [
            'page_size' => 50,
            'sort_order' => 'DESC',
            'sort_field' => 'create_time',
//            'order_status' => 'AWAITING_SHIPMENT'
        ];
        $orderList = $this->getOrderList($params);
        foreach ($orderList as $order) {
            $this->saveOrder($order);
        }
    }

    /**
     * @param $orderId
     * @return void
     * @throws
     */
    public function syncOrderDetail($orderId)
    {
        $orders = $this->request->getOrderDetail([$orderId]);
        foreach ($orders as $order) {
            $this->saveOrder($order);
        }
    }

    /**
     * @param $shopOrder
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function orderShipment($shopOrder, $params = [])
    {
        $order = $this->request->getOrderDetail([$shopOrder->order_id])[0];
        $shippingProviders = $this->request->getShippingProviders($order['delivery_option_id']);
        $orderLineIteIds = array_column($order['line_items'], 'id');
        $shippingParams = [
            'order_line_item_ids' => $orderLineIteIds,
            'tracking_number' => $params['tracking_number'],
            'shipping_provider_id' => $shippingProviders[0]['id']
        ];
        $this->request->markPackageShipped($order['id'], $shippingParams);
    }






    /*******************************************protected 公共方法 ******************************************/

    /**
     * @param $params
     * @param $pageToken
     * @return array|mixed
     * @throws \App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception
     */
    protected function getProductList($params, $pageToken = '')
    {
        if ($this->productRequestNum > 10) return []; //  设置最大循环次数，防止死循环
        $this->productRequestNum ++;
        $data = $this->request->getProductList($params, $pageToken);
        if (!empty($data['next_page_token'])) {
            $product = $this->getProductList($params, $data['next_page_token']);
            $data['products'] = array_merge($data['products'], $product['products'] ?? []);
        }
        return $data['products'];
    }

    /** 循环获取订单列表
     * @param $params
     * @return array|mixed
     * @throws
     */
    protected function getOrderList($params): mixed
    {
        if ($this->orderRequestNum > 10) return []; //  设置最大循环次数，防止死循环
        $this->orderRequestNum ++;
        $data = $this->request->getOrderList($params);
        if (!empty($data['next_page_token'])) {
            $params['page_token'] = $data['next_page_token'];
            $order = $this->getOrderList($params);
            $data['orders'] = array_merge($data['orders'], $order['orders'] ?? []);
        }
        return $data['orders'];
    }

    /**
     * @param $productDetail
     * @return array
     */
    protected function transformProduct($productDetail): array
    {
        info('product info', $productDetail);
        $skuList = [];
        foreach ($productDetail['skus'] as $sku) {
            $skuList[] = [
                'platform_sku_id' => $sku['id'],
                'sku' => $sku['seller_sku'],
                'barcode' => $sku['seller_sku'],
                'title' => '',
                'option' => '',
                'price' => $sku['price']['sale_price'] ?? 0,
                'inventory_quantity' => $sku['inventory']['quantity'] ?? 0,
            ];
        }
        $images = [];
        foreach ($productDetail['main_images'] as $image) {
            $images[] = $image['urls'][0] ?? '';
        }
        return [
            'product_id' => $productDetail['id'],
            'product_name' => $productDetail['title'],
            'tags' => '',
            'status' => $productDetail['status'],
            'options' => [],
            'images' => $images,
            'detail' => $productDetail['description'],
            'published_at' => date('Y-m-d H:i:s', $productDetail['create_time']),
            'skus' => $skuList
        ];
    }

    protected function saveOrder($orderDetail)
    {
        $orderData = $this->transformOrder($orderDetail);
        $orderDataService = new OrderDataService();
        return $orderDataService->createOrder($orderData, $this->shop);
    }

    /**
     * @param array $orderDetail
     * @return array
     */
    protected function transformOrder(array $orderDetail): array
    {
        $lienItem = [];
        $exchange_rates = ExchangeRateModel::query()->where('currency_code', 'CNY')
            ->value('custom_exchange_rate');
        foreach ($orderDetail['line_items'] as $item) {
            if (isset($lienItem[$item['sku_id']])) {
                $lienItem[$item['sku_id']]['quantity'] ++;
            } else {
                $lienItem[$item['sku_id']] = [
                    'line_item_id' => $item['id'],
                    'name' => $item['product_name'],
                    'price' => $item['sale_price'],
                    'product_id' => $item['product_id'],
                    'quantity' => 1,
                    'sku' => $item['seller_sku'],
                    'title' => $item['product_name'],
                    'total_discount' => $item['platform_discount'],
                    'variant_id' => $item['sku_id'],
                    'variant_title' => $item['sku_name'],
                    'imgs' => [$item['sku_image']],
                ];
            }
        }
        $city = '';
        $province = '';
        $country = '';
        foreach ($orderDetail['recipient_address']['district_info'] as $district) {
            if ($district['address_level_name'] == 'Country' && empty($country)) {
                $country = $district['address_name'];
            }
            $district['address_level_name'] == 'State' && $province = $district['address_name'];
            $district['address_level_name'] == 'City' && $city = $district['address_name'];
        }
        $nameArr = explode(' ', $orderDetail['recipient_address']['name']);
        $address = [
            'first_name' => '',
            'last_name' => '',
            'address1' => $orderDetail['recipient_address']['address_detail'],
            'address2' => $orderDetail['recipient_address']['address_line1'],
            'phone' => $orderDetail['recipient_address']['phone_number'],
            'city' => $city,
            'province' => $province,
            'country' => $country,
            'name' => $orderDetail['recipient_address']['name'],
            'company' => $orderDetail['recipient_address']['company'],
            'country_code' => $orderDetail['recipient_address']['region_code'],
            'province_code' => '',
            'postal_code' => $orderDetail['recipient_address']['postal_code'],
            'zip' => $orderDetail['recipient_address']['postal_code'],
        ];
        return [
            'platform' => $this->platform,
            'order_id' => $orderDetail['id'],
            'cancel_reason' => '',
            'cancelled_at' => $orderDetail['cancel_order_sla_time'] ?? null,
            'currency' => $orderDetail['payment']['currency'] ?? '',
            'subtotal_price' => $orderDetail['payment']['total_amount'],
            'current_total_price' => $orderDetail['payment']['total_amount'],
            'vendor_price' => null,
            'payment_info' => $orderDetail['payment'] ?? null,
            'created_at' => $orderDetail['create_time'] ?? null,
            'updated_at' => $orderDetail['update_time'] ?? null,
            'line_items' => array_values($lienItem),
            'exchange_rates' => $exchange_rates,
            'address' => $address
        ];
    }


}
