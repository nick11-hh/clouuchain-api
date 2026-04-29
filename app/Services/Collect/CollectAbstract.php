<?php

namespace App\Services\Collect;

use App\Helper\CurrencyConverter;
use App\Lib\Code;
use Exception;
use App\Exceptions\AccidentException;

abstract class CollectAbstract
{

    CONST LANGUAGE_CN = 'zh_CN';
    CONST LANGUAGE_EN = 'en_US';
    CONST LANGUAGE_RU = 'ru_RU';
    CONST LANGUAGE_AR = 'ar_SA';
    CONST LANGUAGE_PT = 'pt_PT';
    CONST LANGUAGE_VN = 'vi_VN';
    CONST LANGUAGE_MN = 'mn_MN';

    protected $languageList = [
        self::LANGUAGE_CN,
        self::LANGUAGE_EN,
        self::LANGUAGE_RU,
        self::LANGUAGE_AR,
        self::LANGUAGE_PT,
        self::LANGUAGE_VN,
        self::LANGUAGE_MN,
    ];

    protected $platform;

    protected $language = 'en_US';

    protected $currency = 'USD';


    /** 设置产品语言
     * @param $language
     * @return void
     * @throws Exception
     */
    public function setLanguage($language)
    {
        if (!in_array($language, $this->languageList)) {
            throw new AccidentException('暂不支持该语言产品采集', Code::OPERATE_FAIL);
        }
        $this->language = $language;
    }

    /**
     * 设置货币
     * @param $currency
     * @return void
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/10 10:31
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    public function getApiLanguage()
    {
        if (!empty($this->languageMapping)) {
            return  $this->languageMapping[$this->language] ?? 'en_US';
        }
        return $this->language;
    }

    /** 转换产品列表价格
     * @param $list
     * @return mixed
     */
    public function transformListPrice($list)
    {
        if ($this->currency === 'CNY') return $list;
        $rate = $this->getExchangeRate();

        foreach ($list as &$value) {
            $value['original_price'] = $value['price'];
            $value['price'] = round($value['price'] / $rate, 2);
        }
        return $list;
    }

    /** 转换产品详情价格
     * @param $detail
     * @return mixed
     */
    public function transformDetailPrice($detail)
    {
        if ($this->currency === 'CNY') return $detail;
        $rate = $this->getExchangeRate();

        $detail['origin_price'] = $detail['price'];
        $detail['price'] = round($detail['price'] / $rate, 2);

        foreach ($detail['sku_list'] as &$sku) {
            $sku['origin_price'] = $sku['sale_price'];
            $sku['sale_price'] = round($sku['sale_price'] / $rate, 2);
            $sku['quote_price'] = round($sku['quote_price'] / $rate, 2);
            $sku['compare_price'] = round($sku['compare_price'] / $rate, 2);
        }
        return $detail;
    }

    /** 获取美元汇率
     * @return float
     */
    public function getExchangeRate()
    {
        $currencyConverter = new CurrencyConverter();

        return $currencyConverter->rate;
    }
}
