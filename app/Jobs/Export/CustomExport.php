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
 * 客户列表导出
 * Class CustomExport
 * @package App\Jobs\Export
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/8/29 17:11
 */
class CustomExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $customData;

    private ExcelExport $excelExport;

    private array $header;

    /**
     * Create a new job instance.
     *
     * @param  ExcelExport  $excelExport
     * @param  array  $customData
     */
    public function __construct(ExcelExport $excelExport, array $customData)
    {
        $this->customData = $customData;
        $this->excelExport = $excelExport;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $storagePath = '/excel/custom/';
        $fileName = $this->excelExport['name'];

        if (!Storage::disk('admin_public')->exists($storagePath)) {
            Storage::disk('admin_public')->makeDirectory($storagePath);
        }

        $filePath = $storagePath . $fileName;

        $config = ['path' => Storage::disk('admin_public')->path($storagePath)];

        try {
            $excel = new Excel($config);

            $excel = $excel->fileName($fileName, '客户信息')
                ->header($this->getHeadings())
                ->data($this->getData());

            $path = $excel->output();

            // 上传到存储桶
            $url = CosUtil::localUploadToCos($filePath);

        } catch (\Throwable $throwable) {
            $this->excelExport->update([
                'status' => ExcelExport::STATUS_FAILED,
            ]);

            info('客户信息导出失败:' . $throwable);

            return;
        }

        info('客户信息导出成功: '. $path);

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
            "客户ID",
            "客户名称",
            "客户编号",
            "客户组",
            "手机号",
            "邮箱",
            "账户余额($)",
            "信用额度($)",
            "剩余信用额度($)",
            "消费总额($)",
            "佣金比例(%)",
            "佣金($)",
            "邀请人",
            "最后登录时间",
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
        $this->customData = collect($this->customData)->map(function ($custom) {
            return array_values($custom);
        })->toArray();

        return $this->customData;

    }


    /**
     * @param string $col
     * @return float|int
     */
    private function getExcelCol(string $col)
    {
        $number = 0;
        foreach (str_split($col) as $letter){
            $number = ($number * 26) + (ord(strtolower($letter)) - 96);
        }

        return $number - 1;
    }

}
