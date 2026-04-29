<?php

use OSS\OssClient;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'cos' => [
            'driver'     => 'cos',
            'app_id'     => env('COS_APP_ID'),
            'secret_id'  => env('COS_SECRET_ID'),
            'secret_key' => env('COS_SECRET_KEY'),
            'region'     => env('COS_REGION', 'ap-guangzhou'),
            'bucket'     => env('COS_BUCKET'),  // 不带数字 app_id 后缀
            'cdn'        => env('COS_CDN'),
            'signed_url' => false,
            'prefix' => env('COS_PATH_PREFIX'), // 全局路径前缀
            'guzzle' => [
                'timeout'         => env('COS_TIMEOUT', 60),
                'connect_timeout' => env('COS_CONNECT_TIMEOUT', 60),
            ],
        ],

        'oss' => [
            'driver'           => 'oss',
            'root'             => '', // 设置上传时根前缀
            'access_key'       => env('OSS_ACCESS_KEY'),
            'secret_key'       => env('OSS_SECRET_KEY'),
            'endpoint'         => env('OSS_ENDPOINT'), // 使用 ssl 这里设置如: https://oss-cn-beijing.aliyuncs.com
            'bucket'           => env('OSS_BUCKET'),
            'isCName'          => env('OSS_IS_CNAME', false), // 如果 isCname 为 false，endpoint 应配置 oss 提供的域名如：`oss-cn-beijing.aliyuncs.com`，否则为自定义域名，，cname 或 cdn 请自行到阿里 oss 后台配置并绑定 bucket
            'signatureVersion' => env('OSS_SIGNATURE_VERSION', OssClient::OSS_SIGNATURE_VERSION_V4),
            'region'           => env('OSS_REGION', 'cn-hangzhou'),
        ],

        'admin_public' => [
            'driver' => 'local',
            'root' => storage_path('app/public/admin'),
            'url' => env('APP_URL').'/storage/admin',
            'visibility' => 'public',
        ],

        'client_public' => [
            'driver' => 'local',
            'root' => storage_path('app/public/client'),
            'url' => env('APP_URL').'/storage/client',
            'visibility' => 'public',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
