<?php

/**
 * @Author: h9471
 * @Created: 2020/1/2 15:27
 */

namespace App\Services\ThirdPartyApi;

use App\Models\ApiTrackingConfig;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GeoIpService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function getCountryCityByIp($ip): array
    {
        if (empty($ip)) return [];
        try {
            $response = $this->client->request('GET', "http://ip-api.com/json/{$ip}");
            $result = json_decode($response->getBody()->getContents(), true);
            return [
                'country' => $result['country'] ?? '',
                'city' => $result['city'] ?? '',
                'country_code' => $result['countryCode'] ?? ''
            ];
        } catch (GuzzleException $exception) {
            info('ip-api.com 请求出错' . $exception->getMessage());
            return [];
        }
    }

}
