<?php

namespace App\Models;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\Scope\CompanyScope;
use App\Models\Traits\Basis;
use App\Services\CurlClient;
use Exception;
use Illuminate\Support\Facades\Log;

class TrackGeoLocation extends Model
{
    use Basis;

    public const EU_BASE_URL = 'http://photon.komoot.de/api/?q=';

    protected $table = 'dsp_track_geo';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public static function dealTrackInfo(array $info): array
    {
        $data = $info;

        $start = null;
        $end = null;

        foreach ($data as $key => $value) {
            $contextHash = md5($value['context']);
            $result = null;
            $record = self::withoutGlobalScope(CompanyScope::class)->where('context', $contextHash)->first();
            if ($record) {
                $result = [
                    'lng' => $record->longitude,
                    'lat' => $record->latitude,
                ];
                $data[$key]['location'] = $result;
            } else {
                //对于上次查询没有的结果应该加锁,一定时间内不可再次请求
                $result = self::getLocationUseContext($value['context']);

                if ($result) {
                    self::create([
                        'context' => $contextHash,
                        'latitude' => $result['lat'],
                        'longitude' => $result['lng'],
                    ]);
                }
            }
            if ($result) {
                if (!$end) {
                    $end = $result['lat'] . ',' . $result['lng'];
                }
                $start = $result['lat'] . ',' . $result['lng'];
            }
            $data[$key]['location'] = $result;
        }

        $res['data'] = $data;
        $res['start'] = $start;
        $res['end'] = $end;

        Log::debug('处理完成的结果为:', $res);

        return $res;
    }

    /**
     * @param $context
     * @param  bool  $all
     * @return array|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getLocationUseContext($context, bool $all = false)
    {
        $curl = new CurlClient();

        $key = config('jiyun.tencent_map_key');

        $context = urlencode($context);

        $url = "https://apis.map.qq.com/ws/geocoder/v1/?address=$context&key=$key";

        $data = $curl->get($url);

        app('log')->info('查询 tencent 返回结果为:', $data ?? []);

        if ($data && (int) ($data['status']) === 0) {
            if ($all) {
                return $data['result'];
            }

            return $data['result']['location'];
        }

        return null;
    }

    public static function getEuLocationUseContext($context)
    {
        $curl = new CurlClient();

        $res = $curl->get(self::EU_BASE_URL . $context);

        app('log')->debug('eu 查询地址返回的结果为:', $res ?? []);

        if (!$res || count($res['features']) === 0) {
            return null;
        }

        if (count($res['features']) > 3) {
            throw new AccidentException('地址不够精确,请检查', Code::OPERATE_FAIL);
        }

        return self::dealEuApiRet($res['features'][0]);
    }

    /**
     * 处理 eu api 的结果为合适的格式
     */
    public static function dealEuApiRet(array $origin): array
    {
        return [
            'lat' => $origin['geometry']['coordinates'][1],       // 纬度
            'lng' => $origin['geometry']['coordinates'][0],        // 经度
        ];
    }
}
