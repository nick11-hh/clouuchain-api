<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\Goods;
use App\Models\Stock;
use App\Models\StockChangeLogs;
use App\Models\StockItem;
use App\Exceptions\AccidentException;

class WarehouseStockService extends BaseService
{
    protected $filterRules = [
        'goods_name,spec_name,sku' => ['like', 'keyword'],
        'warehouse_id' => ['=', 'warehouse_id'],
        'custom_id' => ['=', 'custom_id'],
        'goods_type' => ['=', 'goods_type'],
        'packing_materials_type' => ['=', 'packing_materials_type'],
    ];

    protected $orderBy = [
        'id' =>'desc',
    ];

    public function __construct(Stock $stock)
    {
        $this->request = request();
        $this->model = $stock;
        $this->query = $this->model->newQuery();
        $this->formData = $this->request->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['warehouse', 'customer']);
        return parent::index();
    }

    public function show($id)
    {
        return $this->model::query()->with(['items', 'warehouse'])->findOrFail($id);
    }

    public function stockItems($params)
    {
        $sku = $params['sku'] ?? '';
        $locationId = $params['location_id'] ?? '';
        $warehouseId = $params['warehouse_id'] ?? '';
        $query = StockItem::query()->with('stock');
        $query->when($sku, function ($query) use ($sku) {
            return $query->where('sku', $sku);
        });
        $query->when($locationId, function ($query) use ($locationId) {
            return $query->where('location_id', $locationId);
        });
        $query->when($warehouseId, function ($query) use ($warehouseId) {
            return $query->where('warehouse_id', $warehouseId);
        });
        return $query->paginate($params['page_size'] ?? 10);
    }

    public function records()
    {
        $keyword = $this->formData['keyword'] ?? '';
        $warehouseId = $this->formData['warehouse_id'] ?? '';
        $beginDate = $this->formData['begin_date'] ?? '';
        $endDate = $this->formData['end_date'] ?? '';
        $query = StockChangeLogs::query()->with('warehouse');
        $query->when($keyword, function ($query) use ($keyword) {
            return $query->where(function ($query) use ($keyword) {
                return $query->where('goods_name', $keyword)->orWhere('sku', $keyword);
            });
        });
        $query->when($warehouseId, function ($query) use ($warehouseId) {
            return $query->where('warehouse_id', $warehouseId);
        });
        $query->when($beginDate, function ($query) use ($beginDate) {
            return $query->where('created_at', '>', $beginDate);
        });
        $query->when($endDate, function ($query) use ($endDate) {
            return $query->where('created_at', '<', $endDate);
        });
        return $query->latest('id')->paginate($this->formData['size'] ?? 10);
    }

    public function getCustomerStock($customerId, $params)
    {
        $warehouseId = $params['warehouse_id'] ?? 0;

        //根据客户ID查询包材类型并且可用库存大于0的商品
        $stock = $this->model::query()
            ->where('custom_id', $customerId)
            ->where('warehouse_id', $warehouseId)
            ->where('goods_type', Stock::GOODS_TYPE_PACKING_MATERIALS)
            ->where('quantity', '>', 0)->get();

        if ($stock->isEmpty()) {
            throw new AccidentException('未查询到客户的备货库存', Code::OPERATE_FAIL);
        }

        $goodsIds = $stock->pluck('goods_id')->toArray();

        //sku的库存数量
        $skuStock = $stock->pluck('quantity', 'sku_id')->toArray();

        //sku的库存数量
        $skuStockId = $stock->pluck('id', 'sku_id')->toArray();

        //根据商品ID查询出对应的商品规格及sku信息
        $goodsQuery = Goods::query()->with('skus')->whereIn('id', $goodsIds);
        $goodsQuery->select('id', 'goods_name', 'spu', 'cover_image', 'options');

        $keyword = $params['keyword'] ?? '';
        if ($keyword) {
            $goodsQuery->where(function ($query) use ($keyword) {
                $query->orWhere('spu', 'like', '%'.$keyword.'%')
                      ->orWhere('goods_name', 'like', '%'.$keyword.'%');
            });
        }
        $goods = $goodsQuery->get();

        //匹配sku可用库存
        $goods->each(function ($item) use ($skuStock, $skuStockId) {
            $item->skus->each(function ($sku) use ($skuStock, $skuStockId) {
                $sku->quantity = $skuStock[$sku->id] ?? 0;
                $sku->stock_id = $skuStockId[$sku->id] ?? 0;
            });
        });

        return $goods;
    }

}
