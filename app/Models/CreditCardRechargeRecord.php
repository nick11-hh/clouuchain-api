<?php

namespace App\Models;

use App\Models\Traits\Basis;

/**
 * App\Models\CreditCardRechargeRecord
 *
 * @property int $id
 * @property int $user_id 用户id
 * @property int $custom_id 客户id
 * @property int $type 类型:1=40Seas
 * @property string $transaction_id 交易编号
 * @property int $amount 金额
 * @property string $currency 货币类型
 * @property int $status 状态:1=待支付,2=支付成功
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property int|null $check_admin_id 核账管理员id
 * @property mixed|null $check_images 核账图片
 * @property string|null $check_desc 核账描述
 * @property int $check_status 核账状态：0=未核账，1=已核账
 * @property string|null $check_time 核验时间
 * @property-read \App\Models\Admin|null $admin
 * @property-read \App\Models\Custom|null $custom
 * @property-read mixed $i_name
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord pageSize(int $size = 10)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereCheckAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereCheckDesc($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereCheckImages($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereCheckStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereCheckTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereCustomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord whereWhen($column, $value, $operator = '=')
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditCardRechargeRecord withoutTrashed()
 * @mixin \Eloquent
 */
class CreditCardRechargeRecord extends Model
{
    use Basis;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;
    //  40Seas平台
    const TYPE_40SEAS = 1;

    const PLATFORM = [
        self::TYPE_40SEAS => '40Seas',
    ];

    // 状态待支付
    const STATUS_PENDING = 1;

    // 状态支付成功
    const STATUS_SUCCESS = 2;

    // 状态支付失败
    const STATUS_FAIL = 3;

    //已核账
    const CHECK_STATUS_SUCCESS = 1;
    //未核账
    const CHECK_STATUS_FAIL = 0;


    protected $table = 'credit_card_recharge_record';


    public static function getPlatformName(int $type): string
    {
        return self::PLATFORM[$type] ?? '';
    }
    
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

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'check_admin_id', 'id');
    }


    // 禁用软删除功能
    public function getDeletedAtColumn()
    {
        return null;
    }

    public function getQualifiedDeletedAtColumn()
    {
        return null;
    }
}
