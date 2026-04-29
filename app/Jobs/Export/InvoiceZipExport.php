<?php

declare(strict_types=1);

namespace App\Jobs\Export;

use App\Helper\CosUtil;
use App\Models\ExcelExport;
use App\Models\InvoiceRecords;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * 发票压缩包导出任务
 */
class InvoiceZipExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private ExcelExport $excelExport;

    /**
     * @var array<int>
     */
    private array $invoiceIds;

    /**
     * @param ExcelExport $excelExport
     * @param array<int> $invoiceIds
     */
    public function __construct(ExcelExport $excelExport, array $invoiceIds)
    {
        $this->excelExport = $excelExport;
        $this->invoiceIds = $invoiceIds;
    }

    public function handle(): void
    {
        $storagePath = '/invoice_zip/';
        $fileName = $this->excelExport->name;

        if (!Storage::disk('admin_public')->exists($storagePath)) {
            Storage::disk('admin_public')->makeDirectory($storagePath);
        }

        $zipRelativePath = $storagePath . $fileName;
        $zipAbsolutePath = Storage::disk('admin_public')->path($zipRelativePath);

        try {
            $records = InvoiceRecords::query()
                ->whereIn('id', $this->invoiceIds)
                ->get(['id', 'invoice_no', 'storage_url']);

            $zip = new ZipArchive();
            if ($zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('无法创建ZIP文件');
            }

            $addedCount = 0;
            foreach ($records as $record) {
                if (empty($record->storage_url)) {
                    continue;
                }

                $content = @file_get_contents($record->storage_url);
                if ($content === false) {
                    continue;
                }

                $path = parse_url($record->storage_url, PHP_URL_PATH) ?? '';
                $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
                $entryName = ($record->invoice_no ?: ('invoice_' . $record->id)) . '_' . Str::random(6) . '.' . $ext;

                $zip->addFromString($entryName, $content);
                $addedCount++;
            }

            $zip->close();

            if (!file_exists($zipAbsolutePath) || filesize($zipAbsolutePath) === 0 || $addedCount === 0) {
                throw new \RuntimeException('ZIP文件生成失败');
            }

            $url = CosUtil::localUploadToCos($zipRelativePath);

            $this->excelExport->update([
                'status' => ExcelExport::STATUS_DONE,
                'url' => $url,
            ]);
        } catch (\Throwable $throwable) {
            info('发票压缩包导出失败:' . $throwable->getMessage(), [
                'invoice_ids' => $this->invoiceIds,
                'export_id' => $this->excelExport->id,
            ]);

            $this->excelExport->update([
                'status' => ExcelExport::STATUS_FAILED,
            ]);
        }
    }
}
