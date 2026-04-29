<?php

namespace App\Services\PlatformShop;

use App\Models\ShopModel;

trait RequestFailTrait
{
    public function requestFail($message)
    {
        $this->shop->status = ShopModel::STATUS_AUTH_EXPIRE;
        $this->shop->fail_info = $message;
        $this->shop->save();
    }
}
