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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vtiful\Kernel\Excel;
use Vtiful\Kernel\Format;

/**
 * 信用卡充值记录导出
 * Class CreditCardRechargeRecordExport
 * @package App\Jobs\Export
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/12/08 16:11
 */
class CreditCardRechargeRecordExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $queue = 'credit-card-recharge-record:export';
    private array $data;

    private ExcelExport $excelExport;

    private array $header;

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
        $storagePath = '/excel/credit_card_recharge/';
        $fileName = $this->excelExport['name'];

        if (!Storage::disk('admin_public')->exists($storagePath)) {
            Storage::disk('admin_public')->makeDirectory($storagePath);
        }

        $filePath = $storagePath . $fileName;

        $config = ['path' => Storage::disk('admin_public')->path($storagePath)];

        try {
            $excel = new Excel($config);

            $urlFile = $excel->fileName($fileName, '客户信用卡充值记录')
                ->header($this->getHeadings());

            $fileHandle = $urlFile->getHandle();

            $format   = new Format($fileHandle);
            $urlStyle = $format->bold()
                ->underline(Format::UNDERLINE_SINGLE)
                ->toResource();

            // 添加数据
            $data = $this->getData();
            Log::info('====',$data);
            $rowCount = 0;
            foreach ($data as $rowIndex => $row) {

                $rowCount ++;
                // 插入数据行
                $urlFile->data([$row]); // 添加一行数据

                // 插入核账图片链接
                $images = $row[11];
                if (is_array($images) && !empty($images[0])) {

                    foreach ($images as $index => $imageUrl) {
                        // info('imageUrl', [
                        //     'index' => $index,
                        //     'imageUrl' => $imageUrl
                        // ]);
                        if ($imageUrl) {

                            $picRow = $rowCount;

                            if($index != 0){
                                $urlFile->data([$row]); // 添加一行数据

                                $rowCount ++;
                                $picRow = $rowCount;
                            }

                            // 插入链接
                            $urlFile->insertUrl($picRow, 11, $imageUrl, NULL, '交易单号[' . $row[0] . ']的第' . (1 + $index) . '张核账图', $urlStyle);
                            // info('imageUrl2', [
                            //     'index' => $index,
                            //     'imageUrl' => $imageUrl
                            // ]);
                        }
                    }
                }
            }

            $path = $urlFile->output();

            // 上传到存储桶
            $url = CosUtil::localUploadToCos($filePath);

        } catch (\Throwable $throwable) {
            $this->excelExport->update([
                'status' => ExcelExport::STATUS_FAILED,
            ]);

            info('客户信用卡充值记录导出失败:' . $throwable);

            return;
        }

        info('客户信用卡充值记录导出成功: '. $path);

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
            "交易单号",
            "客户ID",
            "客户编号",
            "客户名称",
            "客户分组",
            "充值金额$",
            "支付货币",
            "核账状态",
            "支付方式",
            "核账人",
            "核账描述",
            "核账图片",
            "充值时间",
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
