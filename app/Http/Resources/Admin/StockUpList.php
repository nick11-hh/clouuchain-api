<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class StockUpList extends JsonResource
{
    public function toArray($request)
    {
        $latestNode = null;
        $latestApproveName = null;

        if ($this->latestProcessLog && $this->latestProcessLog->isNotEmpty()) {
            $latestLog = $this->latestProcessLog->first();
            $latestNode = $latestLog->node ?? null;
            $latestApproveName = $latestLog->supervisor ?? null;
        }
        return [
            'id' => $this->id,
            'stock_up_number' => $this->stock_up_number,
            'stock_up_type' => $this->stock_up_type,
            'custom_id' => $this->custom_id,
            'exchange_rate' => $this->exchange_rate,
            'stock_up_amount' => $this->stock_up_amount,
            'credit_line' => $this->credit_line,
            'balance' => $this->balance,
            'frozen_limit' => $this->frozen_limit,
            'purchase_amount' => $this->purchase_amount ?? '',
            'submitter_id' => $this->submitter_id,
            'process' => $this->process,
            'stock_up_describe' => $this->stock_up_describe,
            'purchase_opinion' => $this->purchase_opinion ?? '',
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
            'deleted_at' => $this->deleted_at ?? '',
            'custom' => CustomInfo::make($this->custom) ?? [],
            'stock_up_product_item' => StockUpProductItemList::collection($this->stockUpProductItem) ?? [],
            'stock_up_purchase_item' => StockUpPurchaseItemList::collection($this->stockUpPurchaseItem) ?? [],
            'stockProcessLog' => stockProcessLogList::collection($this->stockProcessLog) ?? 0,
            'admin' => AdminList::make($this->admin) ?? [],
            'latest_node' => $latestNode,
            'latest_approve_name' => $latestApproveName,
        ];
    }
}
