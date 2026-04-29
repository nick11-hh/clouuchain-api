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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Vtiful\Kernel\Excel;

class RegionPriceExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $data;

    private ExcelExport $excelExport;

    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param ExcelExport $excelExport
     * @param array $data
     * @param bool $mergeTile
     */
    public function __construct(ExcelExport $excelExport, array $data, protected bool $mergeTile = false)
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

        if (! Storage::disk('admin_public')->exists('/excel/prices/')) {
            Storage::disk('admin_public')->makeDirectory('/excel/prices/');
        }

        $config = ['path' => Storage::disk('admin_public')->path('/excel/prices/')];
        $filePath = "/excel/prices/{$fileName}";

        try {
            $excel = new Excel($config);

            $excel = $excel->fileName($fileName, '价格数据')
                ->data($this->data);

            //合并单元格
            $this->mergeDataSheet($this->data, $excel);

            $path = $excel->output();

            //合并表头
            if ($this->mergeTile) {
                info('需要合并');
                $this->mergeTitle($path);
            }

            if (!config('app.local_storage')) {
                CosUtil::send($filePath);
                Storage::disk('admin_public')->delete($filePath);
            }
        } catch (\Throwable $throwable) {
            $this->excelExport->update([
                'status' => ExcelExport::STATUS_FAILED,
            ]);

            info('价格数据导出失败:' . $throwable);

            return;
        }

        info('价格数据导出成功: '. $path);

        $this->excelExport->update([
            'status' => ExcelExport::STATUS_DONE,
        ]);
    }

    /**
     * @param string $path
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    protected function mergeSheet(string $path)
    {
        $spreadsheet = IOFactory::load($path);

        $sheet = $spreadsheet->getActiveSheet();

        $tmp = '';
        foreach ($sheet->getColumnIterator() as $key => $column) {
            $k = $key + 1;
            $value = $sheet->getCell("B$k")->getValue();

            if ($value === $tmp) {
                //合并这些单元格
                $sheet->mergeCells("B$k:B$key");
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
    }

    /**
     * @param string $path
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    protected function mergeTitle(string $path)
    {
        $spreadsheet = IOFactory::load($path);

        $sheet = $spreadsheet->getActiveSheet();

        $tmp = '';
        foreach ($sheet->getColumnIterator() as $column) {
            $index = $column->getColumnIndex();
            $pre = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($index) - 1);

            $value = $sheet->getCell("{$index}1")->getValue();

            info('index', [$index, $pre, $tmp, $value]);
            if ($value && $value == $tmp) {
                //合并这些单元格
                $sheet->mergeCells("{$pre}1:{$index}1");

                $sheet->getCell("{$pre}1")
                    ->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $tmp = $value;
        }

        $writer = new Xlsx($spreadsheet);

        $writer->save($path);
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

    /**
     * @param array $data
     * @param Excel $excel
     */
    protected function mergeDataSheet(array $data, Excel &$excel)
    {
        $points = [];
        $countData = count($data);

        for ($i = 0; $i < $countData;) {
            if ($i && $data[$i][0] == $data[$i - 1][0]) {
                for ($k = $i +1; $k <= $countData; $k++) {
                    if (! isset($data[$k]) || $data[$k][0] != $data[$i][0]) {
                        $points[] = [$i - 1, $k - 1];
                        $i = $k;
                        break;
                    }
                    continue;
                }
            }
            $i++;
        }

        foreach ($points as $key => $point) {
            $start = $point[0] + 1;
            $end = $point[1] + 1;

            $mergeColumns = ['A'];
            foreach ($mergeColumns as $column) {
                //合并这些单元格
                $excel->mergeCells("{$column}{$start}:{$column}{$end}", $data[$start - 1][$this->getExcelCol($column)]);
            }
        }
    }

}
