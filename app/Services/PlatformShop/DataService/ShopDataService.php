<?php

namespace App\Services\PlatformShop\DataService;

use App\Models\ShopAuth;
use App\Models\ShopModel;

class ShopDataService
{

    protected $platform;

    public function __construct($platform)
    {
        $this->platform = $platform;
    }

    /**
     * @param $data
     * @return void
     */
    public function saveOrUpdateShop($data)
    {
        validator($data, $this->shopRule())->validate();
        $shop = ShopModel::query()->where('platform', $this->platform)
            ->where('platform_shop_id', $data['platform_shop_id'])->first();
        if (empty($shop)) {
            $shop = new ShopModel();
            $shop->platform = $this->platform;
            $shop->platform_shop_id = $data['platform_shop_id'];
            $shop->shop_name = $data['shop_name'];
        }
        $shop->customer_id = getCustomId();
        $shop->shop_url = $data['shop_url'] ?? '';
        $shop->status = ShopModel::STATUS_AUTH;
        $shop->authorize_at = now();
        $shop->access_token = $data['access_token'] ?? '';
        $shop->shop_auth_id = $data['shop_auth_id'] ?? '';
        $shop->platform_shop_code = $data['platform_shop_code'] ?? '';
        $shop->region = $data['region'] ?? '';
        $shop->seller_type = $data['seller_type'] ?? 1;
        $shop->ext_data = $data['ext_data'] ?? null;
        $shop->save();
    }

    /**
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object
     */
    public function saveOrUpdateShopAuth($data)
    {
        validator($data, $this->shopAuthRule())->validate();
        $shopAuth = ShopAuth::query()->where('platform', $this->platform)->where('seller_id', $data['seller_id'])->first();
        if (empty($shopAuth)) {
            $shopAuth = new ShopAuth();
            $shopAuth->platform = $this->platform;
            $shopAuth->seller_id = $data['seller_id'];
        }
        $shopAuth->custom_id = getCustomId();
        $shopAuth->access_token = $data['access_token'];
        $shopAuth->access_token_expire_in = $data['access_token_expire_in'] ?? null;
        $shopAuth->refresh_token = $data['refresh_token'] ?? '';
        $shopAuth->refresh_token_expire_in = $data['refresh_token_expire_in'] ?? null;
        $shopAuth->region = $data['region'] ?? '';
        $shopAuth->origin_data = $data['origin_data'] ?? null;
        $shopAuth->save();
        return $shopAuth;
    }


    protected function shopRule()
    {
        return [
            'shop_name' => 'required|string',
            'platform_shop_id' => 'required|string',
            'shop_url' => 'sometimes|string',
            'access_token' => 'sometimes|string',
            'shop_auth_id' => 'sometimes|int',
            'platform_shop_code' => 'sometimes|string',
            'region' => 'sometimes|string',
            'seller_type' => 'sometimes|int',
            'ext_data' => 'sometimes|array',
        ];
    }

    protected function shopAuthRule()
    {
        return [
            'seller_id' => 'required|string',
            'access_token' => 'required|string',
            'access_token_expire_in' => 'sometimes|string',
            'refresh_token' => 'sometimes|string',
            'refresh_token_expire_in' => 'sometimes|string',
            'region' => 'sometimes|string',
            'origin_data' => 'sometimes|array',
        ];
    }

}
