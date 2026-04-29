<?php

namespace App\Jobs;

use App\Services\ExpressCompanies\ExpressCompanies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogisticsChannelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $express_companies; // 物流公司
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($express_companies)
    {
        $this->express_companies = $express_companies;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $expressCompanies = new ExpressCompanies($this->express_companies->code);
            $expressCompanies->channels($this->express_companies->id);
        } catch (\Exception $e) {
            logger('物流渠道获取失败：'.$e->getMessage());
            info('物流渠道获取失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        logger('物流公司编码：'.$this->express_companies->code);
        logger('物流公司id：'.$this->express_companies->id);
    }
}
