<?php

namespace App\Models;

use App\Lib\Code;
use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Exceptions\AccidentException;

class UserBalance extends Model
{
    use Basis, HasValidateUnique;

    public const TYPE_INCOME = 1; // 类型为收入
    public const TYPE_OUTLAY = 2; // 类型为支出

    //来源类型
    public const COMMISSION_OUTLAY = 1; // 佣金提现
    public const BALANCE_RECHARGE = 2; // 余额充值

    protected $table = 'dsp_user_balance';

    protected $guarded = ['balance']; // 余额字段不可批量赋值

    protected $hidden = [];

    protected $casts = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @param  int  $userId
     * @param  int  $amount
     * @param  bool  $increment 是否余额增加
     * @throws Throwable
     */
    public static function updateUserBalance(int $userId, int $amount, bool $increment = true)
    {
        if ($increment) {
            static::query()->where('user_id', $userId)
                ->lockForUpdate()
                ->update([
                    'balance' => DB::raw("balance + {$amount}"),
                    'history_income' => DB::raw("history_income + {$amount}"),
                ]);
        } else {
            self::balanceEnoughOrFail($userId, $amount);

            static::query()->where('user_id', $userId)
                ->lockForUpdate()
                ->update([
                    'balance' => DB::raw("balance - {$amount}"),
                    'history_outlay' => DB::raw("history_outlay + {$amount}"),
                ]);
        }
    }

    /**
     * @param  int  $userId
     * @param  int  $amount
     * @throws Throwable
     */
    public static function balanceEnoughOrFail(int $userId, int $amount)
    {
        $enough = static::query()->sharedLock()
            ->where('user_id', $userId)
            ->selectRaw("(balance - {$amount}) as enough")
            ->get()->first()->enough;

        throw_if(
            $enough < 0,
            new AccidentException('用户当前余额不足，差额为：' . $enough / 100, Code::OPERATE_FAIL)
        );
    }
}
