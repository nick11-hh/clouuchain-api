<?php

/**
 * @Author: h9471
 * @Created: 2020/3/25 15:14
 */

namespace App\Services\Base;

use App\Lib\Code;
use App\Models\PaypalPayment;
use Exception;
use Illuminate\Http\Request;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Omnipay;
use Omnipay\PayPal\Message\RestResponse;
use Omnipay\PayPal\RestGateway;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;
use App\Exceptions\AccidentException;

class PayPalService
{

    /**
     * @var RestGateway
     */
    protected $gateway;

    /**
     * @var string
     */
    protected $webHookId;

    /**
     * @var bool
     */
    protected $sandbox = false;

    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var string
     */
    protected $clientSecret;

    public function __construct()
    {
        $this->withFileConfig();
    }

    public function withFileConfig(): self
    {
        // $this->sandbox = true;
        // $this->clientId = 'ATNQTkSRsI1eEg9HFF_K8v_LHevgwsN9W0W_jPIU--o8t07Hp5gre_s9IAOjJMZ_xrmTgQSRQEgSiKiV';
        // $this->clientSecret = 'EODa_6mRDREyX7_4Es8XoC8GU1Ssiv7IdcbfXvfW4s8BWPtoJcQDKt1nttEh1_fqAsIz7lbfJDNXWTRv';

        $paypal = PaypalPayment::query()->first();
        $this->sandbox = (boolean)($paypal->sandbox ?? 0);
        $this->clientId = $paypal->client_id ?? '';
        $this->clientSecret = $paypal->secret ?? '';

        return $this;
    }

    public function withDBConfig($testMode = false ): self
    {
        $this->initGateWay($testMode);
        $this->gateway->getToken();
        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function pay($amount, $currency, $returnUrl)
    {
        try {
            $this->gateway->setCurrency($currency);
            /** @var RestResponse $res */
            $res = $this->gateway->purchase([
                'returnUrl' => $returnUrl,
                'cancelUrl' => $returnUrl,
            ])->setAmount($amount)->send();

        } catch (InvalidResponseException $exception) {
            info('PayPal 创建支付请求失败', ['message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()]);

            throw new AccidentException('PayPal 创建支付请求失败', Code::OPERATE_FAIL);
        }

        if ($res->isSuccessful()) {
            $data = $res->getData();
            info('PayPal 创建支付请求', [$data]);

            return $data + ['service_fee' => $amount];
        }
        info('PayPal 创建支付请求失败', ['message' => $res->getMessage()]);
        throw new AccidentException('PayPal 创建支付请求失败', Code::OPERATE_FAIL);
    }

    /**
     * @param string $payerId
     * @param string $payId
     * @return mixed
     * @throws Exception
     */
    public function completePay(string $payerId, string $payId)
    {
        try {
            /** @var RestResponse $res */
            $res = $this->gateway->completePurchase()
                ->setPayerId($payerId)
                ->setTransactionReference($payId)
                ->send();
        } catch (InvalidResponseException $exception) {
            info('PayPal 完成支付请求失败', ['message' => $exception->getMessage()]);

            throw new AccidentException('PayPal 支付请求失败');
        }

        if ($res->isSuccessful()) {
            return $res->getData();
        }
        info('PayPal 完成支付请求失败', ['message' => $res->getMessage()]);
        throw new AccidentException('PayPal 完成支付请求失败');
    }

    /**
     * @param  string  $payId
     * @return mixed
     * @throws AccidentException
     */
    public function paymentCheck(string $payId)
    {
        try {
            /** @var RestResponse $res */
            $res = $this->gateway->fetchPurchase()
                ->setTransactionReference($payId)
                ->send();
        } catch (InvalidResponseException $exception) {
            info('PayPal 查询支付请求失败', ['message' => $exception->getMessage()]);

            throw new AccidentException('PayPal 查询支付请求失败');
        }

        if ($res->isSuccessful()) {
            return $res->getData();
        }
        info('PayPal 查询支付请求失败', ['message' => $res->getMessage()]);
        throw new AccidentException('PayPal 查询支付请求失败');
    }

    /**
     * @param  string  $transactionId
     * @return mixed
     */
    public function transactionCheck(string $transactionId)
    {
        try {
            /** @var RestResponse $res */
            $res = $this->gateway->fetchTransaction([
                'transactionReference' => $transactionId,
            ])->send();
        } catch (InvalidResponseException $exception) {
            info('PayPal 交易查询请求失败', ['message' => $exception->getMessage()]);

            throw new AccidentException('PayPal  交易查询请求失败');
        }

        if ($res->isSuccessful()) {
            return $res->getData();
        }
        info('PayPal 交易查询请求失败', ['message' => $res->getMessage()]);
        throw new AccidentException('PayPal 交易查询请求失败');
    }

    /**
     * @param  Request  $request
     * @return bool
     * @throws PayPalConnectionException
     */
    public function validateWebhook(Request $request)
    {
        $headers = array_change_key_case($request->headers->all(), CASE_UPPER);

        $body = $request->getContent();

        try {
            $data = $this->makeWebhookSign($headers, $body)->post($this->makeApiContext());
        } catch (PayPalConnectionException $exception) {
            info('PayPal webhook回调请求失败');

            throw $exception;
        }

        if ($data->getVerificationStatus() === 'SUCCESS') {
            return true;
        }
        info('PayPal WebHook回调检查失败', $data->toArray());

        return false;
    }

    /**
     * @param  array  $headers
     * @param  string  $body
     * @return VerifyWebhookSignature
     */
    protected function makeWebhookSign(array $headers, string $body)
    {
        $signatureVerification = new VerifyWebhookSignature();
        $signatureVerification->setAuthAlgo($headers['PAYPAL-AUTH-ALGO']);
        $signatureVerification->setTransmissionId($headers['PAYPAL-TRANSMISSION-ID']);
        $signatureVerification->setCertUrl($headers['PAYPAL-CERT-URL']);
        $signatureVerification->setWebhookId($this->webHookId);
        $signatureVerification->setTransmissionSig($headers['PAYPAL-TRANSMISSION-SIG']);
        $signatureVerification->setTransmissionTime($headers['PAYPAL-TRANSMISSION-TIME']);
        $signatureVerification->setRequestBody($body);

        return $signatureVerification;
    }

    /**
     * @return ApiContext
     */
    protected function makeApiContext()
    {
        $context = new ApiContext(
            new OAuthTokenCredential(
                $this->clientId,
                $this->clientSecret
            )
        );

        $context->setConfig(['mode' => $this->getMode()]);

        return $context;
    }

    protected function getMode()
    {
        return $this->sandbox ? 'live' : 'sandbox';
    }

    protected function initConfig()
    {
    }

    protected function initGateWay($testMode = false)
    {
        $this->gateway = Omnipay::create('PayPal_Rest');

        $this->gateway->setTestMode($testMode);
        $this->gateway->setClientId($this->clientId);
        $this->gateway->setSecret($this->clientSecret);
    }
}
