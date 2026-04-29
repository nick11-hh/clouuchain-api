<?php

namespace App\Services\Translation;

use App\Services\Translation\Exceptions\TranslationException;


class Factory
{
    public static function create($config, $language)
    {
        if (empty($config['class'])) {
            throw new TranslationException('Translation class is required');
        }
        $className = $config['class'];
        return new $className($config, $language);
    }
}
