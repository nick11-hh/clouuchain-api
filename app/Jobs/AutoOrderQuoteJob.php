<?php

namespace App\Jobs;

use App\Services\PlatformShop\DataService\OrderDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 自动报价队列
 * Class AutoOrderQuoteJob
 * @package App\Jobs
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2025/2/8 18:54
 */
class AutoOrderQuoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;

    protected $confirmQuotation = false;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order, $confirmQuotation = false)
    {
        $this->order = $order;
        $this->confirmQuotation = $confirmQuotation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $orderDataService = new OrderDataService();
            info('AutoOrderQuoteJob-Processing', [
                'order_id'      => $this->order->order_id,
                'platform'      => $this->order->platform,
            ]);

            $orderDataService->autoOrderQuote($this->order, $this->confirmQuotation);
        } catch (\Exception $e) {
            info('AutoOrderQuoteJob-Exception', [
                'msg_data'      => [$e->getMessage(), $e->getFile(), $e->getLine()],
                'order_id'      => $this->order->order_id,
                'platform'      => $this->order->platform,
            ]);
        }

    }
}
