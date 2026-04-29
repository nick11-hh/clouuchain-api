<?php

/**
 * Created by PhpStorm.
 * User: lin
 * Date: 2019-05-21
 * Time: 10:34
 */

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

class CurlClient
{
    protected $http;

    public function __construct()
    {
        $this->http = new Client(['headers' => ['Language' => 'en'], 'verify' => false]);
    }

    public function post($url, $params, $next = 0)
    {
        try {
            $response = $this->http->post($url, ['form_params' => $params]);
        } catch (\Exception $e) {
            if ($next >= 2) {
                app('log')->info('多次请求出错，不再请求');
                return null;
            }
            $next++;
            app('log')->info('请求地址' . $url . '出错，重新推送,参数', $params);
            app('log')->error($e->getMessage());
            app('log')->error($e->getTraceAsString());
            return $this->post($url, $params, $next);
        }
        if ($response->getStatusCode() === 200) {
            $bodyData = $response->getBody();
            $responseData = json_decode((string) $bodyData, true);
            if (!$responseData) {
                app('log')->info('请求地址' . $url . '返回不是json数组' . $bodyData . ',参数', $params);
                return null;
            }
            return $responseData;
        }
        app('log')->info('请求地址' . $url . '失败', $params);
        return null;
    }

    /**
     * @param $url
     * @param $params
     * @param $method
     * @return false|mixed|null
     * @throws GuzzleException
     */
    public function setMethod($url, $params, $method): mixed
    {
        if ($method === 'post') {
            $responseData = $this->post($url, $params);
        } elseif ($method === 'get') {
            $responseData = $this->get($url . '?' . http_build_query($params));
        } else {
            app('log')->info('接口' . $url . '未知的请求方式' . $method);
            return false;
        }
        return $responseData;
    }

    /**
     * 自定义头部
     * @param $headers
     */
    public function setHeaders($headers)
    {
        $this->http = new Client(['headers' => $headers, 'verify' => false]);
    }

    /**
     * @param $url
     * @return mixed|null
     * @throws GuzzleException
     */
    public function get($url)
    {
        try {
            $res = $this->http->request('GET', $url);
            app('log')->info('测试 url' . $url);
        } catch (\Exception $e) {
            app('log')->info('请求地址' . $url . '出错');
            app('log')->info('错误信息为：' . $e->getMessage());
            return null;
        }

        if ($res->getStatusCode() === 200) {
            $bodyData = $res->getBody();
            return json_decode((string) $bodyData, true);
        }
        return null;
    }

    public function ret($response, $error = '')
    {
        if(empty($error)) {
            return ApiResponseService::success($response);
        }
        return ApiResponseService::error(message: $error);
    }

    /**
     * @param $url
     * @param string $method
     * @param array $options
     * @return StreamInterface|null
     * @throws GuzzleException
     */
    public function request($url, string $method = 'GET', array $options = []): ?StreamInterface
    {
        try {
            $res = $this->http->request($method, $url, $options);
            app('log')->info('测试 url' . $url);
        } catch (\Exception $e) {
            app('log')->info('请求地址' . $url . '出错');
            return null;
        }
        if ($res->getStatusCode() === 200) {
            $bodyData = $res->getBody();
            //            $responseData = json_decode((string) $bodyData,true);
            app('log')->channel('single')->info('请求的返回结果为:' . (string) $bodyData);
            return $bodyData;
        }
        return $res->getBody();
    }
}
