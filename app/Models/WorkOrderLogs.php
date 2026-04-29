<?php

namespace App\Models;

use App\Models\Traits\Basis;

class WorkOrderLogs extends Model
{
    use Basis;

    protected $table = 'dsp_work_order_logs';

    protected $guarded = [];

    protected $hidden = [];

    protected $appends = [
        'type_name'
    ];

    protected $fillable = [];

    // 日志类型 0-新建工单 1-指派 2-新建子工单 3-子工单完成 4-工单完成 5-工单激活 6-工单关闭
    public const STATUS_CREATED = 0;
    public const STATUS_ASSIGN = 1;
    public const STATUS_CREATED_SON = 2;
    public const STATUS_FINISH_SON = 3;
    public const STATUS_FINISH = 4;
    public const STATUS_ACTIVATION = 5;
    public const STATUS_CLOSE = 6;

    public function getTypeNameAttribute()
    {
        $name = '';
        switch ($this->type) {
            case self::STATUS_CREATED:
                $name = '新建工单';
                break;
            case self::STATUS_ASSIGN:
                $name = '指派';
                break;
            case self::STATUS_CREATED_SON:
                $name = '新建子工单';
                break;
            case self::STATUS_FINISH_SON:
                $name = '子工单完成';
                break;
            case self::STATUS_FINISH:
                $name = '工单完成';
                break;
            case self::STATUS_ACTIVATION:
                $name = '工单激活';
                break;
            case self::STATUS_CLOSE:
                $name = '工单关闭';
                break;

            default:
                # code...
                break;
        }
        return $name;
    }
}
