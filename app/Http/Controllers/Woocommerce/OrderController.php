<?php

namespace App\Http\Controllers\Woocommerce;

use App\Http\Controllers\Controller;
use App\Services\Woocommerce\OrderService;

class OrderController extends Controller
{
    private OrderService $service;

    public function __construct(OrderService $service)
    {
        $this->service = $service;
    }

    /** 
     * 订单列表
     */
    public function index()
    {
        return $this->service->getOrderList();
    }

    /** 
     * 订单详情
     */
    public function show($id)
    {
        return $this->service->getInfoById($id);
    }

    /** 
     * 更新订单
     */
    public function update($id)
    {
        return $this->service->updateOrder($id);
    }

    /** 
     * 新增订单备注
     */
    public function addNotes($id)
    {
        return $this->service->addNotes($id);
    }

    /** 
     * 备注列表
     */
    public function notesList($orderId)
    {
        return $this->service->notesList($orderId);
    }

    /** 
     * 备注详情
     */
    public function notesInfo($orderId, $notesId)
    {
        return $this->service->notesInfo($orderId, $notesId);
    }
}
