<?php

declare(strict_types=1);

namespace App\Jobs\Export;

use App\Helper\CosUtil;
use App\Models\ExcelExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Vtiful\Kernel\Excel;

class RegionServicePriceExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $data;

    private ExcelExport $excelExport;

    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  ExcelExport  $excelExport
     * @param  array  $data
     */
    public function __construct(ExcelExport $excelExport, array $data)
    {
        $this->data = $data;
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

        $filePath = "/excel/prices/$fileName";

        if (! Storage::disk('admin_public')->exists('/excel/prices/')) {
            Storage::disk('admin_public')->makeDirectory('/excel/prices/');
        }

        $config = ['path' => Storage::disk('admin_public')->path('/excel/prices/')];

        try {
            $excel = new Excel($config);

            $excel = $excel->fileName($fileName, __('渠道增值服务数据'))
                ->data($this->data);

            $path = $excel->output();

            // 上传到存储桶
            $url = CosUtil::localUploadToCos($filePath);

        } catch (\Throwable $throwable) {
            $this->excelExport->update([
                'status' => ExcelExport::STATUS_FAILED,
            ]);

            info('渠道增值服务数据导出失败:' . $throwable->getMessage());

            return;
        }

        info('渠道增值服务数据导出成功: '. $path);

        $this->excelExport->update([
            'status' => ExcelExport::STATUS_DONE,
            'url' => $url,
        ]);
    }
}
