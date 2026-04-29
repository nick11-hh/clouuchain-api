<?php

namespace App\Services\Base;

use App\Models\Stock;
use App\Models\StockChangeLogs;
use App\Models\StockItem;
use App\Models\StockLock;
use App\Models\StockLockItem;
use App\Models\StockLockLog;
use App\Models\WarehouseGoodsAllocation;
use Exception;
use App\Exceptions\AccidentException;

class StockService
{

    protected mixed $stock;

    protected string $operateSn = '';

    protected int $operateId = 0;

    public function __construct($stock = null)
    {
        $this->stock = $stock;
    }

    public function setOperateSn($operateSn): static
    {
        $this->operateSn = $operateSn;
        return $this;
    }

    public function setOperateId($operateId)
    {
        $this->operateId = $operateId;
        return $this;
    }


    // @todo 弃用，等待删除
    /** 采购单入库
     * @throws Exception
     */
    /*public function purchaseInStorage($purchase, $purchaseItem, $location)
    {
        $this->operateSn = $purchase->order_sn;
        $this->stock = Stock::query()->where('sku', $purchaseItem->sku)
            ->where('warehouse_id', $purchase->warehouse_id)->first();
        if (empty($this->stock)) {
            $data = [
                'warehouse_id' => $purchase->warehouse_id ?? 0,
                'custom_id' => $purchase->custom_id ?? 0,
                'goods_id' => $purchaseItem->goods_id ?? 0,
                'goods_name' => $purchaseItem->title,
                'sku_id' => $purchaseItem->sku_id ?? 0,
                'sku_image' => $purchaseItem->imgs[0] ?? '',
                'spec_name' => $purchaseItem->variant_title,
                'sku' => $purchaseItem->sku,
                'total_quantity' => 0,
                'quantity' => 0,
                'lock_quantity' => 0,
            ];
            $this->stock = Stock::query()->create($data);
        }
        $stockItem = $this->findOrCreateStockItem($location);
        $this->increaseStock(StockChangeLogs::SOURCE_PURCHASE, $purchaseItem->inbound_quantity, $stockItem);
        return $stockItem;
    }*/

    /** 入库单入库
     * @param $inboundOrder
     * @param $inboundItem
     * @param $inboundInfo
     * @return array
     * @throws Exception
     */
    public function inboundOrderInStorage($inboundOrder, $inboundItem, $inboundInfo)
    {
        $this->stock = Stock::query()->where('sku_id', $inboundItem->goods_sku_id)
            ->where('warehouse_id', $inboundOrder->warehouse_id)
            ->where('custom_id', $inboundOrder->custom_id)
            ->where('goods_type', $inboundItem->goods_type ?? 1) //商品类型 1-产品 2-包材
            ->first();
        if (empty($this->stock)) {
            $data = [
                'warehouse_id' => $inboundOrder->warehouse_id ?? 0,
                'custom_id' => $inboundOrder->custom_id ?? 0,
                'goods_id' => $inboundItem->goods_id ?? 0,
                'goods_name' => $inboundItem->goods_name,
                'sku_id' => $inboundItem->goods_sku_id ?? 0,
                'sku_image' => $inboundItem->sku_image,
                'spec_name' => $inboundItem->spec_name,
                'sku' => $inboundItem->goods_sku,
                'total_quantity' => 0,
                'quantity' => 0,
                'lock_quantity' => 0,
                'goods_type' => $inboundItem->goods_type ?? 1, //商品类型 1-产品 2-包材
                'packing_materials_type' => $inboundItem->packing_materials_type ?? 0, //包材类型 1-包装袋 2-纸箱 3-定制盒子 4-贴纸 5-卡片 99-其他
            ];
            $this->stock = Stock::query()->create($data);
        }
        $stockItems = [];
        foreach ($inboundInfo as $inboundData) {
            $this->operateSn = $inboundOrder->inbound_sn;
            $location = WarehouseGoodsAllocation::query()->where('warehouse_id', $inboundOrder->warehouse_id)
                ->where('code', $inboundData['location_code'])
                ->firstOrFail();
            $stockItem = $this->findOrCreateStockItem($location);
            $this->increaseStock(StockChangeLogs::SOURCE_INBOUND_ORDER, $inboundData['quantity'], $stockItem);
            $stockItems[] = $stockItem;
        }
        return $stockItems;
    }


    /** 入库
     * @throws Exception
     */
    public function increaseStock($source, $quantity, $stockItem)
    {
        $this->changeStock(StockChangeLogs::CHANGE_TYPE_INCREASE, $source, $quantity, $stockItem);
    }

    /** 出库
     * @throws Exception
     */
    public function deductionStock($source, $quantity, $stockItem)
    {
        $this->changeStock(StockChangeLogs::CHANGE_TYPE_DEDUCTION, $source, $quantity, $stockItem);
    }


    // @todo 弃用，等待删除
    /** 锁定库存 自动锁定货位
     * @param $quantity
     * @param $source
     * @return array
     * @throws Exception
     */
    /*public function autoLockStock($quantity, $source)
    {
        if ($quantity > $this->stock->quantity) throw new AccidentException('当前可用库存数量不足');

        $stockItems = StockItem::query()->where('stock_id', $this->stock->id)
            ->where('quantity', '>', 0)
            ->orderBy('quantity')->get();

        $result = [];
        foreach ($stockItems as $item) {
            $lockQuantity = min($quantity, $item->quantity);
            $lock = $this->lockStock($lockQuantity, $item, $source);
            $quantity -= $lockQuantity;
            $result[] = [
                'stock_item' => $item,
                'lock' => $lock,
                'quantity' => $lockQuantity
            ];
            if ($quantity <= 0) break;
        }
        return $result;
    }*/

    /** 通过操作单号解锁库位
     * @return false|void
     */
    public function unlockStockByOperateSn($source)
    {
        if (empty($this->operateSn)) return false;

        $stockLockLogs = StockLockLog::query()->where('operate_sn', $this->operateSn)
            ->where('status', 1)->get();

        $stockLockLogs->each(function ($item) use ($source) {
            $this->unlockStock($item, $source);
        });
    }


    /** 锁定库存
     * @param $quantity
     * @param $source
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model | boolean
     * @throws Exception
     */
    public function lockStock($quantity, $source)
    {
        if ($quantity <= 0) return false;

        $this->stock->quantity -= $quantity;
        $this->stock->lock_quantity += $quantity;
        $this->stock->save();

        return $this->addStockLock(StockLockLog::TYPE_LOCK, $source, $quantity);
    }


    /** 自动锁定库存对应库位
     * @param $lock
     * @return true
     */
    public function autoLockStockItem($lock)
    {
        $stockItems = StockItem::query()->where('stock_id', $lock->stock_id)
            ->where('quantity', '>', 0)
            ->orderBy('quantity')->get();

        $quantity = $lock->quantity;
        foreach ($stockItems as $stockItem) {
            $lockQuantity = min($quantity, $stockItem->quantity);
            $lock = $this->lockStockItem($lock, $stockItem, $lockQuantity);
            $quantity -= $lockQuantity;
            if ($quantity <= 0) break;
        }
        return true;
    }


    /** 锁定具体库位
     * @param $lock
     * @param $stockItem
     * @param $quantity
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function lockStockItem($lock, $stockItem, $quantity)
    {
        $stockItem->quantity -= $quantity;
        $stockItem->lock_quantity += $quantity;
        $stockItem->save();

        return $this->addStockLockItem($lock, $stockItem, $quantity);
    }

    /**
     * @param $lock
     * @return mixed
     */
    public function unlockStockLock($lock)
    {
        # 是否已解锁
        if ($lock->status === StockLockLog::TYPE_UNLOCK) return $lock;

        if (empty($this->stock)) $this->stock = $lock->stock;

        foreach ($lock->items as $lockItem) {
            $lockItem->stockItem->quantity += $lockItem->quantity;
            $lockItem->stockItem->lock_quantity -= $lockItem->quantity;
            $lockItem->stockItem->save();
        }

        $this->stock->quantity += $lock->quantity;
        $this->stock->lock_quantity -= $lock->quantity;
        $this->stock->save();

        $lock->status = 2;
        $lock->save();

        return $lock;
    }


    /**
     * @param $lock
     * @param $changeSource
     * @return void
     * @throws Exception
     */
    public function unlockStockLockAndConsume($lock, $changeSource)
    {
        $this->unlockStockLock($lock);
        $this->stock = Stock::query()->findOrFail($lock->stock_id);
        foreach ($lock->items as $lockItem) {
            $this->deductionStock($changeSource, $lockItem->quantity, $lockItem->stockItem);
        }
    }


    // @todo 弃用，等待删除
    /** 解锁某个锁定
     * @param $lock
     * @param $source
     */
    public function unlockStock($lock, $source)
    {
        $this->setOperateSn($lock->operate_sn);
        $stockItem = StockItem::query()->with('stock')->findOrFail($lock->stock_item_id);

        # 是否已解锁
        if ($lock->status === StockLockLog::TYPE_UNLOCK) return $stockItem;

        if (empty($this->stock)) $this->stock = $stockItem->stock;

        $stockItem->quantity += $lock->quantity;
        $stockItem->lock_quantity -= $lock->quantity;
        $stockItem->save();

        $this->stock->quantity += $lock->quantity;
        $this->stock->lock_quantity -= $lock->quantity;
        $this->stock->save();

        $lock->status = 2;
        $lock->save();

        $this->addStockLockLog(StockLockLog::TYPE_UNLOCK, $source, $lock->quantity, $stockItem);
        return $stockItem;
    }

    // @todo 弃用，等待删除
    /**  解除库存锁定并消耗
     * @param $lockId
     * @param $source
     * @param $changeSource
     * @return void
     * @throws Exception
     */
    public function unlockAndConsume($lockId, $source, $changeSource)
    {
        $lock = StockLockLog::query()->findOrFail($lockId);
        $stockItem = $this->unlockStock($lock, $source);
        $this->deductionStock($changeSource, $lock->quantity, $stockItem);
    }



    /** 库存变更
     * @param int $type
     * @param int $source
     * @param int $quantity
     * @param $stockItem
     * @return void
     * @throws Exception
     */
    protected function changeStock(int $type, int $source, int $quantity, $stockItem)
    {
        if (empty($this->stock)) throw new AccidentException('当前操作库存不存在');
        $location = WarehouseGoodsAllocation::query()->findOrFail($stockItem->location_id);
        if ($type === StockChangeLogs::CHANGE_TYPE_INCREASE) {

            $this->stock->total_quantity += $quantity;
            $this->stock->quantity = $this->stock->total_quantity - $this->stock->lock_quantity;

            $stockItem->total_quantity += $quantity;
            $stockItem->quantity = $stockItem->total_quantity - $stockItem->lock_quantity;

            $location->used_count += $quantity;
        } else {

            $this->stock->total_quantity -= $quantity;
            $this->stock->quantity = $this->stock->total_quantity - $this->stock->lock_quantity;

            $stockItem->total_quantity -= $quantity;
            $stockItem->quantity = $stockItem->total_quantity - $stockItem->lock_quantity;

            $location->used_count -= $quantity;
        }
        $stockItem->save();
        $this->stock->save();
        $location->save();

        $this->addStockChangeLog($type, $source, $quantity, $stockItem);
    }

    /** 添加库存变更日志
     * @param int $type
     * @param int $source
     * @param int $quantity
     * @param $stockItem
     * @return mixed
     */
    protected function addStockChangeLog(int $type, int $source, int $quantity, $stockItem): mixed
    {
        $data = [
            'custom_id' => $this->stock->custom_id,
            'warehouse_id' => $this->stock->warehouse_id,
            'stock_id' => $this->stock->id,
            'goods_name' => $this->stock->goods_name,
            'spec_name' => $this->stock->spec_name,
            'sku' => $this->stock->sku,
            'sku_image' => $this->stock->sku_image,
            'location_id' => $stockItem->location_id,
            'location_code' => $stockItem->location_code,
            'operate_sn' => $this->operateSn,
            'source' => $source,
            'change_type' => $type,
            'quantity' => $quantity,
        ];
        return StockChangeLogs::query()->create($data);
    }

    // @todo 弃用，等待删除
    protected function addStockLockLog($type, $source, $quantity, $stockItem)
    {
        $status = $type == StockLockLog::TYPE_LOCK ? 1 : 2;
        $data = [
            'stock_id' => $this->stock->id,
            'stock_item_id' => $stockItem->id,
            'location_id' => $stockItem->location_id,
            'location_code' => $stockItem->location_code,
            'type' => $type,
            'source' => $source,
            'quantity' => $quantity,
            'status' => $status,
            'operate_sn' => $this->operateSn,
        ];
        return StockLockLog::query()->create($data);
    }

    /** 新版 - 锁定库存日志
     * @param $type
     * @param $source
     * @param $quantity
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    protected function addStockLock($type, $source, $quantity)
    {
        $status = $type == StockLockLog::TYPE_LOCK ? 1 : 2;
        $data = [
            'stock_id' => $this->stock->id,
            'type' => $type,
            'source' => $source,
            'quantity' => $quantity,
            'status' => $status,
            'operate_id' => $this->operateId,
        ];
        return StockLock::query()->create($data);
    }

    /** 新版 - 锁定具体库位日志
     * @param $lock
     * @param $stockItem
     * @param $quantity
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    protected function addStockLockItem($lock, $stockItem, $quantity)
    {
        $data = [
            'lock_id' => $lock->id,
            'stock_item_id' => $stockItem->id,
            'location_id' => $stockItem->location_id,
            'location_code' => $stockItem->location_code,
            'quantity' => $quantity
        ];
        return StockLockItem::query()->create($data);
    }


    /** 查找或创建对应货位的库存信息
     * @param $location
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    protected function findOrCreateStockItem($location)
    {
        $stockItem = StockItem::query()->where('stock_id', $this->stock->id)->where('location_id', $location->id)->first();
        if (empty($stockItem)) {
            $data = [
                'stock_id' => $this->stock->id,
                'custom_id' => $this->stock->custom_id,
                'warehouse_id' => $this->stock->warehouse_id,
                'total_quantity' => 0,
                'quantity' => 0,
                'lock_quantity' => 0,
                'location_id' => $location->id,
                'location_code' => $location->code,
            ];
            $stockItem = StockItem::query()->create($data);
        }
        return $stockItem;
    }

}
