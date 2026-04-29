<?php

namespace App\Services\Wechat;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class RequestApi
{
    public Client $client;
    public string $accessToken; // 企业微信 access_token

    public string $baseUrl = 'https://qyapi.weixin.qq.com/cgi-bin';

    public string $corpId; // 企业微信 corpid

    public string $corpSecret;// 企业微信 corpsecret

    public string $token; // 企业微信 回调token
    public string $aesKey; // 企业微信 aeskey

    public function __construct()
    {
        $this->corpId = config('wecom.corpId');
        $this->corpSecret = config('wecom.corpSecret');
        $this->token = config('wecom.token');
        $this->aesKey = config('wecom.aesKey');
        $this->client = new Client();
        $this->auth();
    }

    /**
     * 获取企业微信 access_token
     */
    public function auth(): array|bool
    {
        try {
            $response = $this->client->request('GET', $this->baseUrl . '/gettoken', [
                'query' => [
                    'corpid' => $this->corpId,
                    'corpsecret' => $this->corpSecret
                ]
            ]);

            if ($response->getStatusCode() != 200) {
                Log::error('WeChat API Authentication failed with status code: ' . $response->getStatusCode());
                return false;
            }

            $result = json_decode($response->getBody()->getContents(), true);

            // 检查企业微信返回的错误码
            if (isset($result['errcode']) && $result['errcode'] != 0) {
                Log::error('WeChat API Authentication failed with error code: ' . $result['errcode']);
                return false;
            }

            $this->accessToken = $result['access_token'];
            return true;
        } catch (GuzzleException $e) {
            Log::error('WeChat API Authentication failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::error('Unexpected error during WeChat authentication: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 提交审批申请
     * @param array $approvalData 审批数据
     * @return array|bool
     */
    public function submitApproval(array $approvalData): bool|array
    {
        try {
            $url = $this->baseUrl . '/oa/applyevent';

            $response = $this->client->request('POST', $url, [
                'query' => [
                    'access_token' => $this->accessToken
                ],
                'body' => json_encode($approvalData),
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() != 200) {
                Log::error('WeChat Approval submission failed with status code: ' . $response->getStatusCode());
                return false;
            }

            $result = json_decode($response->getBody()->getContents(), true);

            // 检查企业微信返回的错误码
            if (isset($result['errcode']) && $result['errcode'] != 0) {
                Log::error('WeChat Approval submission failed with error code: ' . $result['errcode'] . ', errmsg: ' . $result['errmsg']);
                return false;
            }

            return $result;
        } catch (GuzzleException $e) {
            Log::error('WeChat Approval submission failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::error('Unexpected error during WeChat approval submission: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * 通过手机号获取企业微信用户ID
     * @param string $mobile 手机号
     * @return bool|string
     */
    public function getUserIdByMobile(string $mobile): bool|string
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/user/getuserid', [
                'query' => [
                    'access_token' => $this->accessToken
                ],
                'body' => json_encode([
                    'mobile' => $mobile
                ]),
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() != 200) {
                Log::error('WeChat Get User ID failed with status code: ' . $response->getStatusCode());
                return false;
            }

            $result = json_decode($response->getBody()->getContents(), true);

            // 检查企业微信返回的错误码
            if (isset($result['errcode']) && $result['errcode'] != 0) {
                Log::error('WeChat Get User ID failed with error code: ' . $result['errcode'] . ', errmsg: ' . $result['errmsg']);
                return false;
            }

            return $result['userid'];
        } catch (GuzzleException $e) {
            Log::error('WeChat Get User ID failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::error('Unexpected error during WeChat get user ID: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 验证签名
     */
    public function checkSignature(?string $signature, ?string $timestamp, ?string $nonce, ?string $encrypted): bool
    {
        if (empty($signature) || empty($timestamp) || empty($nonce) || empty($encrypted)) {
            return false;
        }

        $arr = [$this->token, $timestamp, $nonce, $encrypted];
        sort($arr, SORT_STRING);
        $localSign = sha1(implode('', $arr));

        return hash_equals($localSign, $signature);
    }


    /**
     * 企业微信 AES 解密
     *
     * @throws \RuntimeException
     */
    public function decrypt(string $encrypted): string
    {
        // 官方 43 位 AESKey，补一个 '=' 后 base64 解码为 32 字节
        $key = base64_decode($this->aesKey . '=', true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('invalid aes key');
        }

        $cipherText = base64_decode($encrypted, true);
        if ($cipherText === false) {
            throw new \RuntimeException('base64 decode error');
        }

        $iv = substr($key, 0, 16);

        // 使用 ZERO_PADDING，后面手动去 PKCS#7
        $decrypted = openssl_decrypt(
            $cipherText,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        if ($decrypted === false) {
            throw new \RuntimeException('openssl decrypt failed');
        }

        $result = $this->pkcs7Unpad($decrypted);

        // 明文结构：16字节随机数 + 4字节网络序消息长度 + 消息 + CorpId
        $content = substr($result, 16);
        $lenList = unpack('N', substr($content, 0, 4));
        if (!isset($lenList[1])) {
            throw new \RuntimeException('length unpack error');
        }

        $xmlLen = $lenList[1];
        $xml = substr($content, 4, $xmlLen);
        $corpId = substr($content, 4 + $xmlLen);

        if (trim($corpId) !== $this->corpId) {
            throw new \RuntimeException('corp id not match');
        }

        return $xml;
    }

    /**
     * PKCS#7 去填充
     */
    public function pkcs7Unpad(string $text): string
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            return $text;
        }

        $padChar = chr($pad);
        if (substr($text, -$pad) !== str_repeat($padChar, $pad)) {
            return $text;
        }

        return substr($text, 0, -$pad);
    }


    /**
     * 根据用户ID获取企业微信用户详细信息
     *
     * @param string $userId 企业微信用户ID
     * @return array|bool 用户信息数组或失败时返回false
     */
    public function getUserInfo(string $userId): bool|array
    {
        try {
            $response = $this->client->request('GET', $this->baseUrl . '/user/get', [
                'query' => [
                    'access_token' => $this->accessToken,
                    'userid' => $userId
                ]
            ]);

            if ($response->getStatusCode() != 200) {
                Log::error('WeChat Get User Info failed with status code: ' . $response->getStatusCode());
                return false;
            }

            $result = json_decode($response->getBody()->getContents(), true);

            // 检查企业微信返回的错误码
            if (isset($result['errcode']) && $result['errcode'] != 0) {
                Log::error('WeChat Get User Info failed with error code: ' . $result['errcode'] . ', errmsg: ' . $result['errmsg']);
                return false;
            }

            return $result;
        } catch (GuzzleException $e) {
            Log::error('WeChat Get User Info failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::error('Unexpected error during WeChat get user info: ' . $e->getMessage());
            return false;
        }
    }

}
