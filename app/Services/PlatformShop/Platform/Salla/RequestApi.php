<?php

namespace App\Services\PlatformShop\Platform\Salla;

use App\Lib\Code;
use App\Models\ShopAuth;
use App\Models\ShopModel;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use Spatie\FlareClient\Api;
use App\Exceptions\AccidentException;

class RequestApi
{
    protected ShopModel $shop;

    protected Client $httpClient;

    protected string $clientId = '7325708066249787141';

    protected string $appKey = '6b9uovgb4hse1';

    protected string $appSecret = '62a6f810df9d78e0f961dd9ca5345e6b0a569183';

    protected string $baseUrl = 'https://open-api.tiktokglobalshop.com';

    protected string $version = '202309';

    protected string $accessToken;

    protected string $shopCipher;

    protected string $contentType = 'application/json';

    protected array $header = [];

    public function __construct($shop)
    {
        $this->shop = $shop;
        if (!empty($shop->id)) {
            $this->accessToken = $shop->access_token;
            $this->shopCipher = $shop->ext_data['shop_cipher'];
        }
        $option = [
            'base_uri' => $this->baseUrl,
            'timeout'  => 60,
            'verify'   => false,
        ];
        $this->httpClient = new client($option);
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    /** 获取授权token
     * @param $authCode
     * @param string $grantType
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    public function authorize($authCode, string $grantType = 'authorized_code')
    {

        $url = '/api/v2/token/get';
        $option = [
            'base_uri' => 'https://auth.tiktok-shops.com',
            'timeout'  => 60,
            'verify'   => false,
        ];
        $httpClient = new client($option);
        $params = [
            'app_key' => $this->appKey,
            'app_secret' => $this->appSecret,
            'auth_code' => $authCode,
            'grant_type' => $grantType
        ];
        $option = ['query' => $params];
        $response = $httpClient->request('GET', $url, $option);
        $result = json_decode($response->getBody()->getContents(), true);
        if ($result['code'] != 0) throw new AccidentException('授权失败', Code::OPERATE_FAIL);
        $this->accessToken = $result['data']['access_token'];
        $result['shop_list'] = $this->getAuthorizedShop();
        return $result;
    }

    /** 获取授权的店铺列表
     * @return mixed
     * @throws Exception
     */
    public function getAuthorizedShop(): mixed
    {
        $uri = "/authorization/{$this->version}/shops";
        $this->shopCipher = '';
        $result = $this->request($uri);
        if ($result['code'] != 0) throw new AccidentException('获取授权店铺失败', Code::OPERATE_FAIL);
        return $result['data']['shops'];
    }

    /** 获取产品列表
     * @param array $params
     * @param string $pageToken
     * @return mixed
     * @throws Exception
     */
    public function getProductList(array $params, string $pageToken = ''): mixed
    {
        $uri = "/product/{$this->version}/products/search";
        $query = $params;
        $result = $this->request($uri, 'POST', $query);
        return $result['data'];
    }

    /** 获取产品详情
     * @param string $productId
     * @param bool $returnUnderReviewVersion
     * @return mixed
     * @throws Exception
     */
    public function getProductDetail(string $productId, bool $returnUnderReviewVersion = true): mixed
    {
        $uri = "/product/{$this->version}/products/{$productId}";
        $qurey = [
            'return_under_review_version' => $returnUnderReviewVersion
        ];
        $result = $this->request($uri, 'GET', $qurey);
        return $result['data'];
    }

    /**
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function getOrderList($params): mixed
    {
        $uri = "/order/{$this->version}/orders/search";
        $queryField = ['page_size', 'sort_order', 'page_token', 'sort_field'];
        $params = $this->separateParams($params, $queryField);
        $result = $this->request($uri, 'POST', $params['query'], $params['body']);
        return $result['data'];
    }

    /**
     * @param array $orderIds
     * @return mixed
     * @throws Exception
     */
    public function getOrderDetail(array $orderIds): mixed
    {
        $uri = "/order/{$this->version}/orders";
        $query = ['ids' => implode($orderIds)];
        $result = $this->request($uri, 'GET', $query);
        return $result['data']['orders'];
    }

    public function getEligibleShippingService($orderId)
    {
        $uri = "/fulfillment/{$this->version}/orders/{$orderId}/shipping_services/query";
        $result = $this->request($uri, 'POST');
    }

    public function searchPackage($params)
    {
        $uri = "/fulfillment/{$this->version}/packages/search";
        $queryField = ['page_size', 'sort_order', 'page_token', 'sort_field'];
        $params = $this->separateParams($params, $queryField);
        $result = $this->request($uri, 'POST', $params['query'], $params['body']);
        return $result['data'];
    }


    public function shipPackage($packageId, $params)
    {
        $uri = "/fulfillment/{$this->version}/packages/{$packageId}/ship";
        $result = $this->request($uri, 'POST', [], $params);
    }


    /** 获取返货服务商
     * @param $optionId
     * @return mixed
     * @throws Exception
     */
    public function getShippingProviders($optionId): mixed
    {
        $uri = "/logistics/{$this->version}/delivery_options/{$optionId}/shipping_providers";
        $result = $this->request($uri, 'GET');
        return $result['data']['shipping_providers'];
    }


    public function markPackageShipped($orderId, $params)
    {
        $uri = "/fulfillment/{$this->version}/orders/{$orderId}/packages";
        $requestParams = [
            'order_line_item_ids' => $params['order_line_item_ids'],
            'tracking_number' => $params['tracking_number'],
            'shipping_provider_id' => $params['shipping_provider_id'],
        ];
        $result = $this->request($uri, 'POST', [], $requestParams);
        if ($result['code'] != 0) throw new AccidentException('发货失败', Code::OPERATE_FAIL);
        return $result['data'];
    }


    /** 获取产品分类
     * @return mixed
     * @throws Exception
     */
    public function getCategories($locale = 'zh-CN'): mixed
    {
        $uri = "/product/{$this->version}/categories";
        $queryParams = [
            'locale' => $locale
        ];
        $result = $this->request($uri, 'GET', $queryParams);
        if ($result['code'] != 0) throw new AccidentException('获取tiktok产品类目失败', Code::OPERATE_FAIL);
        return $result['data']['categories'];
    }

    public function getCategoryRules($categoryId)
    {
        $uri = "/product/{$this->version}/categories/{$categoryId}/attributes";
        $result = $this->request($uri, 'GET');
        if ($result['code'] != 0) throw new AccidentException('获取tiktok产品类目规则失败', Code::OPERATE_FAIL);
        return $result['data'];
    }

    /** 获取产品属性
     * @param $categoryId
     * @param $locale
     * @return mixed
     * @throws Exception
     */
    public function getProductAttributes($categoryId, $locale = 'zh-CN'): mixed
    {
        $uri = "/product/{$this->version}/categories/{$categoryId}/attributes";
        $queryParams = [
            'locale' => $locale
        ];
        $result = $this->request($uri, 'GET', $queryParams);
        if ($result['code'] != 0) throw new AccidentException('获取tiktok产品类目属性失败', Code::OPERATE_FAIL);
        return $result['data']['attributes'];
    }

    /** 上传产品图片
     * @param $data
     * @return mixed
     * @throws Exception
     */
    public function uploadProductImage($data): mixed
    {
        $uri = "/product/{$this->version}/images/upload";
        $body = [
            'data' => $data
        ];
        $result = $this->request($uri, 'POST', [], $body);
        if ($result['code'] != 0) throw new AccidentException('上传产品图片失败', Code::OPERATE_FAIL);
        return $result['data'];
    }

    /**
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function createProduct($params): mixed
    {
        $uri = "/product/{$this->version}/products";
        $result = $this->request($uri, 'POST', [], $params);
        if ($result['code'] != 0) throw new AccidentException('创建Tiktok产品失败', Code::OPERATE_FAIL);
        return $result['data'];
    }










    /**************************************************************** protected 公共方法 ***************************************************************/

    /**
     * @param string $uri
     * @param string $method
     * @param array $query query参数
     * @param array $data body参数
     * @return mixed
     * @throws Exception
     */
    protected function request(string $uri, string $method = 'GET', array $query = [], array $data = []): mixed
    {
        $baseData = [
            'app_key' => $this->appKey,
            'timestamp' => time(),
            'version' => $this->version
        ];
        if (!empty($this->shopCipher)) $baseData['shop_cipher'] = $this->shopCipher;
        $params = array_merge($query, $baseData);
        $params['sign'] = $this->getSign($uri, $params, $data);
        $method = strtoupper($method);
        $option = ['headers' => [
            ...$this->header,
            'content-type' => $this->contentType,
            'x-tts-access-token' => $this->accessToken
        ]];
        $option['query'] = $params;
        if ($method != 'GET' && !empty($data)) {
            $option[$this->getContentType()] = $data;
        }
        try {
            $response = $this->httpClient->request($method, $uri, $option);
            $content = $response->getBody()->getContents();
            $content = json_decode($content, true);
            info('tiktok 接口返回', [$option, $content]);
            return $content;
        } catch (GuzzleException $e) {
            info('tiktok 接口请求失败', [$option, $e->getMessage()]);
            throw new AccidentException('tiktok 接口请求失败', Code::OPERATE_FAIL);
        }
    }

    protected function getSign($url, $params, $data): string
    {
        $url = Str::start($url, '/');
        unset($params['access_token']);
        unset($params['sign']);
        ksort($params);
        $query_str = http_build_query($params);
        $query_str = str_replace(['=', '&'], '', $query_str);
        $with_url_str = $url . $query_str;
        if (!empty($data) && $this->contentType != 'multipart/form-data') {
            $with_url_str .= json_encode($data);
        }
        $with_app_secret_str = $this->appSecret . $with_url_str . $this->appSecret;
        return hash_hmac('sha256', $with_app_secret_str, $this->appSecret);
    }

    /**
     * @return string
     */
    protected function getContentType(): string
    {
        if ($this->contentType === 'multipart/form-data') {
            return 'multipart';
        }
        return 'json';
    }

    /** 将参数分割为 query params 和 body params
     * @param array $params
     * @param array $queryField
     * @return array[]
     */
    protected function separateParams(array $params, array $queryField) :array
    {
        $result = [
            'query' => [],
            'body' => $params
        ];
        foreach ($params as $key => $value) {
            if (in_array($key, $queryField)) {
                $result['query'][$key] = $value;
                unset($result['body'][$key]);
            }
        }
        return $result;
    }

    protected function refreshToken()
    {
        $url = '/api/v2/token/refresh';
        $option = [
            'base_uri' => 'https://auth.tiktok-shops.com',
            'timeout'  => 60,
            'verify'   => false,
        ];
        $httpClient = new client($option);
        $shopAuth = ShopAuth::query()->find($this->shop->shop_auth_id);
        $params = [
            'app_key' => $this->appKey,
            'app_secret' => $this->appSecret,
            'refresh_token' => $shopAuth->refresh_token,
            'grant_type' => 'refresh_token'
        ];
        $option = ['query' => $params];
        $response = $httpClient->request('GET', $url, $option);
        $result = json_decode($response->getBody()->getContents(), true);
        if ($result['code'] != 0) throw new AccidentException('授权失败', Code::OPERATE_FAIL);
        $shopAuth->access_token = $result['data']['access_token'];
        $shopAuth->access_token_expire_in = date('Y-m-d H:i:s', $result['data']['access_token_expire_in']);
        $shopAuth->refresh_token = $result['data']['refresh_token'];
        $shopAuth->refresh_token_expire_in = date('Y-m-d H:i:s', $result['data']['refresh_token_expire_in']);
        $shopAuth->save();
        ShopModel::query()->where('shop_auth_id', $shopAuth->id)->update([
            'access_token' =>  $result['data']['access_token']
        ]);
    }

}

