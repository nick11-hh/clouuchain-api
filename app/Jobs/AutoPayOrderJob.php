<?php

namespace App\Jobs;

use App\Lib\Code;
use App\Models\CustomConfig;
use App\Models\Order;
use App\Services\AutoOrderPayment;
use App\Services\Shopify\OrderService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoPayOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $customData = CustomConfig::query()->where('is_auto_payment', 1)->get()->toArray();
        $orderData = Order::query()->where('customer_id', array_column($customData, 'custom_id'))->where('order_status', Order::STATUS_QUOTED)->get()->toArray();
        if ($orderData) {
            $autoOrderPayment = new AutoOrderPayment();
            foreach ($orderData as $order) {
                info("订单自动支付开始处理---id:{$order['id']}---order_id:{$order['order_id']}");
                try {
                    $autoOrderPayment->autoOrderPaymentProcess($order['customer_id'], $order['id']);
                    info('订单支付成功');
                } catch (\Throwable $e) {
                    info('订单支付失败', ['error' => $e->getMessage()]);
                }
            }
        }
    }
}
