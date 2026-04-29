<?php

namespace App\Services\PaymentPlatform\FortySeas;

use App\Models\CreditCardTypes;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RequestApi
{

    protected string $baseUrl = 'https://api.40seas.com';

    private const TOKEN_CACHE_KEY = 'client:pay:forty_seas:token';

    protected string $token;
    private Client $client;

    public string $client_id;
    private string $client_secret;


    public function __construct()
    {
        $this->client = new Client();
        $this->token = $this->getToken();
    }

    /**
     * 获取token（带缓存逻辑）
     * @return string|bool
     */
    private function getToken(): string|bool
    {
        try {
//        $token = Redis::get(self::TOKEN_CACHE_KEY);
//
//        if (!empty($token)) {
//            return $token;
//        }
            $CreditCardType = CreditCardTypes::where('name', CreditCardTypes::TYPE_40SEAS)->where('status', CreditCardTypes::STATUS_NORMAL)->first();
            if (empty($CreditCardType['client_id']) || empty($CreditCardType['client_secret']) || empty($CreditCardType['webhook_secret'])) {
                Log::info('40Seas API key is empty.');
                return false;
            }

            $this->client_id = $CreditCardType['client_id'];
            $this->client_secret = $CreditCardType['client_secret'];

            // 如果缓存中没有，则重新获取
            $data = $this->auth();

            if (empty($data['token_type']) || empty($data['access_token'] || empty($data['expires_in']))) {
                Log::info('40Seas API Authentication failed, Token is empty.');
                return false;
            }

            $token = $data['token_type'] . ' ' . $data['access_token'];

            // $expiresIn = min($data['expires_in'], 86400);

            // Redis::setex(self::TOKEN_CACHE_KEY, $expiresIn, $token);

            return $token;
        } catch (\Exception $e) {
            Log::warning('Failed to get token during migration: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 授权接口
     * @return array|bool
     */
    public function auth(): array|bool
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/v1/auth', [
                'body' => json_encode([
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'grant_type' => 'client_credentials'
                ]),
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            //接口文档与实际返回数据状态码不一致，此处先兼容
            if ($response->getStatusCode() != 200 && $response->getStatusCode() != 201) {
                Log::info('40Seas API Authentication failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info('40Seas API Authentication failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas authentication: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 创建买家信息
     * @param string $country
     * @param string $type
     * @param string $code
     * @param string $displayName
     * @param string $externalId
     * @return array|bool
     */
    public function createBuyer(string $country, string $type, string $code, string $displayName, string $externalId): array|bool
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/v1/buyers', [
                'body' => json_encode([
                    'address' => [
                        'country' => $country, // 国家二字码
                    ],
                    'identification' => [
                        [
                            'type' => $type, // 企业国家类型
                            'code' => $code,//企业编号
                        ]
                    ],
                    'displayName' => $displayName, // 买家显示名称 // 尽量唯一，偶尔会判断出重复
                    'externalId' => $externalId, // 外部唯一标识符
                ]),
                'headers' => [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() != 201) {
                Log::info('40Seas API Create Buyer failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info('40Seas API Create Buyer failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas Create Buyer: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取买家信息
     *
     * @param string $id 买家ID
     * @return array|bool 成功时返回买家信息数组，失败时返回false
     */
    public function getBuyer(string $id): array|bool
    {
        try {
            $response = $this->client->request('GET', $this->baseUrl . '/v1/buyers/' . $id);

            if ($response->getStatusCode() != 200) {
                Log::info('40Seas API Get Buyer failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info('40Seas API Get Buyer failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas Get Buyer: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 创建支付订单
     * @param string $externalId
     * @param string $buyer
     * @param float $amount
     * @param string $currency
     * @param string $status
     * @return false|mixed
     */
    public function checkout(string $externalId, string $buyer, float $amount, string $currency, string $status): array|bool
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/v1/checkout', [
                'body' => json_encode([
                    'externalId' => $externalId,
                    'buyer' => $buyer,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $status,
                ]),
                'headers' => [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() != 201) {
                Log::info('40Seas API Checkout failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info('40Seas API Checkout failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas Checkout: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 创建支付意图
     * @param string $checkoutId 结账ID
     * @return array|bool
     */
    public function createIntentCheckout(string $checkoutId): array|bool
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/v1/intents/checkout', [
                'body' => json_encode([
                    'checkout' => $checkoutId,
                ]),
                'headers' => [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() != 201) {
                Log::info('40Seas API Create Intent Checkout failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info('40Seas API Create Intent Checkout failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas Create Intent Checkout: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 创建发票支付
     * @param string $currency
     * @param string $buyer
     * @param float $amount
     * @param string $externalId
     * @param string $dueDate
     * @param string $issueDate
     * @return array|bool
     */
    public function createInvoice(string $currency, string $buyer, float $amount, string $externalId, string $dueDate = '', string $issueDate = ''): array|bool
    {
        try {

            $data = [
                'currency' => $currency,
                'buyer' => (string)$buyer,
                'amount' => $amount,
                'externalId' => $externalId,
            ];

            if (!empty($dueDate)) {
                $data['dueDate'] = $dueDate;
            }

            if (!empty($issueDate)) {
                $data['issueDate'] = $issueDate;
            }

            $response = $this->client->request('POST', $this->baseUrl . '/v1/invoices', [
                'body' => json_encode($data),
                'headers' => [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() != 201) {
                Log::info('40Seas API Create Invoice failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info('40Seas API Create Invoice failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas Create Invoice: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 标记发票为已支付
     * @param string $invoiceId 发票ID
     * @param float $amount 支付金额
     * @param string $paymentDate 支付日期
     * @return array|bool
     */
    public function markInvoiceAsPaid(string $invoiceId, float $amount, string $paymentDate): array|bool
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/v1/invoices/' . $invoiceId . '/mark-as-paid', [
                'body' => json_encode([
                    'amount' => $amount,
                    'paymentDate' => $paymentDate,
                ]),
                'headers' => [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() != 200 && $response->getStatusCode() != 201) {
                Log::info('40Seas API Mark Invoice As Paid failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info('40Seas API Mark Invoice As Paid failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas Mark Invoice As Paid: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * 设置供应商账期
     * @param string $buyer 买家ID
     * @param string $currency 币种
     * @param float $creditLimit 额度
     * @param string $decisionNote 决策备注
     * @param string $provider 额度提供方（固定）
     * @param string $status 状态（固定）
     * @return array|bool
     */
    public function createCreditLine(string $buyer, float $creditLimit, string $currency = 'USD', string $decisionNote = '', string $provider = 'supplier', string $status = 'approved'): array|bool
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/v1/creditline', [
                'body' => json_encode([
                    'buyer' => (string)$buyer,
                    'currency' => $currency,
                    'result' => [
                        'status' => $status,
                        'creditLimit' => $creditLimit,
                        'decisionNote' => $decisionNote,
                    ],
                    'provider' => $provider,
                ]),
                'headers' => [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() != 201) {
                Log::info('40Seas API Create Credit Line failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info('40Seas API Create Credit Line failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas Create Credit Line: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新供应商账期
     * @param string $creditLineId 信用额度ID
     * @param string $buyer 买家ID
     * @param string $currency 币种
     * @param float $creditLimit 额度
     * @param string $decisionNote 决策备注
     * @param string $provider 额度提供方
     * @param string $status 状态
     * @return array|bool
     */
    public function updateCreditLine(string $creditLineId, string $buyer, string $currency, float $creditLimit, string $decisionNote = '', string $provider = 'supplier', string $status = 'approved'): array|bool
    {
        try {
            $response = $this->client->request('PUT', $this->baseUrl . '/v1/creditline/' . $creditLineId, [
                'body' => json_encode([
                    'buyer' => $buyer,
                    'currency' => $currency,
                    'result' => [
                        'status' => $status,
                        'creditLimit' => $creditLimit,
                        'decisionNote' => $decisionNote,
                    ],
                    'provider' => $provider,
                ]),
                'headers' => [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() != 200) {
                Log::info('40Seas API Update Credit Line failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info('40Seas API Update Credit Line failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas Update Credit Line: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * 删除供应商账期
     * @param string $creditLineId 信用额度ID
     * @return bool
     */
    public function deleteCreditLine(string $creditLineId): bool
    {
        try {
            $response = $this->client->request('DELETE', $this->baseUrl . '/v1/creditline/' . $creditLineId, [
                'headers' => [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() != 200 && $response->getStatusCode() != 204) {
                Log::info('40Seas API Delete Credit Line failed with status code: ' . $response->getStatusCode());
                return false;
            }

            return true;
        } catch (GuzzleException $e) {
            Log::info('40Seas API Delete Credit Line failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::info('Unexpected error during 40Seas Delete Credit Line: ' . $e->getMessage());
            return false;
        }
    }

}
