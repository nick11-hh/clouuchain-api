<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Shopify\Rest\Admin2022_04\Balance;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;
use App\Models\Order;
use App\Models\BalanceRecord;

/**
 * 更新订单支付金额历史数据
 * Class UpdateShopOrderPaymentPrice
 * @package App\Console\Commands
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2025/1/18 10:56
 */
class UpdateShopOrderPaymentPrice extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:update-shop-order-payment-price {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修复订单支付金额历史数据';

    /**
     * Execute the console command.
     *
     * @return bool
     */
    public function handle()
    {
        $data = $this->getPaymentPriceData();

        if (empty($data)) {
            $this->info('暂无需修复数据');
            return true;
        }

        $this->info('开始修复支付金额数据');

        foreach ($data as $value) {
            foreach ($value as $item) {
                $order = Order::query()->where(['customer_id' => $item['customer_id'], 'order_id' => $item['order_sn']])->first();
                if (!empty($order)) {
                    $this->info('开始处理：客户id：'. $item['customer_id'] .'订单号：'. $item['order_sn'] .'修复支付金额数据');
                    $order->payment_price = $item['payment_price'];
                    $order->save();
                }
            }
        }

        $this->info('修复支付金额数据完成');
        return true;
    }

    /**
     * 获取需要修复支付金额的数据
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/16 19:09
     */
    public function getPaymentPriceData()
    {
        $list = BalanceRecord::query()
            ->where('source_type', BalanceRecord::SOURCE_ORDER_PAY)
            ->where('order_sn', '<>', '')
            ->get();

        $data = [];
        foreach ($list as $value) {
            $data[$value->custom_id][$value->order_sn] = [
                'customer_id' => $value->custom_id,
                'order_sn' => $value->order_sn,
                'payment_price' => $value->amount / 100
            ];
        }

        return $data;
    }
}
