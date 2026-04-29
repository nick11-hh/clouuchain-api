<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 售后工单日志模型
 * Class AfterSalesWorkOrderLogs
 * @package App\Models
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/6 16:24
 */
class AfterSalesWorkOrderLogs extends Model
{
    use HasFactory;

    protected $table = 'dsp_after_sales_work_order_logs';

    protected $guarded = [];

    protected $casts = [];

    protected $hidden = [];

    /**
     * 关联工单
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/6 16:25
     */
    public function workOrder()
    {
        return $this->belongsTo(AfterSalesWorkOrder::class, 'work_order_id', 'id');
    }

    /**
     * 关联客户
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/6 15:26
     */
    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    /**
     * 处理人
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/6 15:26
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'handle_id', 'id');
    }
}
