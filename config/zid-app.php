<?php

/**
 * Zid电商平台配置文件
 */
return [
    //客户端ID
    'client_id' => env('ZID_CLIENT_ID', ''),
    //客户端密钥
    'client_secret' => env('ZID_CLIENT_SECRET', ''),
    //授权域名
    'oauth_url' => env('ZID_OAUTH_URL', 'https://oauth.zid.sa'),
    //接口请求域名
    'base_api_url' => env('ZID_BASE_API_URL', 'https://api.zid.sa/v1'),
];
