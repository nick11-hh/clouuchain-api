<?php

/**
 * Salla电商平台配置文件
 */
return [
    //客户端id
    'client_id' => env('SALLA_OAUTH_CLIENT_ID', ''),
    //客户端密钥
    'client_secret' => env('SALLA_OAUTH_CLIENT_SECRET', ''),
    //oauth回调地址
    'redirect_url' => env('SALLA_OAUTH_REDIRECT_URL', ''),
    //授权域名
    'oauth_url' => env('SALLA_OAUTH_BASE_URL', 'https://accounts.salla.sa'),
    //接口请求域名
    'base_api_url' => env('SALLA_API_URL', 'https://api.salla.dev/admin/v2'),
];
