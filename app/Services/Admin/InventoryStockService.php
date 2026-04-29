<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\InventoryStock;
use App\Models\InventoryStockItem;
use App\Models\StockChangeLogs;
use App\Models\StockItem;
use App\Models\WarehouseGoodsAllocation;
use App\Services\Base\StockService;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class InventoryStockService extends BaseService
{
    protected $filterRules = [
        'order_sn' => ['like', 'order_sn'],
        'status' => ['=', 'status'],
        'warehouse_id' => ['=', 'warehouse_id'],
        'created_at' => ['between', ['begin_date', 'end_date']],
    ];

    protected $orderBy = [
        'id' => 'desc',
    ];

    protected InventoryStockItem $itemModel;

    public function __construct(InventoryStock $inventoryStock, InventoryStockItem $inventoryStockItem)
    {
        $this->request = request();
        $this->model = $inventoryStock;
        $this->itemModel = $inventoryStockItem;
        $this->query = $this->model->newQuery();
        $this->formData = $this->request->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with('warehouse');
        return parent::index();
    }

    public function show($id)
    {
        return $this->model::query()->with(['items', 'items.stock', 'warehouse'])->findOrFail($id);
    }

    public function mapData()
    {
        $data['status_list'] = transformArray($this->model::statusList());
        $data['type_list'] = transformArray($this->model::typeList());
        $data['method_list'] = transformArray($this->model::inventoryMethod());
        return $data;
    }

    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $stockItems = $this->getStockItem($params);
        if ($stockItems->isEmpty()) throw new AccidentException('没有匹配到可盘点的库存', Code::OPERATE_FAIL);
        return DB::transaction(function () use ($params, $stockItems) {
            $data = $this->model::init($params);
            $inventory = $this->model::query()->create($data);
            $items = [];
            $locationIds = [];
            foreach ($stockItems as $item) {
                $items = [
                    'warehouse_id' => $item->warehouse_id,
                    'inventory_id' => $inventory->id,
                    'stock_id' => $item->stock_id,
                    'stock_item_id' => $item->id,
                    'location_id' => $item->location_id,
                    'location_code' => $item->location_code,
                    'origin_quantity' => $item->total_quantity,
                    'actual_quantity' => $item->total_quantity,
                    'diff_quantity' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $locationIds[] = $item->location_id;
            }
            $this->itemModel::query()->insert($items);
            WarehouseGoodsAllocation::query()->whereIn('id', $locationIds)->update([
                'is_locked' => 1
            ]);
            return true;
        });
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function saveInventory($id, $params)
    {
        validator($params, $this->inventoryRules())->validate();
        $inventory = $this->model::query()->with('items')->findOrFail($id);
        if (!in_array($inventory->status, [InventoryStock::STATUS_WAIT, InventoryStock::STATUS_PROCESS])) {
            throw new AccidentException('当前状态不允许盘点');
        }
        return DB::transaction(function () use ($inventory, $params) {
            $inventory->status = InventoryStock::STATUS_PROCESS;
            $inventory->save();
            // 更新盘点单数据
            foreach ($params['items'] as $item) {
                InventoryStockItem::query()->where('id', $item['id'])->update([
                    'actual_quantity' => $item['actual_quantity'],
                    'diff_quantity' => $item['diff_quantity'],
                ]);
            }
            return true;
        });
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function submitInventory($id, $params)
    {
        validator($params, $this->inventoryRules())->validate();
        $inventory = $this->model::query()->findOrFail($id);
        if (!in_array($inventory->status, [InventoryStock::STATUS_WAIT, InventoryStock::STATUS_PROCESS])) {
            throw new AccidentException('当前状态不允许盘点');
        }
        return DB::transaction(function () use ($inventory, $params) {
            $inventory->status = InventoryStock::STATUS_FINISH;
            $inventory->save();
            // 更新盘点单数据
            foreach ($params['items'] as $item) {
                InventoryStockItem::query()->where('id', $item['id'])->update([
                    'actual_quantity' => $item['actual_quantity'],
                    'diff_quantity' => $item['diff_quantity'],
                ]);
            }
            // 更新库存
            $items = InventoryStockItem::query()->with(['stock', 'stockItem'])->where('inventory_id', $inventory->id)->get();
            foreach ($items as $item) {
                if ($item->diff_quantity == 0) continue;
                $stockService = new StockService($item->stock);
                if ($item->diff_quantity > 0) {
                    $stockService->increaseStock(StockChangeLogs::SOURCE_STOCK_INVENTORY, $item->diff_quantity, $item->stockItem);
                } else {
                    $stockService->deductionStock(StockChangeLogs::SOURCE_STOCK_INVENTORY, - $item->diff_quantity, $item->stockItem);
                }
            }
            // 释放库位
            $this->releaseLocation($items);
            return true;
        });
    }

    /**
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function cancel($id)
    {
        $inventory = $this->model::query()->with('items')->findOrFail($id);
        if (!in_array($inventory->status, [InventoryStock::STATUS_WAIT, InventoryStock::STATUS_PROCESS])) {
            throw new AccidentException('当前状态不允许作废', Code::OPERATE_FAIL);
        }
        return DB::transaction(function () use ($inventory) {
            $inventory->status = InventoryStock::STATUS_QUIT;
            $inventory->save();
            // 释放库位
            $this->releaseLocation($inventory->items);
            return true;
        });
    }

    /** 释放库位
     * @param $items
     * @return void
     */
    public function releaseLocation($items)
    {
        $ids = array_unique($items->pluck('location_id')->toArray());
        WarehouseGoodsAllocation::query()->whereIn('id', $ids)->update([
            'is_locked' => 0
        ]);
    }

    /**
     * @param $params
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    protected function getStockItem($params)
    {
        $ids = array_column($params['items'], 'id');
        $query = StockItem::query()->where('warehouse_id', $params['warehouse_id']);
        $query->when(!$params['is_zero'], function ($query) {
            $query->where('total_quantity', '>', 0);
        });
        if ($params['type'] === InventoryStock::TYPE_SKU) {
            $query->whereIn('id', $ids);
        } else if ($params['type'] === InventoryStock::TYPE_LOCATION) {
            $query->whereIn('location_id', $ids);
        }
        return $query->get();
    }

    protected function rules()
    {
        return [
            'warehouse_id' => 'required|int',
            'type' => 'required|int',
            'method' => 'required|int',
            'operator_id' => 'required|int',
            'is_zero' => 'required|int',
            'remark' => 'sometimes|string',
            'items' => 'required_if:is_cn_address,1|array',
            'items.*.id' => 'required|int',
        ];
    }

    protected function inventoryRules()
    {
        return [
            'items' => 'required|array',
            'items.*.actual_quantity' => 'required|int',
            'items.*.diff_quantity' => 'required|int',
        ];
    }


}
