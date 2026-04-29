<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\GoodsSku;
use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\Order;
use App\Models\PurchaseInboundRelationModel;
use App\Models\PurchaseOrdersModel;
use App\Services\BarcodeService;
use App\Services\Base\StockService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use App\Exceptions\AccidentException;

class InboundOrderService extends BaseService
{
    public $filterRules = [
        'inbound_sn'        => ['like', 'keyword'],
        'logistics_sn'      => ['like', 'logistics_sn'],
        'purchase:order_sn' => ['like', 'purchase_sn'],
        'inbound_type'      => ['=', 'inbound_type'],
        'custom_id'         => ['=', 'custom_id'],
        'warehouse_id'      => ['=', 'warehouse_id'],
        'created_at'        => ['between', ['begin_date', 'end_date']],
        'status'            => ['=', 'status'],
        'stock_order_sn'    => ['like', 'stock_order_sn'],
    ];

    private InboundOrderItem $itemModel;

    public function __construct()
    {
        $this->model = new InboundOrder();
        $this->itemModel = new InboundOrderItem();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['items', 'warehouse', 'custom', 'purchase:id,order_sn'])
            ->withSum('items', 'quantity')->withSum('items', 'sign_quantity')->withSum('items', 'inbound_quantity');
        $this->query->latest();
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['items', 'warehouse', 'custom'])
            ->withSum('items', 'quantity')->withSum('items', 'sign_quantity')->findOrFail($id);
    }

    public function statusCount()
    {
        //首次加载列表页时会传状态值，导致其他状态的统计数量都为0 所以暂时先过滤状态查询条件
        unset($this->filters['status']);
        $this->setFilter();

        $statuses = $this->query->selectRaw('status, count(*) as count')->groupBy('status')->get();
        $statusCounts = $statuses->pluck('count', 'status');

        $allStatuses = array_keys($this->model::statusList()); //获取所有状态
        $statusCounts = collect($allStatuses)
            ->mapWithKeys(function ($status) use ($statuses) {
                // 如果状态存在于统计结果中，返回它的计数，否则返回0
                return [$status => $statuses->firstWhere('status', $status)?->count ?? 0];
            });

        $data = [];
        foreach ($statusCounts as $status => $count) {
            $data[] = ['status' => $status, 'count' => $count];
        }

        return $data;
    }


    /**
     * @param $params
     * @return mixed
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($params) {
            $data = $this->model::init($params);
            $inbound = $this->model::query()->create($data);
            foreach ($params['items'] as $item) {
                $item = $this->getInboundItemByGoodsSku($item);
                $itemData = $this->itemModel::init($inbound->id, $item);
                $this->itemModel::query()->create($itemData);
            }
            return $inbound;
        });
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($id, $params) {
            $inbound = $this->model::query()->with('items')->findOrFail($id);
            $data = $this->model::init($params, 2);
            $inbound->update($data);
            $itemIds = [];
            foreach ($params['items'] as $item) {
                $item = $this->getInboundItemByGoodsSku($item);
                if (!empty($item['id'])) {
                    $inboundItem = $this->itemModel::query()->findOrFail($item['id']);
                    $itemData = $this->itemModel::init($inbound->id, $item);
                    $inboundItem->update($itemData);
                    $itemIds[] = $item['id'];
                } else {
                    $itemData = $this->itemModel::init($inbound->id, $item);
                    $this->itemModel::query()->create($itemData);
                }
            }
            // 删除多余产品sku
            $oldItemsIds = $inbound->items->pluck('id')->toArray();
            $deleteIds = array_diff($oldItemsIds, $itemIds);
            if (!empty($deleteIds)) {
                $this->itemModel::query()->whereIn('id', $deleteIds)->delete();
            }
            return $inbound;
        });
    }

    /** 数据转换
     * @param $item
     * @return mixed
     */
    protected function getInboundItemByGoodsSku($item)
    {
        $goodsSku = GoodsSku::with('goods')->findOrFail($item['goods_sku_id']);
        $item['goods_id'] = $goodsSku->goods->id ?? 0;
        $item['goods_name'] = $goodsSku->goods->goods_name ?? '';
        $item['goods_sku'] = $goodsSku->sku_id;
        $item['spec_name'] = $goodsSku->spec_name;
        $item['sku_image'] = $goodsSku->images[0] ?? '';
        $item['goods_type'] = $goodsSku->goods->goods_type ?? 1;//商品类型 1-产品 2-包材
        $item['packing_materials_type'] = $goodsSku->goods->packing_materials_type ?? 0; //包材类型 1-包装袋 2-纸箱 3-定制盒子 4-贴纸 5-卡片 99-其他'
        return $item;
    }

    /** 取消入库单
     * @param $id
     * @return bool
     */
    public function cancel($id)
    {
        $inboundOrder = $this->model::query()->findOrFail($id);
        $inboundOrder->status = InboundOrder::STATUS_CANCEL;
        return $inboundOrder->save();
    }


    public function deletes($params)
    {
        if (empty($params['ids'])) throw new AccidentException('请选择需要删除的采购单', Code::OPERATE_FAIL);
        $list = $this->model::with('items')->whereIn('id', $params['ids'])->get();
        foreach ($list as $inbound) {
            $inbound->items()->delete();
            $inbound->delete();
        }
        return true;
    }

    /**
     * @param $params
     * @return array|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws Exception
     */
    public function scanData($params)
    {
        $scanSn = $params['scan_sn'] ?? '';
        if (empty($scanSn)) throw new AccidentException('扫描参数不能为空', Code::OPERATE_FAIL);
        return $this->model::query()->with(['items.goodsSku', 'warehouse', 'custom', 'purchase'])->where(function ($query) use ($scanSn) {
            return $query->where('inbound_sn', $scanSn)->orWhere('logistics_sn', $scanSn)
                ->orWhere(function ($query) use ($scanSn) {
                    $query->whereHas('purchase', function ($query) use ($scanSn) {
                        $query->where('order_sn', $scanSn);
                    });
                });
        })->firstOrFail();
    }


    /** 入库单签收
     * @param $id
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function sign($id, $params)
    {
        validator($params, $this->signRules())->validate();
        $inboundOrder = $this->model::query()->with(['items', 'purchase'])->findOrFail($id);
        if ($inboundOrder->status >= InboundOrder::STATUS_RECEIVED) throw new AccidentException('当前状态不允许签收', Code::OPERATE_FAIL);
        return DB::transaction(function () use ($inboundOrder, $params) {
            $signSuccess = 1;  // 是否全部签收
            $itemParams = collect($params['items'])->keyBy('item_id');
            foreach ($inboundOrder->items as $item) {
                if (isset($itemParams[$item->id])) {
                    $item->sign_quantity += $itemParams[$item->id]['quantity'];
                    if ($item->sign_quantity > $item->quantity) {
                        throw new AccidentException('当前签收数量大于预计入库数量', Code::OPERATE_FAIL);
                    }
                    $item->weight = $itemParams[$item->id]['weight'];
                    $item->length = $itemParams[$item->id]['length'];
                    $item->width = $itemParams[$item->id]['width'];
                    $item->height = $itemParams[$item->id]['height'];

                    $item->save();

                    //更新关联sku的规格尺寸
                    $skuData = [];
                    if (!empty($item->weight)) {
                        $skuData['weight'] = $item->weight;
                    }
                    if (!empty($item->length) && !empty($item->width) && !empty($item->height)) {
                        $skuData['length'] = $item->length;
                        $skuData['width'] = $item->width;
                        $skuData['height'] = $item->height;
                    }

                    GoodsSku::query()->where('id', $item->goods_sku_id)->update($skuData);
                }
                if ($item->sign_quantity < $item->quantity) $signSuccess = 0;
            }
            $inboundOrder->status = InboundOrder::STATUS_RECEIVING;

            if ($signSuccess) {
                $inboundOrder->status = InboundOrder::STATUS_RECEIVED;

                //采购单设置为已完成
                if ($inboundOrder->purchase->isNotEmpty()) {
                    foreach ($inboundOrder->purchase as $purchase) {
                        PurchaseOrdersModel::query()->where('id', $purchase->id)->update(['status' => PurchaseOrdersModel::STATUS_IN_STOCK]);
                    }
                }

                //备货订单设置为已完成并更新完成时间 commited_at 这个字段设为完成时间
                if ($inboundOrder->stock_order_sn ?? null) {
//                    Order::query()->where('order_id', $inboundOrder->stock_order_sn)->update(['order_status' => Order::STATUS_DELIVERY_SUCCESS, 'commited_at' => now()]);
                }
            }

            if (empty($inboundOrder->sign_time)) $inboundOrder->sign_time = now();

            return $inboundOrder->save();
        });
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function inbound($id, $params)
    {
        validator($params, $this->inboundRules(), [
            'items.*.inbound_info.*.location_code.required' => '部分SKU未选择库存',
            'items.*.inbound_info.*.quantity' => '部分SKU未输入上架数量',
        ])->validate();

        $inboundOrder = $this->model::query()->with('items')->findOrFail($id);
        if ($inboundOrder->status != InboundOrder::STATUS_RECEIVED) throw new AccidentException('当前状态不允许上架', Code::OPERATE_FAIL);
        return DB::transaction(function () use ($inboundOrder, $params) {
            $itemsParams = collect($params['items'])->keyBy('item_id');
            $inboundSuccess = 1;  // 是否全部上架

            foreach ($inboundOrder->items as $item) {
                if (isset($itemsParams[$item->id])) {
                    $inboundInfo = $itemsParams[$item->id]['inbound_info'] ?? [];
                    if (empty($inboundInfo)) continue;

                    $item->inbound_quantity += collect($inboundInfo)->sum('quantity');
                    if ($item->inbound_quantity > $item->sign_quantity) {
                        throw new AccidentException('当前入库数量不能大于签收数量', Code::OPERATE_FAIL);
                    }
                    $item->inbound_info = array_merge($item->inbound_info ?: [], $inboundInfo);
                    $item->save();
                    $stockService = new StockService();
                    $stockService->inboundOrderInStorage($inboundOrder, $item, $inboundInfo);
                }
                if ($item->inbound_quantity < $item->sign_quantity) $inboundSuccess = 0;
            }
            if ($inboundSuccess) {
                $inboundOrder->status = InboundOrder::STATUS_IN_STOCK;
                $inboundOrder->inbound_time = now();
            }
            return $inboundOrder->save();
        });
    }

    public function purchaseToInbound($purchaseIds)
    {
        if (empty($purchaseIds)) return false;

        $purchaseIds = is_array($purchaseIds) ? $purchaseIds : [$purchaseIds];

        return DB::transaction(function () use ($purchaseIds) {
            $purchaseOrders = PurchaseOrdersModel::query()->with('skus')->whereIn('id', $purchaseIds)->get();

            if ($purchaseOrders->isEmpty()) return false;

            $itemData = $relationData = [];
            foreach ($purchaseOrders as $purchase) {
                $purchase->logistics_sn = $purchase->shipment_number;//物流单号
                $purchase->inbound_type = InboundOrder::TYPE_PURCHASE;//入库单类型 采购入库
                $purchase->remark = '采购单待入库状态，系统自动生成入库单';

                //采购入库的货主都设置为 本企业
                $purchase->custom_id = 0;

                $data = $this->model::init($purchase);
                $inbound = $this->model::query()->create($data);

                foreach ($purchase->skus as $item) {
                    $item->goods_sku_id = $item->sku_id ?? 0;
                    $item = $this->getInboundItemByGoodsSku($item);

                    $itemData[] = $this->itemModel::init($inbound->id, $item);
                }

                //采购单跟入库单关联
                $relationData[] = [
                    'purchase_id' => $purchase->id,
                    'inbound_id' => $inbound->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            //入库产品
            $this->itemModel::query()->insert($itemData);

            //写入关系表
            PurchaseInboundRelationModel::query()->insert($relationData);

            return true;
        });
    }

    public function printInboundItemsLabel()
    {
        validator($this->formData, [
            'items' => 'required|array',
            'items.*.item_id' => 'required|int',
            'items.*.print_number' => 'required|int',
        ])->validate();

        $itemsParams = array_column($this->formData['items'],'print_number', 'item_id');
        $itemsIds = array_keys($itemsParams);
        $items = InboundOrderItem::with(['inboundOrder', 'goods'])->whereIn('id',$itemsIds)->get();

        $labels = [];
        $items->each(function ($item) use (&$labels, $itemsParams) {
            $printNumber = $itemsParams[$item->id] ?? 1;

            for ($i = 1; $i <= $printNumber; $i++) {
                $labels[] = $this->generatePdf($item);
            }
        });

        return $this->mergePdf($labels);
    }

    public function generatePdf($data)
    {
        $fileName = 'inbound_items_label_' . Str::random(8) . '.pdf';

        $labelData = [
            'goods_name' => $data->goods_name,
            'spec_name' => $data->spec_name,
            'spu' => $data->goods->spu ?? '',
            'sku_barcode' => BarcodeService::generateCode($data->goods_sku),
            'sku' => $data->goods_sku,
        ];

        try {
            $fullPath = Storage::disk('admin_public')->path($fileName);

            \PDF::loadView(
                'labels.inbound-items-label', ['data' => $labelData]
            )->setOptions([
                'page-width' => 70,
                'page-height' => 40,
                'dpi' => 300,
                'margin-top' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'margin-right' => 0,
            ])->save($fullPath, true);

            return $fullPath;
        } catch (\Throwable $throwable) {
            info('入库单标签生成失败', ['msg' => $throwable->getMessage(), 'file' => $throwable->getFile(), 'line' => $throwable->getLine()]);

            info('入库单标签生成失败' . $throwable->getTraceAsString());
            return false;
        }
    }

    /**
     * 合并pdf
     * @throws Exception
     */
    public function mergePdf($labels)
    {
        $path = storage_path('app/public');
        $mergeFile = '/admin/';
        $name = Str::random(8) . '-merge.pdf';

        $outputPath = $path . $mergeFile . $name;

        $filePath = [$outputPath];
        // 本地url
        $localUrl = config('app.url');
        foreach ($labels as $label) {
            if (!$label) {
                continue;
            }
            // 解析 URL
            $parsed_url = parse_url($label);
            // 获取协议和主机名
            $protocol = $parsed_url['scheme'] ?? '';
            $host = $parsed_url['host'] ?? '';

            $host = $protocol . '://' . $host;

            if ($localUrl === $host) {
                $labelPath = str_replace('/storage', '', $parsed_url['path'],);
                $filePath[] = $path . $labelPath;
            } else {
                $newFile = $path . '/admin/' . Str::random(10) . '-tmp.pdf';
                $tmpPdf = file_get_contents($label);
                file_put_contents($newFile, $tmpPdf);
                $filePath[] = $newFile;
            }
        }

        $files = implode(' ', $filePath);

        $process = Process::fromShellCommandline("gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=" . $files);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (!config('app.local_storage')) {
            try {
                Storage::disk('cos')
                       ->putFileAs(
                           '/admin',
                           $outputPath,
                           $name
                       );
            } catch (Exception $e) {
                logger('cos' . $e->getMessage());
            }

        }

        unset($filePath[0]);

        if (!config('app.local_storage')) {
            foreach ($filePath as $file) {
                unlink($file);
            }
        }

        if (!file_exists($outputPath)) {
            throw new AccidentException('打印标签失败', Code::OPERATE_FAIL);
        }

        return config('app.url') . '/storage' . $mergeFile . $name;
    }

    protected function rules()
    {
        return [
            'custom_id' => 'required|int',
            'warehouse_id' => 'required|int',
            'logistics_sn' => 'sometimes|nullable|string',
            'expect_time' => 'sometimes',
            'remark' => 'sometimes',
            'items' => 'required|array',
            'items.*.goods_sku_id' => 'required|int',
            'items.*.quantity' => 'required|int',
        ];
    }

    protected function signRules()
    {
        return [
            'items' => 'required|array',
            'items.*.item_id' => 'required|int',
            'items.*.quantity' => 'required|int',
            'items.*.weight' => 'sometimes|nullable|numeric',
            'items.*.width' => 'sometimes|nullable|numeric',
            'items.*.length' => 'sometimes|nullable|numeric',
            'items.*.height' => 'sometimes|nullable|numeric',
        ];
    }

    protected function inboundRules()
    {
        return [
            'items' => 'required|array',
            'items.*.item_id' => 'required|int',
            'items.*.inbound_info' => 'sometimes|array',
            'items.*.inbound_info.*.location_code' => 'required|string',
            'items.*.inbound_info.*.quantity' => 'required|int|min:1'
        ];
    }

}


