<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use App\Services\ExpressCompanies\DiSiFang\DiSiFangService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GetDsConsignmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $requestNo;
    public $orderId;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($requestNo, $orderId)
    {
        $this->onQueue('get-ds-consignment');

        $this->requestNo = $requestNo;
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::channel('logistics')->info('进队列里查询直发委托单', [
            'requestNo' => $this->requestNo,
            'orderId' => $this->orderId
        ]);

        (new DiSiFangService)->getDsConsignment($this->requestNo, $this->orderId);
    }
}
