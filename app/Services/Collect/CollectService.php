<?php

namespace App\Services\Collect;

use App\Lib\Code;
use App\Services\Collect\Platform\Y1688\Y1688Service;
use Exception;
use App\Exceptions\AccidentException;

class CollectService
{
    // 不同采集平台对应的类映射
    const platformClassMap = [
        '1688' => Y1688Service::class
    ];

    protected CollectInterface $service;

    /**
     * @param string $platform
     * @param string $language
     * @throws Exception
     */
    public function __construct(string $platform = '1688', string $language = 'en_US')
    {
        $this->service = $this->getPlatformInstance($platform);
        $this->service->setLanguage($language);
    }

    /**
     * @param $url
     * @param $language
     * @return mixed
     * @throws Exception
     */
    public function getProductDataByUrl($url, $language): mixed
    {
        $platform = $this->analyseUrlPlatform($url);
        if (!$platform) throw new AccidentException('暂不支持该平台产品解析');
        $this->service = $this->getPlatformInstance($platform);;
        $this->service->setLanguage($language);
        return $this->service->getProductDataByUrl($url);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function getProductList($params): mixed
    {
        return $this->service->getProductList($params);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function productImageSearch($params): mixed
    {
        return $this->service->productImageSearch($params);
    }

    /**
     * @param $productId
     * @return mixed
     */
    public function getProductDetail($productId, $transformPrice = true): mixed
    {
        return $this->service->getProductDetail($productId, $transformPrice);
    }

    /**
     * @param $openUid
     * @return mixed
     */
    public function getWangWangNick($openUid)
    {
        return $this->service->getWangWangNick($openUid);
    }

    /**
     * @param $url
     * @return string
     */
    public function analyseUrlPlatform($url): string
    {
        $platform = '';
        foreach (self::platformClassMap as $plat => $className) {
            if ((new $className())->analysePlatform($url)) {
                $platform = $plat;
                break;
            }
        }
        return $platform;
    }

    /** 获取当前平台实例
     * @param $platform
     * @return mixed
     * @throws Exception
     */
    protected function getPlatformInstance($platform): mixed
    {
        if (!isset(self::platformClassMap[$platform])) throw new AccidentException('暂不支持该平台', Code::OPERATE_FAIL);
        $className = self::platformClassMap[$platform];
        return new $className();
    }
}
