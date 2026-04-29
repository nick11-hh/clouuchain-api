<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统配置操作日志模型
 * Class SystemConfigOperateLogs
 * @package App\Models
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2025/1/11 15:45
 */
class SystemConfigOperateLogs extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table = 'dsp_system_config_operate_logs';

    protected $guarded = [];

    protected $casts = [
    ];

    /**
     * 关联管理员模型
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/11 15:49
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    /**
     * 保存操作日志
     * @param $params
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/13 14:17
     */
    public static function saveLog($params)
    {
        $data = self::init($params);
        return self::query()->create($data);
    }

    public static function init($params)
    {
        return [
            'admin_id' => auth('admin')->id() ?: 0,
            'content' => $params['content'] ?? null,
        ];
    }
}
