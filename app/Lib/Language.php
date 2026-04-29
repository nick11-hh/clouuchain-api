<?php

namespace App\Lib;

class Language
{

    public const CHINESE = 'zh_CN'; //中文
    public const ENGLISH = 'en_US'; //英语
    public const RUSSIAN = 'ru_RU'; //俄语
    public const ARABIC  = 'ar_SA'; //阿拉伯语
    public const PORTUGAL= 'pt_PT'; //葡萄牙语
    public const VIETNAM = 'vi_VN'; //越南语

    //语言列表
    public const LANGUAGE_LIST = [
        self::CHINESE   => '简体中文',
        self::ENGLISH   => 'English',
        self::RUSSIAN   => 'Русский язык',
        self::ARABIC    => 'العربية',
        self::PORTUGAL  => 'Português',
        self::VIETNAM   => 'Tiếng Việt',
    ];

    /**
     * 获取语言列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/26 11:54
     */
    public static function getLanguageList()
    {
        $list = self::LANGUAGE_LIST;

        $data = [];
        foreach ($list as $key => $value) {
            $data[] = [
                'name' => $value,
                'value' => $key,
            ];
        }

        return $data;
    }

    /**
     * 获取语言名称
     * @param $key
     * @return string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/26 13:41
     */
    public static function getLanguageName($key)
    {
        return self::LANGUAGE_LIST[$key] ?? '-';
    }
}
