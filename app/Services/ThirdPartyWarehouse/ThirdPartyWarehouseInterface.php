<?php

namespace App\Services\ThirdPartyWarehouse;

interface ThirdPartyWarehouseInterface
{
    // 同步第三方仓库产品
    public function syncWarehouseProduct($params);

    // 更新仓库产品信息
    public function updateWarehouseProduct($product, $params);

    // 推送订单到仓库
    public function pushOrderToWarehouse($order);

    // 获取仓库订单详情
    public function getOrderDetail($order);

    // 同步订单仓库发货状态
    public function syncOrderSendStatus($order);

    // 更新订单数据
    public function updateOrderData($order, $type = 1, $extraUpdateData = []);

}
