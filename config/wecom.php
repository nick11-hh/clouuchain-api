<?php

return [
    'corpId' => env('WECOM_CORP_ID',''), //企业ID
    'corpSecret' => env('WECOM_CORP_SECRET',''), //企业密钥
    'approval' => [
        'stock_template_id' => env('WECOM_APPROVAL_STOCK_TEMPLATE_ID',''),//备货审批模板ID
    ],
    'token' => env('WECOM_TOKEN',''),
    'aesKey' => env('WECOM_AES_KEY',''),
];
