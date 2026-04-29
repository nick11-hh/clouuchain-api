<?php

namespace App\Services\Shopify;


use App\Lib\Code;
use App\Models\ShopModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;
use App\Exceptions\AccidentException;

class RequestService
{

    protected $client = null;

    protected $shop;

    protected $header = [];

    public $version = '2023-10';

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
        $this->client = new client($option);
    }


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
        return $result['products'] ?? [];
    }

    /** 获取单个产品详情
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function getProductInfo($productId)
    {
        $uri = "products/{$productId}.json";
        $result = $this->request('GET', $uri);
        return $result['product'] ?? [];
    }

    /** 刊登产品
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function publishProduct($data)
    {
        $uri = "products.json";
        return $this->request('POST', $uri, $data);
    }

    /** 删除产品
     * @param $productId
     * @return array|mixed
     * @throws \Exception
     */
    public function deleteProduct($productId)
    {
        $uri = "products/{$productId}.json";
        return $this->request('DELETE', $uri);
    }

    /** 获取产品图片
     * @param $productId
     * @return mixed
     * @throws \Exception
     */
    public function getProductImages($productId)
    {
        $uri = "products/{$productId}/images.json";
        return $this->request('GET', $uri);
    }

    /** 上传产品图片
     * @param $productId
     * @param $image
     * @return mixed
     * @throws \Exception
     */
    public function uploadProductImages($productId, $image)
    {
        $uri = "products/{$productId}/images.json";
        $params = [
            'image' => $image,
        ];
        return $this->request('POST', $uri, $params);
    }

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
    public function setInventoryItems($inventoryItemId, $setData)
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
    public function setInventoryLevels($locationId, $inventoryItemId, $available)
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
    public function connectInventory($locationId, $inventoryItemId)
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
    public function getInventoryLocations()
    {
        $uri = 'locations.json';
        return $this->request('GET', $uri);
    }

    /**
     * @return int|mixed
     * @throws \Exception
     */
    public function getInventoryLocationId()
    {
        $data = $this->getInventoryLocations();
        if (empty($data)) return 0;
        $data = $data['locations'];
        return $data[0]['id'] ?? 0;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getCollect()
    {
        $uri = 'collects.json';
        return $this->request('GET', $uri);
    }

    /**
     * @param $collectionId
     * @return mixed
     * @throws \Exception
     */
    public function getCollections($collectionId)
    {
        $uri = "collections/{$collectionId}.json";
        return $this->request('GET', $uri);
    }

    /**
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function getCustomCollection($data = [])
    {
        $uri = 'custom_collections.json';
        return $this->request('GET', $uri, $data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function getSmartCollections($data = [])
    {
        $uri = 'smart_collections.json';
        return $this->request('GET', $uri, $data);
    }


    public function getFulfillmentServices()
    {
        $uri = 'fulfillment_services.json?scope=all';
        return $this->request('GET', $uri);
    }

    // 服务商
    public function fulfillmentServices($name, $inventoryManagement = false, $requiresShippingMethod = false, $trackingSupport = false)
    {
        $uri = 'fulfillment_services.json';
        $callBackUrl = config('app.url') . '/api/client/shopify/callback';
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

    public function getCarrierServices()
    {
        $uri = 'carrier_services.json';
        return $this->request('GET', $uri);
    }

    public function deleteCarrierServices($id)
    {
        $uri = "carrier_services/{$id}.json";
        return $this->request('DELETE', $uri);
    }

    // 创建webhook
    public function createWebhook()
    {
        $uri = 'webhooks.json';

        $query = [
            'uuid' => getCurrentUuid(),
            'function' => 'orderCreate',
        ];

        $address = config('app.url') . '/api/client/shopify/webhook?' . http_build_query($query);

        $data = [
            'webhook' => [
                'topic'   => 'orders/create',
                'address' => $address,
                'format'  => 'json'
            ]
        ];

        try {
            return $this->request('POST', $uri, $data);
        } catch (\Exception $e) {
            return false;
        }

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
            $option = ['headers' => $this->header,];
            if ($type === 'GET') {
                $option['query'] = $data;
            } else {
                $option['json'] = $data;
            }
            $response = $this->client->request($type, $uri, $option);
            $content = $response->getBody()->getContents();
            return json_decode($content, true);
        } catch (GuzzleException $e) {
            info('shopify请求失败', ['message' => $e->getMessage(), 'data' => $data]);
            throw new AccidentException('请求shopify接口失败' . $e->getMessage(), Code::OPERATE_FAIL);
        }
    }

}
