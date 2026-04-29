<?php
namespace App\Services\ThirdPartyApi\Brevo;

use App\Models\ThirdPartySystemConfigModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;

class BrevoService
{
    protected RequestApi $request;

    protected ThirdPartySystemConfigModel $config;


    public function __construct($config)
    {
        $this->config = $config;
        $this->request = new RequestApi($config);
    }

    /**
     * @param $customer
     */
    public function createOrUpdateContact($customer)
    {
        //根据邮箱查询联系人
        $email = $customer->custom_email ?? '';
        if (empty($email)) {
            return false;
        }

        $contacts = $this->request->getContact($email);

        $list = $this->config->extend['list'] ?? '';
        $listIds = array_map('intval', explode(',', $list));
        $params = [
            'email' => $email,
            'attributes' => [
                'FIRSTNAME' => $customer->custom_name,
                'LASTNAME' => '',
                'SMS' => $customer->custom_phone ? '+' . $customer->phone_area_code . $customer->custom_phone : '',
            ],
            'ext_id' => (string)$customer->id,
            'listIds' => $listIds,
        ];
        //存在则更新,否则新增
        if ($contacts['id'] ?? '') {
            return $this->request->updateContact($email, $params);
        }

        return $this->request->createContact($params);
    }

    /**
     * @param string $identifier
     * @return bool
     */
    public function getContact(string $identifier): bool
    {
        info('brevo-getContact：', [$identifier]);

        try {
            $response = $this->client->request(
                'GET',
                $this->url . self::ROUTE_DETAIL_CONTACTS . $identifier,
                [
                    'headers' => [
                        'accept' => 'application/json',
                        'api-key' => $this->apiKey,
                    ]
                ]
            );
        } catch (GuzzleException $exception) {
            info('brevo-getContact error:' . $exception->getMessage());

            return false;
        }

        $body = $response->getBody()->getContents();

        info('17track 创建：', ['body' => $body]);

        $response = json_decode($body);
        dd($response->id);

        if (isset($response->code) && $response->code === 0) {
            if (!empty($response->data->rejected)) {
                info('17track 重新注册：', ['body' => $body]);

                return $this->registerWithCode($trackingNumber, $carrierCode);
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $trackingNumber
     * @param string $carrierCode
     * @return bool
     */
   /* public function register(string $trackingNumber, string $carrierCode): bool
    {
        $data = [
            [
                'number' => $trackingNumber,
            ],
        ];

        info('17track 创建参数：', $data);

        try {
            $response = $this->client->request(
                'POST',
                $this->url . '/' . self::ROUTE_REGISTER,
                [
                    'headers' => [
                        '17token' => $this->apiKey,
                    ],
                    'json' => $data,
                ]
            );
        } catch (GuzzleException $exception) {
            info('17track 请求出错' . $exception->getMessage());

            return false;
        }

        $body = $response->getBody()->getContents();

        info('17track 创建：', ['body' => $body]);

        $response = json_decode($body);

        if (isset($response->code) && $response->code === 0) {
            if (!empty($response->data->rejected)) {
                info('17track 重新注册：', ['body' => $body]);

                return $this->registerWithCode($trackingNumber, $carrierCode);
            }

            return true;
        }

        return false;
    }*/

    /**
     * @param string $trackingNumber
     * @param string $carrierCode
     * @return bool
     */
    public function registerWithCode(string $trackingNumber, string $carrierCode): bool
    {
        $data = [
            [
                'number' => $trackingNumber,
                'carrier' => $carrierCode,
            ],
        ];

        info('17track 创建参数：', $data);

        try {
            $response = $this->client->request(
                'POST',
                $this->url . '/' . self::ROUTE_REGISTER,
                [
                    'headers' => [
                        '17token' => $this->apiKey,
                    ],
                    'json' => $data,
                ]
            );
        } catch (GuzzleException $exception) {
            info('17track 请求出错' . $exception->getMessage());

            return false;
        }

        $body = $response->getBody()->getContents();

        $response = json_decode($body, true);

        info('17track 创建：', $response);

        if (isset($response['code']) && $response['code'] === 0) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function verify(): bool
    {
        $data = [
            'page_no' => 1,
        ];

        info('17track 测试参数：', $data);

        try {
            $response = $this->client->request(
                'POST',
                $this->url . '/' . self::ROUTE_TRACK_LIST,
                [
                    'headers' => [
                        '17token' => $this->apiKey,
                    ],
                    'json' => $data,
                ]
            );
        } catch (GuzzleException $exception) {
            info('17track 请求出错' . $exception->getMessage());

            return false;
        }

        $body = $response->getBody()->getContents();

        info('17track 测试：', ['body' => $body]);

        $response = json_decode($body);

        if (isset($response->code) && $response->code === 0) {
            return true;
        }

        return false;
    }

    /**
     * @param string $carrierCode
     * @param string $trackingNumber
     * @return array|array[]
     */
    public function get(string $trackingNumber, string $carrierCode)
    {
        info('17track 查询数据', [
            'number' => $trackingNumber,
            'carrier' => $carrierCode,
        ]);

        $data = [
            [
                'number' => $trackingNumber,
                'carrier' => $carrierCode,
            ]
        ];

        info('17track 请求：', $data);

        try {
            $response = $this->client->request(
                'POST',
                $this->url . self::ROUTE_GET,
                [
                    'headers' => [
                        '17token' => $this->apiKey,
                    ],
                    'json' => $data,
                ]
            );
        } catch (GuzzleException $exception) {
            info('物流查询报错,错误信息为:' . $exception->getMessage());
            info('物流查询报错,错误信息为:' . $exception->getTraceAsString());
            return [];
        }

        $response = json_decode($response->getBody()->getContents(), true);

        info('17track 查询：', $response);

        if ($response['code'] === 0 && $response['data']) {
            $data = $response['data']['accepted'];
            if (empty($data)) {
                $data = $response['data']['rejected'];
            }

            //返回第一个轨迹信息
            if ($data) {
                return current($data);
            }
        } else {
            return [];
        }

        return [];
    }

    /**
     * @param string $number
     * @return false|string
     */
    public function carrier(string $number)
    {
        $data = [
            [
                'number' => $number,
            ],
        ];

        $key = "17TrackCarrier:$number";

        if ($carrier = Cache::get($key)) {
            return $carrier;
        }

        info('17track 创建参数：', $data);

        try {
            $response = $this->client->request(
                'POST',
                $this->url . '/' . self::ROUTE_CARRIER_IDENTIFY,
                [
                    'headers' => [
                        '17token' => $this->apiKey,
                    ],
                    'json' => $data,
                ]
            );
        } catch (GuzzleException $exception) {
            info('17track 请求出错' . $exception->getMessage());

            return false;
        }

        $body = $response->getBody()->getContents();
        $response = json_decode($body, true);

        info('17track carrier：', ['body' => $response]);

        if (isset($response['code']) && $response['code'] === 0) {
            $data = $response['data']['accepted'];

            if ($data) {
                if ($data[0]['carrier'] ?? '') {
                    Cache::put($key, $data[0]['carrier'], now()->addMonths(3));

                    return $data[0]['carrier'];
                }
            }
        }

        return false;
    }

    /**
     * @return \Illuminate\Config\Repository|mixed
     */
    protected function getAppKey()
    {
        $brevo = ThirdPartySystemConfigModel::getBrevoConfig();

        return $brevo->app_key ?? '';
    }
}
