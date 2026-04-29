<?php

namespace App\Models;

use App\Models\Traits\Basis;

class WorkOrder extends Model
{
    use Basis;

    protected $table = 'dsp_work_order';

    protected $guarded = [];

    protected $hidden = [];

    protected $appends = [];

    protected $fillable = [];

    protected $casts = [
        'copy_to' => 'array',
        'file' => 'array',
    ];

    // 优先级 0-普通 1-紧急 2-非常紧急
    public const PRIORITY_COMMON = 0;
    public const PRIORITY_EMERGENT = 1;
    public const PRIORITY_VERY_URGENT = 2;

    // 状态 0-草稿 1-处理中 2-已完成 3-已关闭
    public const STATUS_DRAFT = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_FINISH = 2;
    public const STATUS_CLOSE = 3;

    /**
     * 工单类型
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function type()
    {
        return $this->belongsTo(WorkOrderType::class, 'type_id', 'id');
    }

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

    /**
     * 关联客户
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 工单总沟通信息
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function discuss()
    {
        return $this->hasMany(WorkOrderDiscuss::class, 'main_id', 'id');
    }

    /**
     * 子工单沟通信息
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childDiscuss()
    {
        return $this->hasMany(WorkOrderDiscuss::class, 'child_id', 'id');
    }

    /**
     * 工单总沟通信息
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function logs()
    {
        return $this->hasMany(WorkOrderLogs::class, 'main_id', 'id');
    }

    /**
     * 子工单信息
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function workOrder()
    {
        return $this->hasMany(WorkOrder::class, 'parent_id', 'id');
    }

    /**
     * 子工单沟通信息
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childLogs()
    {
        return $this->hasMany(WorkOrderLogs::class, 'child_id', 'id');
    }

    public static function init($data)
    {
        $order_sn = 'GD'.hexdec(uniqid());
        $estimatedCompletion = $data['estimated_completion'] ?? null;
        if (!empty($estimatedCompletion) && is_int($estimatedCompletion)) {
            $estimatedCompletion = date('Y-m-d H:i:s', $estimatedCompletion/1000);
        }

        if (isset($data['company_id'])) {
            $companyId = $data['company_id'];
        } else {
            $companyId = self::getCompanyId();
        }

        if (isset($data['creator_id'])) {
            $creatorId = $data['creator_id'];
        } else {
            $creatorId = auth()->id();
        }

        return [
            'order_sn' => $order_sn,
            'company_id' => $companyId,
            'priority' => $data['priority'] ?? self::PRIORITY_COMMON,
            'title' => $data['title'],
            'type_id' => $data['type_id'],
            'creator_id' => $creatorId,
            'content' => $data['content'],
            'customer' => $data['customer'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'assigned_by' => $data['assigned_by'],
            'copy_to' => $data['copy_to'] ?? [],
            'file' => $data['file'] ?? [],
            'associated_order' => $data['associated_order'] ?? null,
            'estimated_completion' => $estimatedCompletion,
            'parent_id' => $data['parent_id'] ?? null,
            'status' => self::STATUS_DRAFT,
        ];
    }

    public static function initChild($order, $data)
    {
        $order_sn = 'GD'.hexdec(uniqid());
        $estimated_completion = $data['estimated_completion'] ?? null;
        if (!empty($estimated_completion) && is_int($estimated_completion)) {
            $estimated_completion = date('Y-m-d H:i:s', $estimated_completion/1000);
        }
        return [
            'order_sn' => $order_sn,
            'company_id' => $order->company_id,
            'priority' => $order->priority,
            'title' => $data['title'],
            'type_id' => $order->type_id,
            'creator_id' => auth()->id(),
            'content' => $data['content'],
            'customer' => $order->customer ?? null,
            'user_id' => $order->user_id ?? null,
            'assigned_by' => $data['assigned_by'],
            'file' => $data['file'] ?? [],
            'associated_order' => $order->order_sn,
            'estimated_completion' => $estimated_completion,
            'parent_id' => $order->id,
            'status' => self::STATUS_PROCESSING,
        ];
    }

    public static function statusList()
    {
        return [
            self::STATUS_DRAFT => __('草稿'),
            self::STATUS_PROCESSING => __('处理中'),
            self::STATUS_FINISH => __('已完成'),
            self::STATUS_CLOSE => __('已关闭'),
        ];
    }
}
