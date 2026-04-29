<?php

namespace App\Services\Client;

use App\Models\AdminLanguages;
use App\Models\SuperAdminStringTranslation;
use App\Services\C2TTranslate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StringTranslationService
{
    /**
     * 为指定公司指定语言指定来源生成语言翻译数组...
     */
    public static function generateJson($companyID, $locale = 'zh_CN', $source = SuperAdminStringTranslation::PC)
    {
        $cacheKey = 'StringTranslation_' . $companyID . '_' . $locale . '_' . $source;
        $flushKey = 'StringTranslation_' . $companyID . '_flush';

        if (Cache::get($flushKey)) {
            Cache::forget($cacheKey);
            Cache::forget($flushKey);
        }

        return Cache::get($cacheKey, function () use ($companyID, $cacheKey, $locale, $source) {
                //查看公司支持的语言
                if ($companyID) {
                    $languages = AdminLanguages::where('company_id', $companyID)->pluck('language_code');

                    if (!$languages->contains($locale)) {
                        $locale = 'zh_CN';
                    }
                }

                app()->setlocale($locale); //设置语言环境

                $stringTranslations = SuperAdminStringTranslation::where('source', $source)->get();

                $result = [];

                foreach ($stringTranslations as $translation) {
                    $result[$translation->key] = $translation->translation;
                }

                Cache::forever($cacheKey, $result);

                return $result;
            });
    }

    /**
     * API字符串翻译
     *
     * @param $key
     * @param array $replace
     * @param null $locale
     * @return mixed|string|string[]
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function apiTran($key, $replace = [], $locale = null)
    {
        if (!$locale) {
            $locale = app()->getLocale();
        }

        $replace = self::sortReplacements($replace);

        foreach ($replace as $rKey => $value) {
            $key = str_replace(
                [':'.$rKey, ':'.Str::upper($rKey), ':'.Str::ucfirst($rKey)],
                [$value, Str::upper($value), Str::ucfirst($value)],
                $key
            );
        }

        $stringTranslation = SuperAdminStringTranslation::getStringTranslation(
            $key,
            SuperAdminStringTranslation::API
        );

        if (! $stringTranslation || $stringTranslation === $key) {
            $tran = $key
                ? ((!str_contains(trans('msg.' . $key, $replace), 'msg.'))
                    ? trans('msg.'.$key, $replace)
                    : __($key, $replace))
                : $key;

            if ($tran === $key && ($locale === 'zh_TW' || $locale === 'zh_HK')) {
                return (new C2TTranslate())->c2t($key);
            }

            return $tran;
        }

        return is_array($stringTranslation)
            ? ($stringTranslation[$locale] ?? $key)
            : $stringTranslation;
    }

    /**
     * Sort the replacements array.
     *
     * @param  array  $replace
     * @return array
     */
    protected static function sortReplacements(array $replace)
    {
        return collect($replace)->sortBy(function ($value, $key) {
            return mb_strlen($key) * -1;
        })->all();
    }
}
