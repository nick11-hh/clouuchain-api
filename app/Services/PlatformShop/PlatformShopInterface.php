<?php

namespace App\Services\PlatformShop;

interface PlatformShopInterface
{
    // 获取店铺授权链接
    public function getAuthUrl($params);

    // 店铺授权获取token
    public function authorize($params);

    // 同步平台产品
    public function syncProductList();

    // 同步单个产品详情
    public function syncProductDetail($productId);

    // 产品刊登发布
    public function productPublish($clientGoods, $params);

    // 同步平台订单
    public function syncOrderList($filterParams = []);

    // 同步单个订单详情
    public function syncOrderDetail($orderId);

    // 订单履约发货
    public function orderShipment($shopOrder, $params);

    public function packageShipment($package, $order, $params);

}
