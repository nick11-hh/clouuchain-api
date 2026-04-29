<?php

declare(strict_types=1);

namespace App\Jobs\Export;

use App\Helper\CosUtil;
use App\Models\ExcelExport;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Util;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Vtiful\Kernel\Excel;

class OrderExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $orderData;

    private array $detailData;

    private ExcelExport $excelExport;

    private array $header;

    /**
     * Create a new job instance.
     *
     * @param  ExcelExport  $excelExport
     * @param  array  $orderData
     * @param  array  $detailData
     */
    public function __construct(ExcelExport $excelExport, array $orderData, array $detailData)
    {
        $this->orderData = $orderData;
        $this->detailData = $detailData;
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

        if (! Storage::disk('admin_public')->exists('/excel/orders/')) {
            Storage::disk('admin_public')->makeDirectory('/excel/orders/');
        }

        $filePath = "/excel/orders/$fileName";

        $config = ['path' => Storage::disk('admin_public')->path('/excel/orders/')];

        try {
            $excel = new Excel($config);

            $excel = $excel->fileName($fileName, '订单信息')
                ->header($this->orderHeadings())
                ->data($this->getOrderData());

            $excel = $excel->addSheet('产品明细')
                ->header($this->detailHeadings())
                ->data($this->detailData);

//            $this->mergeDetailSheet($this->detailData, $excel);

            $path = $excel->output();

            // 上传到存储桶
            $url = CosUtil::localUploadToCos($filePath);

        } catch (\Throwable $throwable) {
            $this->excelExport->update([
                'status' => ExcelExport::STATUS_FAILED,
            ]);

            info('订单导出失败:' . $throwable);

            return;
        }

        info('订单导出成功: '. $path);

        $this->excelExport->update([
            'status' => ExcelExport::STATUS_DONE,
            'url' => $url,
        ]);
    }

    /**
     * @return array
     */
    protected function orderHeadings(): array
    {
        if (getCurrentUuid() === '8016a66761e5c96905badaac02edd08b') { // mate 返回英文
            return [
                'Order ID',
                'Platform',
                'Seller',
                'Order No',
                'Order Total Price',
                'Status',
                'Country',
                'Logistics Company',
                'Logistics Tracking Number',
                'Product Quote Price',
                'Logistics Quote Price',
                'Favourable Price(USD)',
                'Other Price(USD)',
                'Quote Total Price(USD)',
                'Supplement Price(USD)',
                'Refund_Price(USD)',
                'Total Price(USD)',
                'System Remark',
                'Warehouse Remark',
                'Purchase Sn',
                'Created At',
                'Payment At',
                'Submit At',
                'Cancel At',
                'Address First Name',
                'Address Last Name',
                'Address Country',
                'Address Company',
                'Address Phone',
                'Address Zip',
                'Address Province',
                'Address City',
                'Address1',
                'Address2',
                'Address tax',
                'Platform Tracking Number'
            ];
        }
        return [
            '订单号',
            '站点',
            '卖家',
            '平台编号',
            '订单价格',
            '状态',
            '国家',
            '物流渠道',
            '物流单号',
            '产品总报价(USD)',
            '物流报价(USD)',
            '优惠金额(USD)',
            '其他金额(USD)',
            '报价总价(USD)',
            '补收金额(USD)',
            '退款金额(USD)',
            '订单总金额(USD)',
            '系统备注',
            '仓库备注',
            '关联采购单',
            '下单时间',
            '付款时间',
            '提交时间',
            '订单取消时间',
            '收货地址-名',
            '收货地址-姓',
            '收货地址-国家',
            '公司/短地址',
            '收货地址-手机号',
            '收货地址-邮编',
            '收货地址-省份',
            '收货地址-城市',
            '收货地址-地址1',
            '收货地址-地址2',
            '收货地址-税号',
            '平台物流单号'
        ];
    }

    public function getOrderData(): array
    {
        $this->orderData =  collect($this->orderData)->map(function ($order) {
            return array_values($order);
        })->toArray();

        return $this->orderData;

    }

    /**
     * @return array
     */
    protected function detailHeadings(): array
    {
        if (getCurrentUuid() === '8016a66761e5c96905badaac02edd08b') { // mate 返回英文
            return [
                'Order ID',
                'Order No',
                'Country',
                'Logistics Tracking Number',
                'Product Name',
                'Spec',
                'SKU',
                'System SKU',
                'QTY',
                'Purchase Price',
                'Quote Price(USD)',
                'Once Price(USD)',
                'System Product Name',
                'Long(cm)',
                'Width(cm)',
                'Height(cm)',
                'Weight(g)',
                'System Spec'
            ];
        }
        return [
            '订单号',
            '平台编号',
            '国家',
            '物流单号',
            '品名',
            '规格',
            'SKU',
            '关联本地SKU',
            '数量',
            '产品原始价格',
            '产品报价(USD)',
            '产品一口价(USD)',
            '本地品名',
            '长(cm)',
            '宽(cm)',
            '高(cm)',
            '重量(g)',
            '产品规格'
        ];
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
    protected function mergeDetailSheet(array $data, Excel &$excel)
    {
        $points = [];
        $countData = count($data);

        for ($i = 0; $i < $countData;) {
            if ($i && $data[$i][2] == $data[$i - 1][2]) {
                for ($k = $i +1; $k <= $countData; $k++) {
                    if (! isset($data[$k]) || $data[$k][2] != $data[$i][2]) {
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
            $start = $point[0] + 2;
            $end = $point[1] + 2;

            $mergeColumns = ['A'];
            foreach ($mergeColumns as $column) {
                //合并这些单元格
                $excel->mergeCells("{$column}{$start}:{$column}{$end}", $data[$start - 2][$this->getExcelCol($column)]);
            }
        }
    }
}
