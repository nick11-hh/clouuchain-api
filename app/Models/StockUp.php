<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockUp extends Model
{
    use Basis, SoftDeletes;

    protected $table = 'dsp_stock_up';

    //国内仓全款备货
    const STOCK_UP_TYPE_HOME = 1;

    //海外仓全款备货
    const STOCK_UP_TYPE_OVERSEAS = 2;

    // 当前流程状态:0=暂存
    const PROCESS_DRAFT = 0;

    // 当前流程状态:1=审批中
    const PROCESS_PENDING = 1;

    // 当前流程状态:2=已通过（发起采购）
    const PROCESS_APPROVED = 2;

    // 当前流程状态:3=已驳回
    const PROCESS_REJECTED = 3;

    // 当前流程状态:4=已取消
    const PROCESS_CANCELLED = 4;

    // 当前流程状态:5=财务核账
    const PROCESS_ACCOUNTING_RECONCILIATION = 5;

    // 当前流程状态:6=完成
    const PROCESS_COMPLETE = 6;

    // 当前流程状态:7=转审
    const PROCESS_REVIEW_TRANSFER = 7;


    const PROCESS = [
        [
            'label' => '暂存',
            'value' => self::PROCESS_DRAFT,
        ],
        [
            'label' => '进行中',
            'value' => [
                self::PROCESS_PENDING,
                self::PROCESS_APPROVED,
                self::PROCESS_ACCOUNTING_RECONCILIATION,
            ],
        ],
        [
            'label' => '已驳回',
            'value' => self::PROCESS_REJECTED,
        ],
        [
            'label' => '已取消',
            'value' => self::PROCESS_CANCELLED,
        ],
        [
            'label' => '已完成',
            'value' => self::PROCESS_COMPLETE,
        ],
    ];


    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'submitter_id', 'id');
    }

    public function stockUpProductItem()
    {
        return $this->hasMany(StockUpProductItem::class, 'stock_up_id', 'id')->whereNull('deleted_at');
    }

    public function stockUpPurchaseItem()
    {
        return $this->hasMany(StockUpPurchaseItem::class, 'stock_up_id', 'id')->whereNull('deleted_at');
    }

    public function stockProcessLog()
    {
        return $this->hasMany(StockProcessLog::class, 'stock_up_id', 'id')->where('process', '!=', StockProcessLog::PROCESS_PENDING)->whereNull('deleted_at');
    }

    public function latestProcessLog()
    {
        return $this->hasMany(StockProcessLog::class, 'stock_up_id', 'id')->whereIn('process', [StockProcessLog::PROCESS_PENDING, StockProcessLog::PROCESS_REJECTED])->whereNull('deleted_at');
    }
}
