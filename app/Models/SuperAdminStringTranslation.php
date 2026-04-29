<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\BatchUpdate;
use App\Observers\StringTranslationObserver;
use Illuminate\Support\Facades\Cache;
use Spatie\Translatable\HasTranslations;

/**
 * @class SuperAdminStringTranslation 超级管理员端字符串翻译
 */
class SuperAdminStringTranslation extends Model
{
    // 这里的翻译只用于保存,模型返回仍然采用扩展默认的 array
    use Basis, HasTranslations, BatchUpdate;

    public const PC = 1;  // 来源为 pc 端
    public const API = 2;  // 来源为 api 端
    public const MINI = 3;  // 来源为 小程序 端

    //用于翻译
    public $translatable = ['translation'];

    protected $table = 'dsp_sa_string_translation';

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    public function getSourceNameAttribute()
    {
        return self::sourceList()[$this->source] ?? '';
    }

    public static function sourceList()
    {
        return [
            self::PC => __('PC网站'),
            self::API => __('API'),
            self::MINI => __('小程序'),
        ];
    }

    /**
     * @param string $key
     * @param int $source
     * @return int[]|mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function getStringTranslation(string $key, int $source)
    {
        $data = self::getCacheData();

        $translation = collect($data)->filter(function ($v) use ($key, $source) {
            return $v['key'] === $key && $v['source'] === $source;
        })->first();

        if ($translation) {
            return $translation['translation'];
        }

        return $key;
    }

    /**
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function getCacheData()
    {
        $key = 'string-translation-data-'.self::getCompanyId();

        return Cache::driver('file')->get($key, function () use ($key) {
            return self::cacheData($key);
        });
    }

    /**
     * @param string $key
     * @param int $companyId
     * @return array
     */
    public static function cacheData(string $key, int $companyId = 0)
    {
        $data = self::when($companyId, function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->get()->map(function (self $v) {
            return $v->toBaseArray();
        })->all();

        Cache::driver('file')->put($key, $data);

        return $data;
    }

    public static function booted()
    {
        parent::booted();

        static::observe(StringTranslationObserver::class);
    }
}
