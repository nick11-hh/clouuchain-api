<?php

namespace App\Services\Tracking\Track17;

use App\Models\ApiTrackingConfig;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 17Track Api对接服务类
 * Class Track17Service
 * @package App\Services\Tracking\Track17
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/11/9 10:39
 */
class Track17Api
{
    public const ROUTE_GET = '/gettrackinfo';
    public const ROUTE_REGISTER = '/register';
    public const ROUTE_TRACK_LIST = '/gettracklist';
    public const ROUTE_CARRIER_IDENTIFY = '/carrierIdentify';

    public string $url = 'https://api.17track.net/track/v2.2';

    public string $method;

    protected string $apiKey;

    protected Client $client;

    public function __construct($apiKey = null)
    {
        $this->client = new Client([
            'verify' => false
        ]);

        $this->apiKey = $apiKey ?? $this->getAppKey();
    }

    /**
     * 注册物流单号
     * @param string $trackingNumber
     * @param string $carrierCode
     * @return bool
     */
    public function register(string $trackingNumber, string $carrierCode): bool
    {
        $data = [
            [
                'number' => $trackingNumber,
//                'lang' => 'en', //默认英文
            ],
        ];

        info('17track 创建参数：', $data);

        try {
            $response = $this->client->request(
                'POST',
                $this->url  . self::ROUTE_REGISTER,
                [
                    'headers' => [
                        '17token' => $this->apiKey,
                    ],
                    'json' => $data,
                ]
            );
            info('17track 创建：', ['body' => $body, 'response' => $response]);
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
    }

    /**
     * 重新注册物流单号
     * @param string $trackingNumber
     * @param string $carrierCode
     * @param string $language
     * @return bool
     */
    public function registerWithCode(string $trackingNumber, string $carrierCode, string $language = ''): bool
    {
        $data = [
            [
                'number' => $trackingNumber,
                'carrier' => $carrierCode,
//                'lang' => $language === 'zh_CN' || empty($language) ? '' : 'en',
            ],
        ];

        info('17track 创建参数：', $data);

        try {
            $response = $this->client->request(
                'POST',
                $this->url . self::ROUTE_REGISTER,
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
     * 获取注册单号列表
     * @return bool
     */
    public function getList(): bool
    {
        $data = [
            'page_no' => 1,
        ];

        info('17track 测试参数：', $data);

        try {
            $response = $this->client->request(
                'POST',
                $this->url . self::ROUTE_TRACK_LIST,
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
     * 获取物流单号详情
     * @param string $trackingNumber
     * @param string $carrierCode
     * @param bool $isReturnTrackList 是否返回物流轨迹 false=只返回状态 true=返回轨迹列表信息
     * @return array|array[]
     */
    public function get(string $trackingNumber, string $carrierCode, bool $isReturnTrackList = false)
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

        info('17track 请求参数：', $data);

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

//        info('17track 查询结果：', $response);

        if ($response['code'] === 0 && $response['data']) {
            return $this->handleGetTrackInfoResult($response, $isReturnTrackList);
        } else {
            return [];
        }
    }

    /**
     * 获取物流商代码
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
                $this->url . self::ROUTE_CARRIER_IDENTIFY,
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
     * 使用系统密钥
     * @return $this
     */
    public function useSystemConfig()
    {
        $this->apiKey = config('tracking.17track.key');

        return $this;
    }

    /**
     * 获取配置密钥
     * @return \Illuminate\Config\Repository|mixed
     */
    protected function getAppKey()
    {
        $config = ApiTrackingConfig::query()->first();

        return $config?->getAttributeValue('17track_app_key') ?: '';
    }

    /**
     * 处理物流查询轨迹结果
     * @param $response
     * @param bool $isReturnTrackList
     * @return array|false|mixed|void
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/8 15:08
     */
    public function handleGetTrackInfoResult($response, $isReturnTrackList = false)
    {
        $data = $response['data']['accepted'];

        if ($isReturnTrackList) {
            $item = $data[0] ?? [];
            if ($item) {
                $trackList = [];
                $trackInfo = $item['track_info'];
                $trackingProvider = $trackInfo['tracking']['providers'][0];

                if (!empty($trackingProvider)) {
                    $trackList = collect($trackingProvider['events'])
                        ->map(function ($value) {
                            if (isset($value['description_translation'])) {
                                $description = $value['description_translation']['description'] ?? '-';
                            } else {
                                $description = $value['description'];
                            }

                            $context = $value['location'] && !str_contains($description, "【{$value['location']}】")
                                ? sprintf('【%s】 %s', $value['location'], $description)
                                : $description;

                            return [
                                'datetime'  => Carbon::parse($value['time_iso'])->toDateTimeString(),
                                'location'  => $value['location'],
                                'context'   => $context,
                            ];
                        })->values()->unique('context')->all();
                }
                $latestSyncTime = Carbon::createFromFormat("Y-m-d\TH:i:s\Z", $trackingProvider['latest_sync_time'], 'UTC')
                    ->setTimezone(config('app.timezone'));

                return [
                    'number'                => $item['number'], //快递单号
                    'carrier'               => $item['carrier'], //物流商代码
                    'shipping_info'         => $trackInfo['shipping_info'], //地区信息，参考17track API文档
                    'latest_status'         => $trackInfo['latest_status'], //最新状态信息
                    'provider'              => $trackingProvider['provider'], //快递承运商信息
                    'latest_sync_time'      => $latestSyncTime->format("Y-m-d H:i:s"), //最新同步时间
                    'latest_sync_status'    => $trackingProvider['latest_sync_status'] !== "Success" ? 0 : 1, //同步状态
                    'track_list'            => $trackList, //轨迹信息(字段参考上方)
                ];

            }

            return [];
        } else {
            if (empty($data)) {
                $data = $response['data']['rejected'];
            }

            // 返回第一个轨迹信息
            if ($data) {
                return current($data);
            }
        }
    }
}
