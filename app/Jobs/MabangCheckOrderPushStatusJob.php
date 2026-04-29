<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\ThirdPartyWarehouse\Mabang\MabangService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MabangCheckOrderPushStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Order $order;
    protected $pushLog;

    public $tries = 5;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order, $pushLog)
    {
        $this->order = $order;
        $this->pushLog = $pushLog;
        // 延迟10秒
        $this->delay = 10;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $config = ThirdPartyWarehouseConfig::getConfig();
        if (empty($config)) return;
        $attempts = $this->attempts();
        $service = new MabangService($config);
        $service->checkOrderCreate($this->order, $this->pushLog, $attempts != $this->tries);
    }

    public function backoff()
    {
        //重试时间间隔 1分钟 2分钟 10分钟 20分钟
        return [60, 120, 600, 1200];
    }
}
