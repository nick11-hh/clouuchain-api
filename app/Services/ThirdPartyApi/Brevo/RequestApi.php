<?php

namespace App\Services\ThirdPartyApi\Brevo;

use App\Lib\Code;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Exceptions\AccidentException;

class RequestApi
{
    protected Client $httpClient;
    protected string $appKey;
    protected string $baseUrl = 'https://api.brevo.com/v3';

    public const ROUTE_DETAIL_CONTACTS = '/contacts/';//联系人详情
    public const ROUTE_CREATE_CONTACTS = '/contacts';//创建联系人
    public const ROUTE_UPDATE_CONTACTS = '/contacts/';//更新联系人

    protected array $header = [
        'content-type' => 'application/json',
        'accept' => 'application/json',
    ];

    public function __construct($config)
    {
        $this->appKey = $config->app_key;

        $option = [
            'timeout'  => 60,
            'verify'   => false
        ];
        $this->httpClient = new client($option);
    }


    public function getContact($email)
    {
        return $this->request(self::ROUTE_DETAIL_CONTACTS . $email, [], 'GET');
    }

    public function updateContact($email, $params)
    {
        return $this->request(self::ROUTE_UPDATE_CONTACTS . $email, $params, 'PUT');
    }

    public function createContact($params)
    {
        return $this->request(self::ROUTE_CREATE_CONTACTS, $params, 'POST');
    }

    /**************************************************************** protected 公共方法 ***************************************************************/

    /**
     * @param string $uri
     * @param array $data body参数
     * @param string $method
     * @return mixed
     */
    protected function request(string $uri, array $data = [], string $method = 'POST'): mixed
    {
        $option = ['headers' => array_merge($this->header, [
            'api-key' => $this->appKey,
        ])];
        $body = json_encode($data);
        $option['body'] = $body;
        try {
            $url = $this->baseUrl . $uri;
            $response = $this->httpClient->request(strtoupper($method), $url, $option);
            $content = $response->getBody()->getContents();
            $content = json_decode($content, true);
            info('brevo 接口返回', [$option, $content]);
            return $content;
        } catch (GuzzleException $e) {
            info('brevo 接口请求失败', [$option, $e->getMessage()]);
            if ($e->getCode() === 404) {
                $content = $e->getResponse()->getBody()->getContents();
                return json_decode($content, true);
            }

            throw new AccidentException('brevo 接口请求失败:' . $e->getMessage(), Code::OPERATE_FAIL);
        }
    }

}

