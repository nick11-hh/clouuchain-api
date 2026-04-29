<?php

namespace App\Services\Base;


use App\Helper\CurrencyConverter;
use App\Lib\Code;
use App\Models\AdminCollectGoodsSku;
use App\Models\Country;
use App\Models\Custom;
use App\Models\GoodsDiscountRule;
use App\Models\GoodsSku;
use App\Models\LogisticsChannelModel;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\Order;
use App\Models\QuotationTemplateModel;
use App\Models\ShopOrderLogs;
use App\Models\SystemConfig;
use App\Models\WarehouseAddress;
use App\Models\CustomsQuoteConfig;
use App\Services\Admin\ExpressPriceService;
use App\Services\Admin\WarehouseAddressService;
use App\Services\Base\SystemConfigService;
use Exception;
use App\Exceptions\AccidentException;
use Illuminate\Support\Facades\DB;

class OrderQuoteService
{

    protected Order $order;

    protected $country;

    protected $saveLog = true;

    protected bool $isAutoPrice = false;

    protected bool $isSavePriceQuote = false;

    public function __construct(Order $order)
    {
        $this->order =$order;

        $this->country = Country::query()
            ->where('cn_name', $this->order->shippingAddress->country)
            ->orWhere('en_name', $this->order->shippingAddress->country)
            ->orWhere('code', strtolower($this->order->shippingAddress->country_code))
            ->first();
        if (empty($this->country)) throw new AccidentException("不支持国家{$this->order->shippingAddress->country}发货，请先去配置", Code::OPERATE_FAIL);
    }

    /** 设置当前为保存价格时的报价计算
     * 如果商品未关联或者没有渠道等报价信息不完整的直接抛出异常
     * @return $this
     */
    public function setSavePriceQuote()
    {
        $this->isSavePriceQuote = true;
        return $this;
    }

    /** 设置当前为自动报价
     * @return $this
     */
    public function setAutoPrice()
    {
        $this->isAutoPrice = true;
        return $this;
    }

    public function setLog($saveLog)
    {
        $this->saveLog = $saveLog;
        return $this;
    }

    /** 订单报价
     * @param $params //参数
     * @return array
     * $params = [
     * 'mapping_list' => [],  // 商品映射关系，如果为空则显示
     * 'express_line_id' => 0,
     * 'stock_type' => 1,
     * 'custom_logistic_price' => -1,
     * 'custom_favourable_price' => -1,
     * 'other_supplement_price' => 0,
     * 'order_one_price' => 0,
     * 'goods_once_price' => 0
     * ];
     * @throws \Exception
     */
    public function orderQuote($params)
    {
        if ($this->saveLog) {
            info("{$this->order->order_id}--计算报价价格", [
                'trace' => debug_backtrace()[0] ?? []
            ]);
        }
        $goodsList = $this->getOrderQuoteMapping($params['mapping_list'] ?? []);
        // 是否使用客户库存
        $stockType = $params['use_customer_stock'] ?? 0;
        // 客户产品利润
        $productProfitRate = bcsub(100, $params['product_quote_default_profit_rate'], 2);
        $profitRate = bcdiv($productProfitRate, 100, 2);
        // 客户物流利润
        $freightProfitRate = bcsub(100, $params['freight_quote_default_profit_rate'], 2);
        $freightRate = bcdiv($freightProfitRate, 100, 2);

        $quoteDetail = [];

        $quoteDetail['message'] = '';
        // 商品报价
        $goodsPrice = $this->goodsQuote($goodsList, $stockType, $profitRate, $params['exchange_rates'], $params['goods_once_price']);
        $goods_price_cn = $this->customNumberFormat($goodsPrice['goods_price'], 4);
        $quoteDetail['goods_price_cn'] = round($goods_price_cn, 2);  // 商品人民币报价
        $quoteDetail['goods_price'] = round(bcdiv($goods_price_cn, $params['exchange_rates'], 4), 2);  // 商品美元报价
        $quoteDetail['goods_price_detail'] = $goodsPrice['goods_price_detail'];  // 商品报价详情
        $quoteDetail['goods_weight'] = bcdiv($goodsPrice['goods_weight'], 1000, 3);  // 商品重量
        $quoteDetail['exchange_rates'] = $params['exchange_rates'];
        $quoteDetail['goods_once_price'] = $params['goods_once_price'];
        $quoteDetail['product_quote_default_profit_rate'] = $params['product_quote_default_profit_rate'];
        $quoteDetail['freight_quote_default_profit_rate'] = $params['freight_quote_default_profit_rate'];
        $quoteDetail['logistics_provider'] = 0;
        $quoteDetail['logistics_provider_code'] = '';
        $quoteDetail['express_line_id'] = 0;
        $quoteDetail['origin_logistics_fee'] = 0;
        $quoteDetail['logistics_compute_fee'] = 0;
        $quoteDetail['logistics_fee'] = 0;
        $quoteDetail['myLogisticsId'] = 0;
        $quoteDetail['myLogisticsChannelId'] = 0;
        #默认物流成本、物流利润
        $quoteDetail['logistics_cost'] = $quoteDetail['logistics_profit'] = $quoteDetail['freight_quote_amount_type'] = $quoteDetail['freight_quote_calculate_method'] = 0;
        // 订单属性
        $quoteDetail['prop_id'] = $this->getPropId($quoteDetail['goods_price_detail']);
        // 可使用的物流报价模板列表
        if ($params['goods_once_price'] == 1) {
            $channel_list = QuotationTemplateModel::query()
                ->where('id', 1)->get();
        } else {
            $channel_list = QuotationTemplateModel::query()
                ->where('is_fixed_price', $params['goods_once_price'])
                ->where('prop_id', $quoteDetail['prop_id'])
                ->where('status', 1)->get();
        }

        $quoteDetail['channel_list'] = $channel_list;

        if (!$params['goods_once_price']) {
            if ($params['express_line_id']) {
                $logisticsDetail = $this->logisticsQuote($goodsList, $params['express_line_id'], $params['goods_once_price'], $quoteDetail['prop_id'], $params['quote_id']);
                $quoteDetail['logistics_provider'] = $logisticsDetail['logistics_provider'];  // 物流渠道id
                $quoteDetail['logistics_provider_code'] = $logisticsDetail['logistics_provider_code'];// 物流服务商编码
                $quoteDetail['express_line_id'] = $logisticsDetail['express_line_id'];  // 运费模板渠道id
                $origin_logistics_fee = bcdiv($logisticsDetail['origin_logistics_fee'], $freightRate, 4);
                $logistics_price = bcdiv($logisticsDetail['logistics_price'], $freightRate, 4);
                $quoteDetail['origin_logistics_fee'] = $this->customNumberFormat($origin_logistics_fee, 2);  // 物流原始币种价格
                $quoteDetail['logistics_compute_fee'] = $this->customNumberFormat($logistics_price, 2);  // 物流计算价格
                $quoteDetail['logistics_fee'] = $quoteDetail['logistics_compute_fee'];
                $quoteDetail['logistics_cost'] = $logisticsDetail['origin_logistics_fee'];
                $quoteDetail['myLogisticsId'] = $logisticsDetail['myLogisticsId'];
                $quoteDetail['myLogisticsChannelId'] = $logisticsDetail['myLogisticsChannelId'];
            }
        } else {
            $quoteDetail['express_line_id'] = $params['express_line_id'] ?: 29;
            $quoteDetail['logistics_provider'] = 1;
            $quoteDetail['logistics_provider_code'] = 'yuntu';
        }

//        // 物流报价
//        try {
////            if (empty($params['express_line_id'])) $params['express_line_id'] = $this->order->express_line_id;
//            $logisticsDetail = $this->logisticsQuote($goodsList, $params['express_line_id'] ?? 0, $params['goods_once_price'] ?? 0);
//        } catch (Exception $e) {
//            $quoteDetail['message'] .= $e->getMessage();
//            $logisticsDetail = [];
//            // 自动报价没有物流渠道不给报
//            if ($this->isAutoPrice) {
//                throw new AccidentException($e->getMessage());
//            }
//        }
//
//        //订单报价界面物流总报价，如果报价配置->物流报价->价格表金额为"物流成本"就需要根据新规则计算
//
//        $keyList = [
//            SystemConfig::FREIGHT_QUOTE_AMOUNT_TYPE,
//            SystemConfig::FREIGHT_QUOTE_CALCULATE_METHOD,
//            SystemConfig::FREIGHT_QUOTE_DEFAULT_PROFIT_RATE,
//            SystemConfig::FREIGHT_QUOTE_DEFAULT_FIXED_AMOUNT,
//        ];
//
//        $systemConfig = SystemConfigService::getMultipleConfig($keyList);
//
//        #默认物流成本、物流利润
//        $quoteDetail['logistics_cost'] = $quoteDetail['logistics_profit'] = $quoteDetail['freight_quote_amount_type'] = $quoteDetail['freight_quote_calculate_method'] = 0;
//
//        if($systemConfig['freight_quote_amount_type'] == CustomsQuoteConfig::FREIGHT_QUOTE_AMOUNT_TYPE_2){
//
//            $quoteDetail['freight_quote_amount_type'] = $systemConfig['freight_quote_amount_type'];
//            $calculateMethod = $quoteDetail['freight_quote_calculate_method'] = $systemConfig['freight_quote_calculate_method'];
//            $profitRate = $systemConfig['freight_quote_default_profit_rate'];
//            $fixedAmount = $systemConfig['freight_quote_default_fixed_amount'];
//            $customsQuoteConfig = CustomsQuoteConfig::where('customer_id', $params['customer_id'])->first();
//            if($customsQuoteConfig && ((float) $customsQuoteConfig->freight_quote_default_profit_rate > 0) && ((float) $customsQuoteConfig->freight_quote_default_fixed_amount > 0)){
//
//                $profitRate = (float) $customsQuoteConfig->freight_quote_default_profit_rate;
//                $fixedAmount = (float) $customsQuoteConfig->freight_quote_default_fixed_amount;
//            }
//
//            $currencyConverter = new CurrencyConverter();
//
//            if($calculateMethod == CustomsQuoteConfig::FREIGHT_QUOTE_CALCULATE_METHOD_1){
//                #按百分比计算：物流成本 / (1 - 利润率) / 汇率
//
//                $quoteDetail['logistics_cost'] = $this->customNumberFormat($logisticsDetail['origin_logistics_fee'] ?? 0, 2);
//
//                $rate = (float) $profitRate / 100;
//                //用物流原始币种(人民币)价格
//                $quote = $quoteDetail['logistics_cost'] / (1 - $rate);
//
//                //报价转换成美元
//                $logisticsDetail['logistics_price'] = $currencyConverter->reversedCurrenciesExchange($quote);
//
//
//                //利润计算规则：应该是采购成本/（1-利润%）*利润%=12/(1-10%）*10%=1.33
//                $quoteDetail['logistics_profit'] = $quoteDetail['logistics_cost'] / (1 - $rate) * $rate;
//
//                $quoteDetail['logistics_profit'] = $this->customNumberFormat($quoteDetail['logistics_profit'], 2);
//
//                //直接用美元物流成本
//                // $logisticsDetail['logistics_price'] = ($logisticsDetail['logistics_price'] ?? 0) / (1 - (float) $profitRate / 100);
//
//            }
//
//            if($calculateMethod == CustomsQuoteConfig::FREIGHT_QUOTE_CALCULATE_METHOD_2){
//                #按固定金额计算：(物流成本 + 固定金额(人民币)) / 汇率
//
//                $quoteDetail['logistics_cost'] = $this->customNumberFormat($logisticsDetail['origin_logistics_fee'] ?? 0, 2);
//                $quoteDetail['logistics_profit'] = $this->customNumberFormat($fixedAmount, 2);
//
//                //直接用美元物流成本
//                $quote = $quoteDetail['logistics_cost'] + (float) $fixedAmount;
//
//                //报价转换成美元
//                $logisticsDetail['logistics_price'] = $currencyConverter->reversedCurrenciesExchange($quote);
//
//            }
//
//        }
//
//        $quoteDetail['logistics_provider'] = $logisticsDetail['logistics_provider'] ?? 0;  // 物流渠道id
//        $quoteDetail['logistics_provider_code'] = $logisticsDetail['logistics_provider_code'] ?? '';  // 物流服务商编码
//        $quoteDetail['express_line_id'] = $logisticsDetail['express_line_id'] ?? 0;  // 运费模板渠道id
//        $quoteDetail['origin_logistics_fee'] = $this->customNumberFormat($logisticsDetail['origin_logistics_fee'] ?? 0, 2);  // 物流原始币种价格
//        $quoteDetail['logistics_compute_fee'] = $this->customNumberFormat($logisticsDetail['logistics_price'] ?? 0, 2);  // 物流计算价格
//        $quoteDetail['channel_list'] = $logisticsDetail['channel_list'] ?? [];  // 可用物流渠道列表
//        $quoteDetail['logistics_fee'] = $this->customNumberFormat($params['custom_logistic_price'] >= 0 ? $params['custom_logistic_price'] : $quoteDetail['logistics_compute_fee'], 2);  // 物流最终价格
//        $quoteDetail['goods_once_price'] = $params['goods_once_price'] ?? 0;

        // 优惠金额
        $favourableDetail = $this->favourablePrice($goodsList, $quoteDetail['goods_price_detail']);
        $quoteDetail['favourable_discount'] = $favourableDetail['discount'];
        $quoteDetail['favourable_compute_price'] = $this->customNumberFormat($favourableDetail['price'], 2);   // 计算的优惠价格
//        $quoteDetail['favourable_price'] = $this->customNumberFormat($params['custom_favourable_price'], 2);   // 最终优惠价格
        $quoteDetail['favourable_price'] = $this->customNumberFormat($params['custom_favourable_price'] > 0 ? $params['custom_favourable_price'] : $quoteDetail['favourable_compute_price'], 2);   // 最终优惠价格

        // 其他补价
        $quoteDetail['other_supplement_price'] = $this->customNumberFormat($params['other_supplement_price'] ?? 0, 2);
        $quoteDetail['charge_type_id'] = $params['charge_type_id'] ?? 0;

        $quoteDetail['stock_type'] = $stockType;  // 使用库存

        //goods_price + logistics_price + other_supplement_price - favourable_price
        $total_price = $this->customNumberFormat($this->sumPrice($quoteDetail), 4);
        $quoteDetail['total_price'] = round($total_price, 2);  // 订单总价

        if ($this->saveLog) {
            info("{$this->order->order_id}--计算报价完成");
        }

        return $quoteDetail;
    }

    /**
     *  获取订单最大属性
     * @param $list
     * @return int|mixed
     */
    protected function getPropId($list)
    {
        $prop_id = 1;
        foreach ($list as $item) {
            if (isset($item['prop_id']) && $item['prop_id'] > $prop_id) {
                $prop_id = $item['prop_id'];
            }
        }
        return $prop_id;
    }

    /**
     * @param $quoteData
     * @param $params
     * @return void
     */
    public function saveQuoteData($quoteData, $params)
    {
        $this->order->vendor_price = $quoteData['goods_price'];
        $this->order->logistics_fee = $quoteData['logistics_fee'];
        $this->order->logistics_compute_fee = $quoteData['logistics_compute_fee'];
        $this->order->favourable_price = $quoteData['favourable_price'];
        $this->order->favourable_discount = $quoteData['favourable_discount'] ?? null;
        $this->order->favourable_compute_price = $quoteData['favourable_compute_price'];
        $this->order->other_supplement_price = $quoteData['other_supplement_price'];
        $this->order->order_one_price = $quoteData['goods_once_price'];
        $this->order->total_price = $quoteData['total_price'];

        $this->order->use_customer_stock = $params['use_customer_stock'] ?? 0;
        $this->order->save();

        $logContent = '订单报价：商品报价('.$this->order->vendor_price.') + 物流报价('.$this->order->logistics_fee.') + 其他补价('.$this->order->other_supplement_price.') - 优惠价格('.$this->order->favourable_price .') = ' . $this->order->total_price;
        if (!empty($this->order->use_customer_stock)) {
            $logContent .= '；使用客户库存，商品报价为 0';
        }
        $logData = [
            'order_id' => $this->order->id,
            'operator_type' => ShopOrderLogs::OPERATOR_TYPE_VENDOR_PRICE,
            'content' => $logContent,
        ];
        ShopOrderLogs::addLog($logData);
    }

    /** 商品报价
     * @param $goodsList
     * @param $stockType
     * @param $profitRate
     * @param $exchangeRates
     * @param $goodsOncePrice
     * @return array
     * @throws AccidentException
     */
    protected function goodsQuote($goodsList, $stockType, $profitRate, $exchangeRates, $goodsOncePrice)
    {
        $goodsPrice = 0;
        $goodsWeight = 0;
        $goodsDetail = [];
        $productService = new ProductService();
        $currencyConverter = new CurrencyConverter();
        $GoodsSkus = new GoodsSku();

        // 解决订单item关联了一样的品，但是查询一客一价时数量不对，需要合并数量
        $mappingGoodsQuantity = [];
        foreach ($goodsList as &$goods) {
            if (empty($goods->mappingGoodsSku)) continue;
            if (isset($mappingGoodsQuantity[$goods->mappingGoodsSku->id])) {
                $mappingGoodsQuantity[$goods->mappingGoodsSku->id] += $goods->quantity;
            } else {
                $mappingGoodsQuantity[$goods->mappingGoodsSku->id] = $goods->quantity;
            }
        }

        foreach ($goodsList as &$goods) {
            $goods->quote_price = 0;
            $detail = [
                'line_item_id' => $goods->id,
                'variant_id' => $goods->variant_id
            ];
            $detail['mappingGoodsSku'] = $goods->mappingGoodsSku;
            $detail['quote_price'] = 0;
            if (empty($goods->mappingGoodsSku) && $this->isAutoPrice) {
                throw new AccidentException("商品{$goods->variant_id}未匹配本地产品", Code::OPERATE_FAIL);
            }
            if (!empty($goods->mappingGoodsSku) && $stockType == 0) {  // 需要进行报价的
                // sku 报价，包括一客一价
                // 产品重量
                $sku_weight = $GoodsSkus->where('sku_id', $goods->mappingGoodsSku->sku_id)->value('weight');
                // 产品属性
                $detail['prop_id'] = $GoodsSkus->where('sku_id', $goods->mappingGoodsSku->sku_id)->value('prop_id');
                $skuQuote = $productService->getSkuQuote($goods->mappingGoodsSku->id, $this->order->customer_id, $this->country, $mappingGoodsQuantity[$goods->mappingGoodsSku->id]);
                if (empty($skuQuote['has_quote']) && $this->isSavePriceQuote && $goodsOncePrice === 1) {
                    throw new AccidentException("商品{$goods->mappingGoodsSku->sku_id}未设置报价价格");
                }
//                if ($skuQuote['sku_quotation_price'] === 0 && $goodsOncePrice === 1) {
//                    throw new AccidentException("商品{$goods->mappingGoodsSku->sku_id}未设置报价价格11", Code::OPERATE_FAIL);
//                }
                if ($goodsOncePrice) {
                    // 产品报价价格（美元）
                    $unit_price_en = $skuQuote['sku_quotation_price'];
                    $unit_price = bcmul($unit_price_en, $exchangeRates, 4);
                } else {
                    // 采购成本价格（人民币）* 产品利润
                    $unit_price = bcdiv($skuQuote['sku_purchase_price'], $profitRate, 4);
                    $unit_price_en = bcdiv($unit_price, $exchangeRates, 4);
                }
                $detail['has_quote'] = $skuQuote['has_quote'];
                $detail['purchase_price'] = $goods->mappingGoodsSku->purchase_price;
                $detail['profit_price'] = $this->getProfitPrice($unit_price, $detail['purchase_price'], $currencyConverter);
                $detail['quantity'] = $goods->quantity;
                $detail['weight'] = (int)bcmul($sku_weight, $goods->quantity, 2);
                $quote_price = (float)bcmul($unit_price, $goods->quantity, 4);
                $detail['quote_price'] = round($quote_price, 2);
                $quote_price_en = (float)bcdiv($quote_price, $exchangeRates, 4);
                $detail['quote_price_en'] = round($quote_price_en, 2);
                $procure_cost = (float)bcmul($skuQuote['sku_purchase_price'], $goods->quantity, 4);
                $detail['procure_cost'] = round($procure_cost, 2);
                $detail['unit_price'] = round($unit_price, 3);
                $detail['unit_price_en'] = round($unit_price_en, 3);
                $goodsPrice += $detail['quote_price'];
                $goodsWeight += $detail['weight'];
            }
            $goodsDetail[] = $detail;
        }
        return [
            'goods_price' => $goodsPrice,
            'goods_weight' => $goodsWeight,
            'goods_price_detail' => $goodsDetail,
        ];
    }

    /*** 物流报价
     * @param $goodsList
     * @param $channelId
     * @param $goodsOncePrice
     * @param $propId
     * @return array
     * @throws Exception
     */
    protected function logisticsQuote($goodsList, $channelId, $goodsOncePrice, $propId, $quote_id)
    {
        $weight = 0.0;
        foreach ($goodsList as $goods) {
            if (!empty($goods->mappingGoodsSku)) {
                $weight += $goods->mappingGoodsSku->weight * $goods->quantity;
            }
        }
        $propIds = [$propId];
        if (in_array($quote_id, [6, 7])) {
            $propIds = [1];
            if ($weight > 3000) {
                $weight -= 3000;
            }
        }
        $channelList = $this->getLogisticsChannel($weight, $propIds);

        //过滤未设置优先级的渠道
        $channel = collect($channelList)->filter(function ($item) use ($channelId) {
            if ($channelId) return $item['id'] == $channelId;
            if (!$this->isAutoPrice) return true;
            return $item['sort'] > 0;
        })->sortBy('sort')->first();

        if (empty($channel) && $this->isAutoPrice) {
            throw new AccidentException('物流渠道没有设置优先级', Code::OPERATE_FAIL);
        }

        if (empty($channel)) { // 没有合适的渠道
            return [
                'logistics_provider' => 0,
                'logistics_provider_code' => '',
                'logistics_channel_id' => '',
                'logistics_price' => 0,
                'express_line_id' => 0,
                'origin_logistics_fee' => 0,
                'myLogisticsId' => 0,
                'myLogisticsChannelId' => 0,
//                'channel_list' => [],
                'goods_once_price' => $goodsOncePrice
            ];
        }

        // 物流费用
        if ($goodsOncePrice) {
            $logisticsFee = 0;
            $originLogisticsFee = 0;
        } else {
            $logisticsFee = $channel['expire_fee'] / 100;
            $originLogisticsFee = $channel['origin_logistics_fee'] / 100;
        }
        // 渠道编码
        $logisticsChannel = LogisticsChannelModel::with('expressCompanies:id,code')
            ->where('code', $channel['channel_code'])
            ->orWhere('name', $channel['channel_code'])
            ->first();

        return [
            'logistics_provider' => $logisticsChannel->id ?? '',
            'logistics_channel_id' => $logisticsChannel->id  ?? '',
            'logistics_provider_code' => $logisticsChannel->expressCompanies->code ?? '',
            'express_line_id' => $channel['id'] ?? 0,
            'logistics_price' => $logisticsFee,// 美元
            'origin_logistics_fee' => $originLogisticsFee,// 人民币
            'myLogisticsId' => $channel['myLogisticsId'],
            'myLogisticsChannelId' => $channel['myLogisticsChannelId'],
//            'channel_list' => $channelList,
            'goods_once_price' => $goodsOncePrice
        ];
    }


    // 优惠价格
    protected function favourablePriceOld($goodsList)
    {
        $totalQuantity = 0;
        $propIds = [];
        foreach ($goodsList as $goods) {
            if (!empty($goods->mappingGoodsSku)) {
                $totalQuantity += $goods->quantity;
                $attributes = LogisticsCustomsDeclarationModel::query()->where('goods_sku_id', $goods->mappingGoodsSku->id)->value('attributes');

                //取属性最多的一个产品
                if (is_array($attributes) && (count($propIds) < count($attributes))) {
                    $propIds = $attributes;
                }
            }
        }
        $quoteFavourableSetting= SystemConfig::query()->where('config_key', SystemConfig::QUOTE_FAVOURABLE_SETTING)->value('config_value');
        $favourablePrice = 0;
        if ($quoteFavourableSetting && $totalQuantity > 1) {
            //开启报价优惠
            if ($quoteFavourableSetting['is_open']) {
                $favourablePrice = bcmul(($totalQuantity - 1), $quoteFavourableSetting['price']);
            }
        }
        return $favourablePrice;
    }

    /** 优惠金额
     * @param $goodsList
     * @param $goodsPriceDetail
     * @return array
     */
    protected function favourablePrice($goodsList, $goodsPriceDetail)
    {
        // 构建优惠的商品数量，合并相同商品
        $goodsDiscountList = [];
        foreach ($goodsList as $goods) {
            if (!empty($goods->mappingGoodsSku)) {
                if (isset($goodsDiscountList[$goods->mappingGoodsSku->goods->id])) {
                    $goodsDiscountList[$goods->mappingGoodsSku->goods->id]['quantity'] += $goods->quantity;
                } else {
                    $goodsDiscountList[$goods->mappingGoodsSku->goods->id] = [
                        'goods_id' => $goods->mappingGoodsSku->goods->id,
                        'quantity' => $goods->quantity,
                    ];
                }
            }
        }


        // 查询符合条件的优惠规则
        // $goodsIds = collect($goodsDiscountList)->pluck('goods_id');
        // $query = GoodsDiscountRule::query()->where('customer_id', $this->order->customer_id);
        // $discountList = $query->has('items', '=', count($goodsIds))
        //     ->whereDoesntHave('items', function ($q) use ($goodsIds) {
        //         $q->whereNotIn('goods_id', $goodsIds);
        //     })
        //     ->where(function ($q) use ($goodsDiscountList) {
        //         foreach ($goodsDiscountList as $key => $item) {
        //             $q->whereHas('items', function ($sub) use ($item) {
        //                 $sub->where('goods_id', $item['goods_id'])
        //                     ->where('quantity', '=', $item['quantity']); // 完全相等
        //             });
        //         }
        //     })->get();

        // 查询符合条件的优惠规则 - 使用 JOIN + GROUP BY + HAVING 优化性能
        $goodsCount = count($goodsDiscountList);
        if ($goodsCount === 0) {
            $discountList = collect();
        } else {
            // 构建 HAVING SUM 条件：确保每个商品的数量都匹配
            $sumConditions = [];
            $bindings = [];
            foreach ($goodsDiscountList as $item) {
                $sumConditions[] = "(i.goods_id = ? AND i.quantity = ?)";
                $bindings[] = $item['goods_id'];
                $bindings[] = $item['quantity'];
            }
            $sumCondition = implode(' OR ', $sumConditions);

            $query = DB::table('dsp_goods_discount_rules as r')
                ->join('dsp_goods_discount_rule_items as i', function ($join) {
                    $join->on('r.id', '=', 'i.rule_id')
                         ->whereNull('i.deleted_at');
                })
                ->where('r.customer_id', $this->order->customer_id)
                ->whereNull('r.deleted_at')
                ->groupBy('r.id')
                ->havingRaw('COUNT(*) = ?', [$goodsCount])
                ->havingRaw("SUM({$sumCondition}) = ?", array_merge($bindings, [$goodsCount]));

            // 获取符合条件的规则 ID
            $ruleIds = $query->pluck('r.id');

            // 加载完整的规则对象及其关联数据
            $discountList = GoodsDiscountRule::query()
                ->whereIn('id', $ruleIds)
                ->with('items')
                ->get();
        }

        // 计算每条规则的优惠金额
        $discountList->each(function ($discount) use ($goodsPriceDetail) {
            if ($discount->discount_type === GoodsDiscountRule::DISCOUNT_TYPE_FIXED_AMOUNT) {
                $discount->amount = $discount->discount_value;
            } else {
                $goodsPrice = 0;
                $itemGoodsIds = $discount->items->pluck('goods_id')->toArray();
                foreach ($goodsPriceDetail as $priceDetail) {
                    if (in_array($priceDetail['mappingGoodsSku']->goods_id, $itemGoodsIds)) {
                        $goodsPrice += $priceDetail['quote_price_en'];
                    }
                }
                $discount->amount = bcmul($goodsPrice, $discount->discount_value / 100, 2);
            }
        });

        // 按照优惠金额从大到小排序
        $matchDiscount = $discountList->sortByDesc('amount')->first();
        if (empty($matchDiscount)) {
            return [
                'price'    => 0,
                'discount' => null
            ];
        }
        return [
            'price' => $matchDiscount->amount,
            'discount' => $matchDiscount
        ];
    }

    /** 获取物流渠道
     * @param $weight
     * @param $propIds
     * @return mixed
     * @throws \Exception
     */
    protected function getLogisticsChannel($weight, $propIds)
    {
        $countryId = $this->country->id;
//        //获取仓库ID
//        $warehouse = new WarehouseAddressService(new WarehouseAddress);
//        $warehouseList = $warehouse->filterList(['country_id' => $countryId]);
//        $warehouseId = $warehouseList[0]['id'] ?? 0;

        //客户分组ID
        $customerGroup = Custom::query()->where('id', $this->order->customer_id)->value('group_id');

        //组装查询物流费用的参数
        $expressPriceData =  [
            'country_id' => $countryId,
//            'warehouse_id' => $warehouseId,//仓库
            'weight' => $weight,
            'customer_id' => $this->order->customer_id ?? 0,//客户
            'customer_group' => $customerGroup,//客户分组
            'postcode' => $this->order->shippingAddress->zip ?? '',//邮编
            'prop_ids' => $propIds,
        ];

        //查询物流费用
        return (new ExpressPriceService())->query($expressPriceData);
    }


    /** 获取订单商品映射关系
     * @param $goodsList
     * @return mixed
     */
    protected function getOrderQuoteMapping($goodsList)
    {
        foreach ($this->order->lineItems as &$item) {
            if (!empty($goodsList)) {
                $item->mappingGoodsSku = null;
                foreach ($goodsList as $goods) {
                    if ($goods['line_item_id'] === $item->id && !empty($goods['sku_id'])) {
                        $item->mappingGoodsSku = GoodsSku::query()->where('id', $goods['sku_id'])->first();
                    }
                }
            }
        }
        return $this->order->lineItems;
    }

    /** 总价计算
     * @param $quoteDetail
     * @return mixed
     */
    protected function sumPrice($quoteDetail)
    {
        $price = bcadd($quoteDetail['goods_price'], $quoteDetail['logistics_fee'], 4);
        $price = bcadd($price, (float)$quoteDetail['other_supplement_price'], 4);
        $price = bcsub($price, $quoteDetail['favourable_price'], 4);
        return max($price, 0);
    }

    /** 获取商品利润
     * @param $quote
     * @param $purchase
     * @param CurrencyConverter $currencyConverter
     * @return string
     */
    protected function getProfitPrice($quote, $purchase, CurrencyConverter $currencyConverter)
    {
//        $quoteCNY = $currencyConverter->convert($quote);
//        return bcsub($quoteCNY, $purchase, 2);
        return round(bcsub($quote, $purchase, 4), 2);
    }

    protected function customNumberFormat($num, $decimals)
    {
        return number_format($num, $decimals, '.', '');
    }

}
