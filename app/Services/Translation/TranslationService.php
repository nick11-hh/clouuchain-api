<?php

namespace App\Services\Translation;

use App\Services\Translation\Factory;
use App\Services\Translation\Exceptions\TranslationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class TranslationService
{

    protected $language = '';

    protected $platform = '';

    protected $service = null;

    protected $cache = true;

    protected $cacheHours = 240;  // 240 hours = 10 days

    protected $config = [];

    /**
     * 构造函数
     * @param string $language
     * @param string|null $translationPlatform
     * @return void
     */
    public function __construct($language, $translationPlatform = null )
    {
        $this->language = $language;
        $this->platform = $translationPlatform ?: config('translation.default');

        $this->config = config('translation.translations.' . $this->platform);

        if (empty($this->config)) {
            throw new TranslationException('Translation platform is not supported');
        }

        $this->service = Factory::create($this->config, $language);
    }

    /**
     * 设置语言
     * @param string $language
     * @return $this
     */
    public function setLanguage($language)
    {
        $this->language = $language;
        if ($this->service) {
            $this->service->language = $language;
        }
        return $this;
    }

    /**
     * 翻译
     * @param string|array $text
     * @return string|array
     */
    public function translation($text)
    {
        $text = Arr::wrap($text);
        $result = [];

        $needTranslation = [];
        foreach ($text as $item) {
            if ($this->cache) {
                $translation = $this->getCacheTranslation($item);
                if ($translation) {
                    $result[$item] = $translation;
                    continue;
                }
                $needTranslation[] = $item;
            }
        }
        if (empty($needTranslation)) return $result;
        $translations = $this->service->translation($needTranslation);
        foreach ($translations as $key => $translation) {
            $this->cacheTranslation($item, $translation);
            $result[$needTranslation[$key]] = $translation;
        }

        return $result;
    }


    /**
     * 获取缓存的翻译
     * @param string $text 原文
     * @return string 缓存翻译
     */
    protected function getCacheTranslation($text)
    {
        $cacheKey = $this->getCacheKey($text);
        return Cache::get($cacheKey);
    }

    /**
     * 缓存翻译
     * @param string $text 原文
     * @param string $translationText 翻译结果
     * @return void
     */
    protected function cacheTranslation($text, $translationText)
    {
        if (empty($text) || empty($translationText)) {
            return;
        }

        $cacheKey = $this->getCacheKey($text);
        // 翻译结果可以长期缓存，设置为 10 天
        Cache::put($cacheKey, $translationText, now()->addHours($this->cacheHours));
    }

    /**
     * 生成缓存键
     * @param string $text 原文
     * @return string 缓存键
     */
    protected function getCacheKey($text)
    {
        $language = $this->language;

        // 使用 MD5 生成短键名，避免键名过长
        $textHash = md5($text);

        return "translation:{$language}:{$textHash}";
    }
}
