<?php

declare(strict_types=1);

namespace App\Jobs\Export;

use App\Exports\ExpressRegionsExport;
use App\Models\ExcelExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExpressRegionExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $params;

    private ExcelExport $excelExport;

    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param ExcelExport $excelExport
     * @param array $params
     * @param bool $mergeTile
     */
    public function __construct(ExcelExport $excelExport, array $params, protected bool $mergeTile = false)
    {
        $this->params = $params;
        $this->excelExport = $excelExport;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $fileName = $this->excelExport['name'];

        //实际导出操作 并存储默认DISK
        Excel::store(new ExpressRegionsExport($this->params), admin_path($fileName));

        // 定义要替换的域名模式数组
        $patterns = [
            'dev-api.mateseller.com/storage',
            'api.mateseller.com/storage',
            'api.matedropshipping.com/storage'
        ];
        // 定义替换目标
        $replacement = 'img.matedropshipping.com';

        $newUrl = str_replace($patterns, $replacement, $this->excelExport['url']);
        //更新导出状态
        $this->excelExport->update([
            'status' => ExcelExport::STATUS_DONE,
            'url' => $newUrl
        ]);
    }

}
