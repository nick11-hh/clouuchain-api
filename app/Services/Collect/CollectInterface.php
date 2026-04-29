<?php

namespace App\Services\Collect;


interface CollectInterface
{

    /** 解析是否是该平台的商品链接
     * @param $url
     */
    public function analysePlatform($url) :bool;

    /** 获取商品数据
     * @param $url
     */
    public function getProductDataByUrl($url);

    /** 获取产品列表
     * @param $params
     * @return mixed
     */
    public function getProductList($params);

    /** 获取产品详情
     * @param $productId
     * @return mixed
     */
    public function getProductDetail($productId, $transformPrice = true);

    /** 以图搜物
     * @param $params
     * @return mixed
     */
    public function productImageSearch($params);

}
