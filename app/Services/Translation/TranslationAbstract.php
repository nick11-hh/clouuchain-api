<?php

namespace App\Services\Translation;

abstract class TranslationAbstract 
{
    protected $language = '';

    protected $supportedLanguages = [];

    protected $config = [];

    /**
     * 构造函数
     * @param string $language
     * @return void
     */
    public function __construct($config, $language)
    {
        $this->language = $language;
        $this->config = $config;
    }



}