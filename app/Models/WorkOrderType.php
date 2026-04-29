<?php

namespace App\Models;

use App\Models\Traits\Basis;

class WorkOrderType extends Model
{
    use Basis;

    protected $table = 'dsp_work_order_type';

    protected $guarded = [];

    protected $hidden = [];

    protected $appends = [
        'service_type_name'
    ];

    protected $fillable = [];

    protected $casts = [
        'copy_to' => 'array',
    ];

    // 服务类型 0-自定义服务 1-拆包清点, 2-打包加固, 3-异常包裹, 4-异常订单, 5-高货值未购保险, 6-仓储超期提醒
    public const SERVICE_CUSTOM = 0;
    public const SERVICE_UNPACKING = 1;
    public const SERVICE_PACKING_REINFORCE = 2;
    public const SERVICE_ABNORMAL_PACKAGE = 3;
    public const SERVICE_ABNORMAL_ORDER = 4;
    public const SERVICE_HIGH_VALUE = 5;
    public const SERVICE_WAREHOUSE_OVERDUE = 6;

    public static array $serviceTypes = [
        self::SERVICE_CUSTOM => '自定义服务',
        self::SERVICE_UNPACKING => '拆包清点',
        self::SERVICE_PACKING_REINFORCE => '打包加固',
        self::SERVICE_ABNORMAL_PACKAGE => '异常包裹',
        self::SERVICE_ABNORMAL_ORDER => '异常订单',
        self::SERVICE_HIGH_VALUE => '高货值未购保险',
        self::SERVICE_WAREHOUSE_OVERDUE => '仓储超期提醒'
    ];

    /**
     * 创建人
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'creator_id', 'id');
    }

    /**
     * 指派人
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assign()
    {
        return $this->belongsTo(Admin::class, 'assigned_by', 'id');
    }

    public function getServiceTypeNameAttribute()
    {
        return static::$serviceTypes[$this->service_type] ?? '';
    }
}
