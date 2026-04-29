<?php

namespace App\Services\PlatformShop\Exceptions;

use App\Lib\Code;
use App\Services\ApiResponseService;
use Exception;

class PlatformShopException extends Exception
{

    protected $message;

    public string $type;


    const SHOPIFY_API_ERROR = 'shopify接口请求失败';
    const SHOPIFY_AUTH_ERROR = 'shopify授权信息失效';
    const SHOPIFY_ORDER_ON_HOLED = 'shopify订单已搁置';

    public function __construct($type, $message = '')
    {
        $this->type = $type;
        $this->message = $message;
    }

}
