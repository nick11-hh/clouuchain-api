<?php

namespace App\Jobs\Export;

use App\Models\ThirdPartyWarehouseConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Vtiful\Kernel\Excel;
use App\Helper\CosUtil;
use App\Models\ExcelExport;
use App\Models\Order;
use App\Exports\OrderDianxiaomiExport;

/**
 * 店小秘订单导出队列
 * Class DianXiaoMiOrderExport
 * @package App\Jobs\Export
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/14 14:24
 */
class DianXiaoMiOrderExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $formData;

    public Builder $query;

    public ExcelExport $excelExport;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ExcelExport $excelExport, array $formData)
    {
        $this->formData = $formData;
        $this->excelExport = $excelExport;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $storagePath = '/excel/dianxiaomi_order/';
        $fileName = $this->excelExport['name'];

        if (!Storage::disk('admin_public')->exists($storagePath)) {
            Storage::disk('admin_public')->makeDirectory($storagePath);
        }

        $filePath = $storagePath . $fileName;

        $config = ['path' => Storage::disk('admin_public')->path($storagePath)];

        try {
            $excel = new Excel($config);

            $excel = $excel->fileName($fileName, '店小秘订单信息')
                ->header($this->getHeadings())
                ->data($this->getData());

            $path = $excel->output();

            // 上传到存储桶
            $url = CosUtil::localUploadToCos($filePath);

        } catch (\Throwable $throwable) {
            $this->excelExport->update([
                'status' => ExcelExport::STATUS_FAILED,
            ]);

            info('店小秘订单信息导出失败:' . $throwable);

            return;
        }

        info('店小秘订单信息导出成功: '. $path);

        $this->query->update([
            'fulfillment_platform' => ThirdPartyWarehouseConfig::PLATFORM_DIANXIAOMI
        ]);

        $this->excelExport->update([
            'status' => ExcelExport::STATUS_DONE,
            'url' => $url,
        ]);
    }

    /**
     * 获取每列的标题
     * @return string[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/12 14:29
     */
    public function getHeadings(): array
    {
        return [
            '订单号',
            '店铺账号',
            'sku',
            '属性',
            '数量',
            '单价',
            '总运费',
            '币种',
            '买家指定物流',
            '发货仓库',
            '买家姓名',
            '地址1',
            '地址2',
            '城市',
            '省/州',
            '国家二字码',
            '邮编',
            '电话',
            '手机',
            'E-mail',
            '买家税号',
            '门牌号',
            '公司名',
            '订单备注',
            '图片网址',
            '出售链接',
            '中文报关名',
            '英文报关名',
            '申报金额(USD)',
            '申报重量(g)',
            '材质',
            '用途',
            '海关编码',
            '报关属性',
            '卖家税号',
            '下单时间(北京时间)',
            '客服备注',
            '拣货备注',
        ];
    }

    /**
     * 获取数据
     * @return array[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/12 14:28
     */
    public function getData()
    {
        $this->query = Order::query();
        if (isset($this->formData['order_ids']) && !empty($this->formData['order_ids'])) {
            $this->query->whereIn('id', $this->formData['order_ids']);
        } else {
            $this->queryCondition();
        }

        //获取店小秘的导出订单数据
        $data = (new OrderDianxiaomiExport($this->query))->array();
        logger('导出店小秘订单数据', ['data' => $data]);
        return $data;
    }

    /**
     * 处理查询条件
     * @return bool
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/14 14:23
     */
    public function queryCondition(): bool
    {
        //无关联采购单
        if (isset($this->formData['purchase']) && !empty($this->formData['purchase'])) {
            $this->query->doesntHave('purchaseOrder');
        }

        //订单状态
        if(isset($this->formData['status']) && !empty($this->formData['status'])) {
            $this->query->whereIn('order_status', $this->formData['status']);
        }

        //店铺ID
        if(isset($this->formData['shop_ids']) && !empty($this->formData['shop_ids'])) {
            $this->query->whereIn('shop_id', $this->formData['shop_ids']);
        }

        //无运单号
        if (!empty($this->formData['no_waybill_number'])) {
            $this->query->where(function ($query) {
                $query->whereHas('logisticsApply', function ($query) {
                    $query->whereNull('way_bill_number')->orWhere('way_bill_number', '');
                })->orDoesntHave('logisticsApply');
            });
        }


        //订单关键字
        if (!empty($this->formData['order_keyword'] ?? '')) {
            switch ($this->formData['order_keyword_type']) {
                case 1:
                    //平台订单
                    $this->query->where('order_id', 'like', '%'.$this->formData['order_keyword'].'%');
                    break;
                case 2:
                    //平台编号
                    $this->query->where('name', 'like', '%'.$this->formData['order_keyword'].'%');
                    break;
                case 3:
                    //系统单号
                    $this->query->where('custom_order_id', 'like', '%'.$this->formData['order_keyword'].'%');
                    break;
            }
        }

        //产品关键字
        if (!empty($this->formData['product_keyword'] ?? '')) {
            switch ($this->formData['product_keyword_type']) {
                case 1:
                    //平台SKU
                    $this->query->whereHas('allLineItems', function ($query) {
                        $query->where('sku', 'like', '%'.$this->formData['product_keyword'].'%');
                    });
                    break;
                case 2:
                    //关联SKU
                    $this->query->whereHas('allLineItems.mapping.goodsSku.goods', function ($query) {
                        $query->where('sku_id', 'like', '%'.$this->formData['product_keyword'].'%');
                    });
                    break;
                case 3:
                    //产品名称
                    $this->query->whereHas('allLineItems', function ($query) {
                        $query->where('name', 'like', '%'.$this->formData['product_keyword'].'%')
                            ->orWhere('title', 'like', '%'.$this->formData['product_keyword'].'%');
                    });
                    break;
            }
        }

        //物流关键字
        if (!empty($this->formData['logistics_keyword'] ?? '')) {
            switch ($this->formData['logistics_keyword_type']) {
                case 1:
                    //物流单号
                    $this->query->whereHas('logisticsApply', function ($query) {
                        $query->where('way_bill_number', 'like', '%'.$this->formData['logistics_keyword'].'%');
                    });
                    break;
                case 2:
                    //包裹号
                    $this->query->whereHas('expressOrders', function ($query) {
                        $query->where('package_sn', 'like', '%'.$this->formData['logistics_keyword'].'%');
                    });
                    break;
                case 3:
                    //收件人
                    $this->query->whereHas('shippingAddress', function ($query) {
                        $query->where('name', 'like', '%'.$this->formData['logistics_keyword'].'%');
                    });
                    break;
                case 4:
                    //邮箱
                    $this->query->whereHas('shippingAddress', function ($query) {
                        $query->where('email', 'like', '%'.$this->formData['logistics_keyword'].'%');
                    });
                    break;
            }
        }

        return true;
    }

}
