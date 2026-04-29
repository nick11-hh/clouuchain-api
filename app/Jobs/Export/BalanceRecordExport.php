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

/**
 * 客户余额流水记录导出
 * Class OnlineRechargeRecordExport
 * @package App\Jobs\Export
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/12/08 16:11
 */
class BalanceRecordExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $data;

    private ExcelExport $excelExport;

    private $type;

    private array $header;

    /**
     * Create a new job instance.
     *
     * @param  ExcelExport  $excelExport
     * @param  array  $data
     * @param int $type
     */
    public function __construct(ExcelExport $excelExport, array $data, int $type = 1)
    {
        $this->data = $data;
        $this->excelExport = $excelExport;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $storagePath = '/excel/balance_record/';
        $fileName = $this->excelExport['name'];

        if (!Storage::disk('admin_public')->exists($storagePath)) {
            Storage::disk('admin_public')->makeDirectory($storagePath);
        }

        $filePath = $storagePath . $fileName;

        $config = ['path' => Storage::disk('admin_public')->path($storagePath)];

        try {
            $excel = new Excel($config);

            $sheetName = '客户余额流水记录';
            $getHeadings = $this->getHeadings();
            if ($this->type == 10) {
                $sheetName = '客户流水明细';
                $getHeadings = $this->getHeadings2();
            }
            $excel = $excel->fileName($fileName, $sheetName)
                ->header($getHeadings)
                ->data($this->getData());

            $path = $excel->output();

            // 上传到存储桶
            $url = CosUtil::localUploadToCos($filePath);

        } catch (\Throwable $throwable) {
            $this->excelExport->update([
                'status' => ExcelExport::STATUS_FAILED,
            ]);

            info('客户余额流水记录导出失败:' . $throwable);

            return;
        }

        info('客户余额流水记录导出成功: '. $path);

        $this->excelExport->update([
            'status' => ExcelExport::STATUS_DONE,
            'url' => $url,
        ]);
    }

    /**
     * 获取每一列的标题
     * @return array
     */
    protected function getHeadings(): array
    {
        return [
            "流水号",
            "客户ID",
            "客户编号",
            "客户名称",
            "客户分组",
            "支付类型",
            "金额",
            "变动后余额",
            "关联单号",
            "平台单号",
            "备注",
            "创建时间",
        ];
    }

    protected function getHeadings2(): array
    {
        return [
            "流水号",
            "客户编号",
            "客户名称",
            "客户分组",
            "支付类型",
            "调整额度$",
            "调整后余额度$",
            "调整后授信额度$",
            "调整后冻结额度$",
            "备注",
            "创建时间",
        ];
    }

    /**
     * 格式转换
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 17:04
     */
    public function getData(): array
    {
        $this->data = collect($this->data)->map(function ($item) {
            return array_values($item);
        })->toArray();

        return $this->data;

    }


}
