<?php

namespace App\Services\ThirdPartyWarehouse\Mabang;

use App\Lib\Code;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Exceptions\AccidentException;

class RequestApi
{
    protected Client $httpClient;

    protected string $appKey = '201251';

    protected string $appSecret = '9ace94002cd06661bd91c6dd53a81076';
    protected string $baseUrl = 'https://gwapi.mabangerp.com/api/v2';

    protected array $header = [
        'Content-type' => 'application/json'
    ];

    public function __construct($config)
    {
        $this->appKey = $config->app_key;
        $this->appSecret = $config->app_secret;
        $option = [
            'base_uri' => $this->baseUrl,
            'timeout'  => 60,
            'verify'   => false
        ];
        $this->httpClient = new client($option);
    }


    public function createShop($params)
    {
        $apiName = 'sys-do-create-shop';
        return $this->request($apiName, $params);
    }

    public function createOrder($params)
    {
        $apiName = 'order-do-create-order';
        return $this->request($apiName, $params);
    }

    public function getOrderDetail($orderSn)
    {
        $apiName = 'order-get-order-list-new';
        $params = [
            'allstatus' => 1,
            'platformOrderIds' => $orderSn
        ];
        return $this->request($apiName, $params);
    }

    /**
     * 批量获取订单详情（最多支持10个订单号）
     * @param array $orderIds 订单号数组
     * @return mixed
     * @throws Exception
     */
    public function batchGetOrderDetail(array $orderIds)
    {
        $apiName = 'order-get-order-list-new';
        $params = [
            'allstatus' => 1,
            'platformOrderIds' => implode(',', $orderIds)
        ];
        return $this->request($apiName, $params);
    }

    public function getStockSku($params)
    {
        $apiName = 'stock-do-search-sku-list-new';
        return $this->request($apiName, $params);
    }

    public function updateStockSku($stockSku, $params = [])
    {
        $apiName = 'stock-do-change-stock';
        $params['stockSku'] = $stockSku;
        return $this->request($apiName, $params);
    }

    public function getLogisticChannel($params)
    {
        $apiName = 'wl-get-custom-logistics';
        return $this->request($apiName, $params);
    }

    /**
     * 请求标记订单为配货中
     * @param $orderIds
     * @return mixed
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/14 17:04
     */
    public function markInDistribution($orderIds)
    {
        $params = [
            'platformOrderIds' => is_array($orderIds) ? [implode(',', $orderIds)] : [$orderIds],
        ];
        $apiName = 'order-update-order-new-order';
        return $this->request($apiName, $params);
    }

    public function checkOrderCreate($orderId)
    {
        $apiName = 'order-do-create-order-check';
        $params = [
            'platformOrderId' => $orderId
        ];
        return $this->request($apiName, $params);
    }

    /**
     * 修改订单
     * @param $params
     * @return mixed
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/20 15:25
     */
    public function updateOrderData($params)
    {
        $apiName = 'order-do-change-order';
        return $this->request($apiName, $params);
    }

    public function addMabangStock($params)
    {
        $apiName = 'stock-do-add-stock';
        return $this->request($apiName, $params);
    }

    public function updateMabangStock($params)
    {
        $apiName = 'stock-do-change-stock';
        return $this->request($apiName, $params);
    }

    public function skuLinkBindMabangStock($params)
    {
        $apiName = 'stock-bind-product-link';
        return $this->request($apiName, $params);
    }

    public function addMabangComboSku($params)
    {
        $apiName = 'stock-do-add-combo-sku';
        return $this->request($apiName, $params);
    }

    public function updateMabangComboSku($params)
    {
        $apiName = 'stock-do-change-combosku';
        return $this->request($apiName, $params);
    }

    public function getEmployeeList($params = [])
    {
        $apiName = 'sys-get-employee-list';
        return $this->request($apiName, $params);
    }

    /**************************************************************** protected 公共方法 ***************************************************************/

    /**
     * @param string $apiName
     * @param array $data body参数
     * @param string $method
     * @return mixed
     * @throws Exception
     */
    protected function request(string $apiName, array $data = [], string $method = 'POST'): mixed
    {
        $baseData = [
            'api' => $apiName,
            'appkey' => $this->appKey,
            'data' => $data,
            'timestamp' => time(),
            'version' => '1',
        ];
        $body = json_encode($baseData);
        $option = ['headers' => array_merge($this->header, [
            'Authorization' => $this->getSign($body)
        ])];
        $option['body'] = $body;
        try {
            $response = $this->httpClient->request(strtoupper($method), '', $option);
            $content = $response->getBody()->getContents();
            $content = json_decode($content, true);
//             if ($apiName != 'order-get-order-list-new') {
                info('mabang 接口返回', [$option, $content]);
//             }
            return $content;
        } catch (GuzzleException $e) {
            info('mabang 接口请求失败', [$option, $e->getMessage()]);
            throw new AccidentException('mabang 接口请求失败', Code::OPERATE_FAIL);
        }
    }

    protected function getSign($body): string
    {
        return hash_hmac('sha256', $body, $this->appSecret);
    }

}

