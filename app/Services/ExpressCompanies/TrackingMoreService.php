<?php

/**
 * @Author: h9471
 * @Created: 2020/1/2 15:27
 */

namespace App\Services\ExpressCompanies;

use App\Lib\Code;
use App\Models\ApiTrackingConfig;
use App\Models\Order;
use App\Models\TrackingMoreLog;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class TrackingMoreService
{
    public const ROUTE_CARRIERS = 'carriers/';
    public const ROUTE_CARRIERS_DETECT = 'carriers/detect';
    public const ROUTE_TRACKINGS = 'trackings';
    public const ROUTE_LIST_ALL_TRACKINGS = 'trackings/get';
    public const ROUTE_CREATE_TRACKING = 'trackings/post';
    public const ROUTE_TRACKINGS_BATCH = 'trackings/batch';
    public const ROUTE_TRACKINGS_REALTIME = 'trackings/realtime';

    public $url;

    public $method;

    protected $apiKey;

    protected $client;

    public $companyId;

    public function __construct($apiKey = null, $companyId = null)
    {
        $this->companyId = $companyId;

        $this->client = new Client();

        $this->url = config('tracking.trackingMore.url');
        $this->apiKey = $apiKey ?? $this->getTrackingMoreKey();
    }

    /**
     * @return string
     * @throws Exception
     */
    public function realtimeInfo()
    {
        $json = [
            'tracking_number' => '778374883667',
            'carrier_code' => 'fedex',
        ];

        try {
            $response = $this->client->request(
                'POST',
                $this->url . '/' . self::ROUTE_TRACKINGS_REALTIME,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Tracking-Api-Key' => $this->apiKey,
                        'Lang' => 'CN',
                    ],
                    'json' => $json,
                ]
            );
        } catch (GuzzleException $exception) {
            throw new AccidentException($exception->getMessage(), Code::OPERATE_FAIL);
        }

        return $response->getBody()->getContents();
    }

    /**
     * @param string $carrierCode
     * @param string $trackingNumber
     * @param string $lang
     * @return bool
     */
    public function create(string $carrierCode, string $trackingNumber, string $lang = 'cn'): bool
    {
        $data = [
            [
                'tracking_number' => $trackingNumber,
                'carrier_code' => $carrierCode,
                'lang' => $lang,
            ],
        ];

        $key = "TRACKINGMORE:CREATED:{$carrierCode}_{$trackingNumber}_{$lang}";

        /*if (Cache::get($key) === 1) {
            return true;
        }*/

        try {
            $response = $this->client->post(
                $this->url . '/' . self::ROUTE_TRACKINGS_BATCH,
                [
                    'headers' => [
                        'Tracking-Api-Key' => $this->apiKey,
                    ],
                    'json' => $data,
                ]
            );
        } catch (GuzzleException $exception) {
            info('tracking more 请求出错' . $exception->getMessage());

            return false;
        }

        $body = $response->getBody()->getContents();
        info('Tracking创建参数：', $data);
        info('Tracking创建：', ['body' => $body]);

        $response = json_decode($body, true);

        if (isset($response['meta']['code']) && in_array($response['meta']['code'], [200, 201])) {
            Cache::put($key, 1, Carbon::now()->addMonth());

            return true;
        }
        return false;
    }

    /**
     * @param string $carrierCode
     * @param string $trackingNumber
     * @param string $lang
     * @return array|array[]
     */
    public function get(string $carrierCode, string $trackingNumber, string $lang = 'cn')
    {
        $carrierCode = urlencode($carrierCode);
        $data = implode('/', compact('carrierCode', 'trackingNumber', 'lang'));

        try {
            $response = $this->client->request(
                'GET',
                $this->url . '/' . self::ROUTE_TRACKINGS . '/' . $data,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Tracking-Api-Key' => $this->apiKey,
                    ],
                ]
            );
        } catch (GuzzleException $exception) {
            info('物流查询报错,错误信息为:' . $exception->getMessage());
            info('物流查询报错,错误信息为:' . $exception->getTraceAsString());
            return [];
        }

        $response = json_decode($response->getBody()->getContents());

        info('TrackingMore 查询：', (array)$response);

        if (isset($response->meta) && $response->meta->code === 200 && $response->data) {
            //app('log')->debug('当前返回的数据为:' . json_encode($response));
            return [
                [
                    'status' => $response->data->status,
                    'carrier_code' => $response->data->carrier_code,
                    'tracking_number' => $response->data->tracking_number,
                    'track_info' => $response->data->origin_info->trackinfo ?? [],
                ]
            ];
        } else {
            return [];
        }
    }

    public function detect(string $trackingNumber)
    {
        if (strlen($trackingNumber) <= 6) {
            return [];
        }

        $data = [
            'tracking_number' => $trackingNumber,
        ];

        $key = "TRACKINGMORE:DETECT:{$trackingNumber}";

        $res = Cache::get($key);
        if ($res) {
            return $res;
        }

        try {
            $response = $this->client->request(
                'GET',
                $this->url . '/' . self::ROUTE_CARRIERS_DETECT,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Tracking-Api-Key' => $this->apiKey,
                        'lang' => 'cn',
                    ],
                    'json' => $data,
                ]
            );
        } catch (GuzzleException $exception) {
            app('log')->debug('请求出错,错误原因为:' . $exception->getTraceAsString());

            return [];
        }

        $response = json_decode($response->getBody()->getContents());

        if (isset($response->meta) && $response->meta->code === 200) {
            $res = collect([$response->data])->flatten()->all();

            Cache::put($key, $res, Carbon::now()->addMonth());

            return $res;
        }
        app('log')->debug('返回值不是 200');
        app('log')->debug('返回的值为:' . json_encode($response));
        return [];
    }

    public function callback($data)
    {
        if (empty($data['code']) || !in_array($data['code'], [200, 201])) {
            return false;
        }

        if (empty($data['data'])) return false;

        return $this->updateOrderTracking($data['data']);

    }


    protected function updateOrderTracking($result)
    {
        $order = Order::query()
            ->where('company_id', Order::getCompanyId())
            ->where('logistics_sn', $result['tracking_number'])
            ->where('tracking_type', ApiTrackingConfig::TYPE_51_TRACKING)
            ->first();

        if (!$order) return true;

        return DB::transaction(function () use ($result, $order) {

            $companyId = Order::getCompanyId();

            $order->update(['third_tracking_status' => $result['delivery_status']]);

            TrackingMoreLog::query()->create([
                'company_id' => $companyId,
                'type' => TrackingMoreLog::TYPE_ORDER,
                'express_num' => $result['tracking_number'],
                'status' => $result['delivery_status']
            ]);

            return true;
        });
    }


    /**
     * @return $this
     */
    public function useSystemConfig()
    {
        $this->apiKey = config('jiyun.tracking.51tracking_key');

        return $this;
    }

    /**
     * @return \Illuminate\Config\Repository|mixed
     */
    protected function getTrackingMoreKey()
    {
        if (!empty($this->companyId)) {
            $config = ApiTrackingConfig::query()->where('company_id', $this->companyId)->first();
        } else {
            $config = ApiTrackingConfig::query()->first();
        }

        return $config?->getAttributeValue('51tracking_app_key') ?: '';
    }
}
