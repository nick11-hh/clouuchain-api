<?php

namespace App\Services\PlatformShop;

use App\Models\ShopModel;

abstract class PlatformShopAbstract
{
    protected ShopModel $shop;

    protected string $platform;

    public function __construct(ShopModel $shop)
    {
        $this->shop = $shop;
    }
}
