<?php

namespace App\Http\Controllers\Woocommerce;

use App\Http\Controllers\Controller;
use App\Services\Woocommerce\ProductService;

class ProductController extends Controller
{
    private ProductService $service;

    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }

    /** 
     * 产品列表
     */
    public function index()
    {
        return $this->service->getProductList();
    }

    /** 
     * 产品详情
     */
    public function show($id)
    {
        return $this->service->getInfoById($id);
    }

}
