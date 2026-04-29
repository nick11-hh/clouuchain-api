<?php

namespace App\Models;

use App\Models\Traits\Basis;

class StockProcessLog extends Model
{
    use Basis;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'dsp_stock_process_log';
    const NODE_START = 1;
    const NODE_WECOM_APPROVE = 2;
    const NODE_PURCHASE_START = 3;
    const NODE_FINANCE_APPROVE = 4;

    const NODE_LABELS = [
        self::NODE_START => '流程发起',
        self::NODE_WECOM_APPROVE => '企微审批',
        self::NODE_PURCHASE_START => '发起采购',
        self::NODE_FINANCE_APPROVE => '财务核账',
    ];

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

}
