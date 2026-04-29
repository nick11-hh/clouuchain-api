<?php

namespace App\Http\Controllers\Admin;

use App\Helper\CosUtil;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BarcodeService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;

class TestController extends Controller
{
    public function printSendLable()
    {
        // $brcode = BarcodeService::generateCode('5493298430180');

        // $mPdf = new Fpdi('L', 'mm', [100, 100]);
        // $mPdf->setHeaderData($ln = '', $lw = 0, $ht = '', $hs = '', $tc = [0, 0, 0], $lc = [255, 255, 255]);
        // $mPdf->setFooterData($tc = [0, 0, 0], $lc = [255, 255, 255]);

        $fileName = 'send_label_' . Str::random(8) . '.pdf';
        // $data = $this->model->newQuery()->with('goods')->whereIn('id', [])->get()->toArray();
        $data = Order::with(['logisticsApply', 'lineItems'])->where('id', 89)->first();

        $items = [];
        $skuNumber = $data->lineItems->count();
        $data->lineItems->each(function($item) use(&$items){
            $items[] = [
                'img' => $item->imgs[0] ?? '',
                'name' => $item->title,
                'spec' => $item->variant_title,
                'quantity' => $item->quantity,
                'stock' => '',
            ];
        });

        $total = array_sum(array_column($items, 'quantity'));
        $labelData = [
            'brcode' => BarcodeService::generateCode($data->order_id),
            'name' => $data->name,
            'order_id' => $data->order_id,
            'way_bill_number' => $data->logisticsApply->way_bill_number,
            'print_time' => date('Y-m-d H:i:s'),
            'sku_num' => $skuNumber,
            'total' => $total,
            'items' => $items
        ];
        // var_dump($data->lineItems->toArray());die();
        // $res = [];
        // foreach($data as $sku) {
        //     $res[] = [
        //         'sku_barcode' => BarcodeService::generateCode($sku['sku']),
        //         'goods_name' => $sku['goods']['name'],
        //         'spec_name' => trim($sku['spec_name'],'"'),
        //         'spu' => $sku['goods']['spu'],
        //         'sku' => $sku['sku']
        //     ];
        // }

        try {
            $fullPath = Storage::disk('admin_public')->path($fileName);

            \PDF::loadView(
                'labels.send-label', ['data' => $labelData]
            )->setOptions([
                'page-height' => 100,
                'page-width' => 100,
                'dpi' => 300,
                'margin-top'=>0,
                'margin-bottom'=>0,
                'margin-left'=>0,
                'margin-right'=>0
            ])
                ->save($fullPath, true);

        } catch (\Throwable $throwable) {
            info('SKU标签生成失败', ['msg' => $throwable->getMessage(), 'file' => $throwable->getFile(), 'line' => $throwable->getLine()]);

            info('SKU标签生成失败' . $throwable->getTraceAsString());
            return false;
        }

        return Storage::disk('admin_public')->url($fileName);


        // return view('labels.send-label', ['brcode' => $brcode, 'printTime' => date('Y-m-d H:i:s'), 'orderCode' => '5493298430180']);
    }
}
