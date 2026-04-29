<?php

return [

    'default' => env('TRANSLATION_DEFAULT', 'google'),

    'translations' => [
        'google' => [
            'class' => \App\Services\Translation\platform\google\GoogleTranslationService::class,
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'supported_languages' => [
                'zh_CN',
                'en_US',
                'ru_RU',
            ],
        ],
    ],
];