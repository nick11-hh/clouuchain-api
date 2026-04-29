<?php

namespace App\Services\Client;

use App\Models\Stock;
use App\Models\StockChangeLogs;
use App\Models\StockItem;

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
        $this->query->with(['warehouse', 'customer'])->where(['custom_id' => getCustomId()]);
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

}
