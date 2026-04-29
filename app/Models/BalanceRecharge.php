<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BalanceRecharge extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_balance_recharges';

    protected $casts = [
        'check_images' => 'array',
    ];

    protected $guarded = [];

    CONST STATUS_DEFAULT = 0;
    CONST STATUS_SUCCESS = 1;
    CONST STATUS_FAIL = 2;

    CONST TYPE_PAYPAL = 1;

    public const CHECK_STATUS_0 = 0;//未核账
    public const CHECK_STATUS_1 = 1;//已核账

    /**
     * 客户关联
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/4 20:39
     */
    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public static function statusList()
    {
        return [
            self::STATUS_DEFAULT => '未支付',
            self::STATUS_SUCCESS => '支付成功',
            self::STATUS_FAIL => '支付失败',
        ];
    }

    public static function typeList()
    {
        return [
            self::TYPE_PAYPAL => 'PayPal',
        ];
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }

    public function getTypeNameAttribute()
    {
        return self::typeList()[$this->type] ?? '';
    }

    public function getCheckStatusNameAttribute()
    {
        return $this->check_status != self::CHECK_STATUS_1 ? __('未核验') : __('已核验');
    }

    public function getCheckAdminNameAttribute()
    {
        return $this->check_status == self::CHECK_STATUS_1 ? Admin::where('id', $this->check_admin_id)->value('name') : '';
    }

}
