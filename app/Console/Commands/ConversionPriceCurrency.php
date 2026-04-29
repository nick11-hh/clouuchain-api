<?php

namespace App\Console\Commands;

use App\Helper\CurrencyConverter;
use App\Models\AdminCollectGoods;
use App\Models\Goods;
use App\Models\Order;
use App\Models\SkuQuotationGroupAttrModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class ConversionPriceCurrency extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:conversion_currency {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '转换币种 把历史所有报价金额转换为美元';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /*$this->convertGoods();
        $this->convertOrder();
        $this->covertOneCustomOnePrice();*/
        return 1;
    }

    public function convertGoods()
    {
        DB::beginTransaction();
        try {
            $currencyConverter = new CurrencyConverter();

            Goods::query()->with('skus')->chunk(100, function ($goodsList) use ($currencyConverter) {
                foreach ($goodsList as $goods) {
                    $goods->goods_lowest_price = $currencyConverter->reversedCurrenciesExchange($goods->goods_lowest_price);
                    $goods->min_sale_price = $currencyConverter->reversedCurrenciesExchange($goods->min_sale_price);
                    $goods->max_sale_price = $currencyConverter->reversedCurrenciesExchange($goods->max_sale_price);
                    $this->info('goods_spu:'. $goods->spu);
                    $goods->save();
                    foreach ($goods->skus as $sku) {
                        $sku->sale_price = $currencyConverter->reversedCurrenciesExchange($sku->sale_price);
                        $sku->quote_price = $currencyConverter->reversedCurrenciesExchange($sku->quote_price);
                        $sku->original_price = $currencyConverter->reversedCurrenciesExchange($sku->origin_price);
                        $sku->save();
                    }
                }
            });

             AdminCollectGoods::query()->with('skus')->chunk(100, function ($collectGoodsList) use ($currencyConverter) {
                foreach ($collectGoodsList as $collectGoods) {
                    $collectGoods->purchase_price = $currencyConverter->reversedCurrenciesExchange($collectGoods->purchase_price);
                    $collectGoods->save();
                    $this->info('collect_goods_spu:'. $collectGoods->spu);
                    foreach ($collectGoods->skus as $collectSku) {
                        $collectSku->sale_price = $currencyConverter->reversedCurrenciesExchange($collectSku->sale_price);
                        $collectSku->quote_price = $currencyConverter->reversedCurrenciesExchange($collectSku->quote_price);
                        $collectSku->compare_price = $currencyConverter->reversedCurrenciesExchange($collectSku->compare_price);
                        $collectSku->save();
                    }
                }
            });

            $this->info('商品价格货币转换完成');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('goods error ----' . $e->getMessage());
        }
    }

    public function convertOrder()
    {
        DB::beginTransaction();
        try {
            $currencyConverter = new CurrencyConverter();
            Order::query()->with('lineItems')->where('vendor_price', '>', 0)->chunk(100, function ($orders) use ($currencyConverter) {
                foreach ($orders as $order) {
                    $tempVendorPrice = $order->vendor_price;
                    $order->refund_price = $currencyConverter->reversedCurrenciesExchange($order->refund_price);
                    $order->supplement_price = $currencyConverter->reversedCurrenciesExchange($order->supplement_price);
                    $order->favourable_price = $currencyConverter->reversedCurrenciesExchange($order->favourable_price);
                    $order->sku_logistics_fee = $currencyConverter->reversedCurrenciesExchange($order->sku_logistics_fee);
                    $order->change_logistics_fee = $currencyConverter->reversedCurrenciesExchange($order->change_logistics_fee);
                    $order->vendor_price = $currencyConverter->reversedCurrenciesExchange($order->vendor_price);
                    $order->vendor_change_price = $currencyConverter->reversedCurrenciesExchange($order->vendor_change_price);
                    $order->other_supplement_price = $currencyConverter->reversedCurrenciesExchange($order->other_supplement_price);
                    $order->logistics_fee = $currencyConverter->reversedCurrenciesExchange($order->logistics_fee);

                    $this->info("订单号: {$order->order_id}，vendor_price => {$order->vendor_price}, old_vendor_price => {$tempVendorPrice}");
                    $order->save();
                    foreach ($order->lineItems as $item) {
                        $item->quote_price = $currencyConverter->reversedCurrenciesExchange($item->quote_price);
                        $item->save();
                    }
                }
            });

            $this->info('订单价格货币转换完成');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('order error : ----' . $e->getMessage());
        }

    }

    public function covertOneCustomOnePrice()
    {
        DB::beginTransaction();
        try {
            $currencyConverter = new CurrencyConverter();
            SkuQuotationGroupAttrModel::query()->chunk(100, function ($skuPriceList) use ($currencyConverter) {
                foreach ($skuPriceList as $skuPrice) {
                    $skuPrice->price = $currencyConverter->reversedCurrenciesExchange($skuPrice->price);
                    $this->info("一客一价SKU报价记录ID: $skuPrice->id");
                    $skuPrice->save();
                }
            });

            $this->info('一客一价SKU报价价格货币转换完成');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('one price error : ----' . $e->getMessage());
        }

    }
}
