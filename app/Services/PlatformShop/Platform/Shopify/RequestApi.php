<?php

namespace App\Services\PlatformShop\Platform\Shopify;


use App\Lib\Code;
use App\Models\ShopModel;
use App\Services\PlatformShop\Exceptions\PlatformShopException;
use App\Services\PlatformShop\RequestFailTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Exceptions\AccidentException;

class RequestApi
{
    use RequestFailTrait;

    protected ?Client $client = null;

    protected ShopModel $shop;

    protected array $header = [];

    public $version = '2024-10';

    public $responseHeaders = [];

    public function __construct($shop)
    {
        $this->shop = $shop;
        $baseUrl = 'https://'.$this->shop->shop_url.'/admin/api/';
        $this->header['X-Shopify-Access-Token'] = $this->shop->access_token;
        $option = [
            'base_uri' => $baseUrl,
            'timeout'  => 60,
            'verify'   => false
        ];
        /*if(config('app.env') == 'local') {
            $option['proxy'] = env('LOCAL_PROXY', 'http://127.0.0.1:7897');
        }*/

        $this->client = new client($option);
    }

    /** ===============根据shopify政策调整，2025.2.1废弃产品相关接口，后续删除=============== */

    /** 获取产品数量
     * @return mixed
     * @throws \Exception
     */
    public function getProductCount()
    {
        $uri = 'products/count.json';
        return $this->request('GET', $uri);
    }

    /**
     * 获取产品列表
     * @param array $ids
     * @return mixed
     * @throws \Exception
     */
    public function getProductList($data)
    {
        $uri = 'products.json';
        $result = $this->request('GET', $uri, $data);
        $link = $this->responseHeaders['link'][0] ?? '';
        $paginate = $this->getShopifyPaginate($link);

        return [
            'paginate' => $paginate,
            'products' => $result['products'] ?? [],
        ];
    }

    /** 获取单个产品详情
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function getProductInfo($productId): mixed
    {
        $uri = "products/{$productId}.json";
        $result = $this->request('GET', $uri);
        return $result['product'] ?? [];
    }

    public function getVariantInfo($variantId)
    {
        $uri = "variants/{$variantId}.json";
        $result = $this->request('GET', $uri);
        return $result['variant'] ?? [];
    }

    /** 刊登产品
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function publishProduct($data): mixed
    {
        $uri = "products.json";
        return $this->request('POST', $uri, $data);
    }

    /** 删除产品
     * @param $productId
     * @return array|mixed
     * @throws \Exception
     */
    public function deleteProduct($productId): mixed
    {
        $uri = "products/{$productId}.json";
        return $this->request('DELETE', $uri);
    }

    /** 获取产品图片
     * @param $productId
     * @return mixed
     * @throws \Exception
     */
    public function getProductImages($productId): mixed
    {
        $uri = "products/{$productId}/images.json";
        $result = $this->request('GET', $uri);
        return $result['images'];
    }

    /** 上传产品图片
     * @param $productId
     * @param $image
     * @return mixed
     * @throws \Exception
     */
    public function uploadProductImages($productId, $image): mixed
    {
        $uri = "products/{$productId}/images.json";
        $params = [
            'image' => $image,
        ];
        return $this->request('POST', $uri, $params);
    }

    /** ===============end=============== */


    public function inventoryItemInfo($itemId)
    {
        $uri = "inventory_items/{$itemId}.json";
        return $this->request('GET', $uri);
    }


    /** 设置商品库存项
     * @param $locationId
     * @param $inventoryItemId
     * @return mixed
     * @throws \Exception
     */
    public function setInventoryItems($inventoryItemId, $setData): mixed
    {
        $uri = "/inventory_items/{$inventoryItemId}.json";
        $params = [
            'inventory_item' => [
                'id' => $inventoryItemId,
            ]
        ];
        $params['inventory_item'] = array_merge($params['inventory_item'], $setData);
        return $this->request('PUT', $uri, $params);
    }

    /** 设置库存水平
     * @param $locationId
     * @param $inventoryItemId
     * @param $available
     * @return mixed
     * @throws \Exception
     */
    public function setInventoryLevels($locationId, $inventoryItemId, $available): mixed
    {
        $uri = "inventory_levels/set.json";
        $params = [
            "location_id" => $locationId,
            'inventory_item_id' => $inventoryItemId,
            'available'=> $available
        ];
        return $this->request('POST', $uri, $params);
    }


    /** 连接库存与地址
     * @param $locationId
     * @param $inventoryItemId
     * @return mixed
     * @throws \Exception
     */
    public function connectInventory($locationId, $inventoryItemId): mixed
    {
        $uri = "inventory_levels/connect.json";
        $params = [
            'location_id' => $locationId,
            'inventory_item_id' => $inventoryItemId,
        ];
        return $this->request('POST', $uri, $params);
    }

    public function setInventoryLevelAdjust($locationId, $inventoryItemId, $quantity)
    {
        $uri = "inventory_levels/adjust.json";
        $params = [
            'location_id' => $locationId,
            'inventory_item_id' => $inventoryItemId,
            'available_adjustment' => $quantity,
        ];
        return $this->request('POST', $uri, $params);
    }


    /** 获取库存位置列表
     * @return mixed
     * @throws \Exception
     */
    public function getInventoryLocations(): mixed
    {
        $uri = 'locations.json';
        return $this->request('GET', $uri);
    }

    // 服务商
    public function fulfillmentServices($name, $inventoryManagement = false, $requiresShippingMethod = false, $trackingSupport = false)
    {
        $uri = 'fulfillment_services.json';
        $callBackUrl = config('app.url') . "/api/client/shopify/callback";
        $data = [
            'fulfillment_service' => [
                'name' => $name,
                'inventory_management' => $inventoryManagement,
                'requires_shipping_method' => $requiresShippingMethod,
                'tracking_support' => $trackingSupport,
                "callback_url" => $callBackUrl,
                "fulfillment_orders_opt_in" => true,
                "permits_sku_sharing" => true,
                "format" => "json"
            ]
        ];
        try {
            return $this->request('POST', $uri, $data);
        } catch (\Exception $e) {
            return false;
        }

    }

    public function getFulfillmentServices($scope = 'all')
    {
        $uri = "fulfillment_services.json";
        $params = ['scope' => $scope];
        $data = $this->request('GET', $uri, $params);
        return $data['fulfillment_services'] ?? [];
    }

    public function updateFulfillmentServices($fulfillmentServiceId, $data)
    {
        $uri = "fulfillment_services/{$fulfillmentServiceId}.json";
        return $this->request('PUT', $uri, $data);
    }


    public function deleteFulfillmentServices($id)
    {
        $uri = "fulfillment_services/{$id}.json";
        return $this->request('DELETE', $uri);
    }

    // 服务商
    public function carrierServices($name, $serviceDiscovery = false)
    {
        $uri = 'carrier_services.json';
        $callBackUrl = config('app.url') . '/api/client/shopify/carrier-service/callback?uuid=' . getCurrentUuid();
        $data = [
            'carrier_service' => [
                'name' => $name,
                "callback_url" => $callBackUrl,
                "service_discovery" => $serviceDiscovery,
            ]
        ];
        try {
            return $this->request('POST', $uri, $data);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getCarrierServicesList()
    {
        $uri = 'carrier_services.json';
        return $this->request('GET', $uri);
    }

    public function deleteCarrierService($carrierId)
    {
        $uri = "carrier_services/{$carrierId}.json";
        return $this->request('DELETE', $uri);
    }


    public function getOrderList($params = [])
    {
        $uri = 'orders.json';

        $data = $this->request('GET', $uri, $params);
        $link = $this->responseHeaders['link'][0] ?? '';
        $paginate = $this->getShopifyPaginate($link);
        return [
            'paginate' => $paginate,
            'data' => $data
        ];
    }

    public function getShopifyPaginate($link)
    {
        preg_match_all('/<([^>]+)>; rel="([^"]+)"/', $link, $matches);
        return array_combine($matches[2], $matches[1]);
    }

    public function getOrderDetail($orderId)
    {
        $uri = "orders/{$orderId}.json";
        return $this->request('GET', $uri);
    }


    public function getFulfillmentOrders($orderId)
    {
        $uri = "orders/{$orderId}/fulfillment_orders.json";
        return $this->request('GET', $uri);
    }

    public function getOrderFulfillments($orderId)
    {
        $uri = "orders/{$orderId}/fulfillments.json";
        $data = $this->request('GET', $uri);
        return $data['fulfillments'] ?? [];
    }

    public function getFulfillmentOrderFulfillments($fulfillmentOrderId)
    {
        $uri = "fulfillment_orders/{$fulfillmentOrderId}/fulfillments.json";
        $data = $this->request('GET', $uri);
        return $data['fulfillments'] ?? [];
    }


    /** 取消履约订单搁置
     * @param $fulfillmentOrderId
     * @return mixed
     * @throws AccidentException
     * @throws PlatformShopException
     */
    public function releaseHoldFulfillmentOrder($fulfillmentOrderId)
    {
        $uri = "fulfillment_orders/{$fulfillmentOrderId}/release_hold.json";
        return $this->request('POST', $uri);
    }


    public function createFulfillment($data)
    {
        info('创建履行订单请求数据', $data);
        $uri = 'fulfillments.json';

        $res = $this->request('POST', $uri, $data);
        info('创建履行订单', $res);
        return $res;
    }

    /** 取消发货的履约项
     * @param $fulfillmentId
     * @return mixed
     * @throws \Exception
     */
    public function cancelFulfillment($fulfillmentId)
    {
        $uri = "fulfillments/{$fulfillmentId}/cancel.json";
        return $this->request('POST', $uri);
    }

    public function getFulfillment($orderId, $fulfillmentId)
    {
        $uri = "orders/{$orderId}/fulfillments/{$fulfillmentId}.json";
        try {
            return $this->request('GET', $uri);
        } catch (GuzzleException $e) {
            return null;
        }

    }

    /** 创建webhook事件监听
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function createWebhooks($data): mixed
    {
        $uri = 'webhooks.json';
        return $this->request('POST', $uri, $data);
    }

    public function getWebhooks()
    {
        $uri = 'webhooks.json';
        $result = $this->request('GET', $uri);
        return $result['webhooks'] ?? [];
    }

    public function deleteWebhook($webhookId)
    {
        $uri = "webhooks/{$webhookId}.json";
        return $this->request('DELETE', $uri);
    }

    public function fulfillmentRequest($fulfillmentOrderId)
    {
        $uri = "fulfillment_orders/{$fulfillmentOrderId}/fulfillment_request";
        return $this->request('POST', $uri);
    }

    public function openCloseOrder($orderId)
    {
        $uri = '/admin/api/2023-10/orders/' . $orderId . '/open.json';
        return $this->request('POST', $uri);
    }

    public function updateFulfillmentTracking($fulfillmentOrderId, $data)
    {
        $uri = 'fulfillments/' . $fulfillmentOrderId . '/update_tracking.json';
        return $this->request('POST', $uri, $data);
    }

    /***---------------------------- 方法 --------------------------------------****/

    /**
     * @param $type
     * @param $uri
     * @param array $data
     * @return mixed
     */
    protected function request($type, $uri, array $data = [])
    {
        $type = strtoupper($type);
        $uri = $this->version . '/' . $uri;
        try {
            $this->responseHeaders = [];
            $option = ['headers' => $this->header,];
            if ($type === 'GET') {
                $option['query'] = $data;
            } else {
                $option['json'] = $data;
            }

            $response = $this->client->request($type, $uri, $option);
            $this->responseHeaders = $response->getHeaders();
            $content = $response->getBody()->getContents();
            return json_decode($content, true);
        } catch (GuzzleException $e) {
            info('shopify请求失败', ['message' => $e->getMessage(), 'data' => $data, 'uri' => $uri]);
            $message = match ($e->getCode()) {
                401 => '授权信息失效 401',
                403 => '授权信息错误 403',
                default => '',
            };
            if ($message) {
                $this->requestFail($message);
                throw new PlatformShopException(PlatformShopException::SHOPIFY_AUTH_ERROR);
            }
            if (str_contains($e->getMessage(), 'status= on_hold')) {
                throw new PlatformShopException(PlatformShopException::SHOPIFY_ORDER_ON_HOLED);
            }
            throw new AccidentException('请求shopify接口失败' . $e->getMessage(), Code::OPERATE_FAIL);
        }
    }

}
