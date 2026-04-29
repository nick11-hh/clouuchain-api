<?php

namespace App\Services\Base;

use App\Models\Goods;
use App\Models\GoodsSku;
use App\Models\SkuQuotationGroupModel;

class ProductService
{
    public function getSkuQuote($skuId, $customerId, $country, $qty)
    {
        //根据产品id查询所有的报价信息
        $skuQuotationData = [];
        $skuQuotationList = SkuQuotationGroupModel::query()->with([
            'skuQuotationGroupAttr' => function ($query) {
                $query->where('is_new', 1);//只获取最新的报价
            }
        ])->where('sku_id', $skuId)->get()->toArray();

        foreach ($skuQuotationList as $quotation) {
            foreach ($quotation['sku_quotation_group_attr'] as $item) {
                //组装报价信息 skuID-客户ID-国家ID
                $skuQuotationData[$quotation['sku_id']. '-'  . $quotation['custom_id'] . '-' . $quotation['country_id']][] = [
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ];
            }
        }

        //sku报价参数
        $skuQuotation = [];

        //sku报价 所有客户&&所有国家 优先级：4
        $skuQuotationWithAllKey = $skuId . '-0-0';
        if (isset($skuQuotationData[$skuQuotationWithAllKey])) {
            $skuQuotation = $skuQuotationData[$skuQuotationWithAllKey];
        }

        //sku报价 所有客户&&指定国家 优先级：3
        $skuQuotationWithAllCustomerKey = $skuId . '-0-' . $country->id;
        if (isset($skuQuotationData[$skuQuotationWithAllCustomerKey])) {
            $skuQuotation = $skuQuotationData[$skuQuotationWithAllCustomerKey];
        }

        //sku报价 指定客户&&所有国家 优先级：2
        $skuQuotationWithAllCountryKey = $skuId . '-' . $customerId . '-0';
        if (isset($skuQuotationData[$skuQuotationWithAllCountryKey])) {
            $skuQuotation = $skuQuotationData[$skuQuotationWithAllCountryKey];
        }

        //sku报价 指定客户&&指定国家 优先级：1
        $skuQuotationKey = $skuId . '-' . $customerId . '-' . $country->id;
        if (isset($skuQuotationData[$skuQuotationKey])) {
            $skuQuotation = $skuQuotationData[$skuQuotationKey];
        }


        //sku报价金额
        $quoteGoodsSku = GoodsSku::query()->with('goods')->findOrFail($skuId);
        $purchasePrice = $quoteGoodsSku->purchase_price;

        // 除了虚拟品之外不再使用产品库中的价格作为默认价格，而是必须要设置一客一价
        if (empty($skuQuotation) && $quoteGoodsSku->goods->goods_type != Goods::GOODS_TYPE_VIRTUAL_PRODUCT) {
            return [
                'sku_quotation_price' => 0,
                'sku_purchase_price' => $purchasePrice,
                'has_quote' => 0,
            ];
        }

        //sku报价金额
        $quotationPrice = $quoteGoodsSku->quote_price;
        if ($skuQuotation) {
            //组装成数量对应价格并按数量正序排序 方便计算区间报价
            $skuQuotation = array_column($skuQuotation, 'price', 'quantity');
            ksort($skuQuotation);

            foreach ($skuQuotation as $quantity => $price) {
                if ($qty >= $quantity) {
                    $quotationPrice = $price;
                }
            }
        }

        return [
            'sku_quotation_price' => $quotationPrice,
            'sku_purchase_price' => $purchasePrice,
            'has_quote' => 1,
        ];
    }
}
