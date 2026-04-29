<?php

namespace App\Models;

use App\Lib\Code;
use App\Models\Traits\Basis;
use Exception;
use App\Exceptions\AccidentException;

class Currency extends Model
{
    use Basis;

    public const DEFAULT_CURRENCY = 'USD';

    protected $table = 'dsp_currencies';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * @return false|string
     */
    public static function code()
    {
        $data = self::query()->first();

        if (! $data) {
            return self::DEFAULT_CURRENCY;
        }

        return $data->code;
    }

    /**
     * @return mixed
     */
    public static function current($currency)
    {
        $data = self::query()->where('code', $currency)->first();

        if (! $data) {
            return new self([
                'name' => '人民币',
                'code' => 'CNY',
            ]);
        }

        return [
            'name' => $data->name,
            'code' => $data->code,
        ];
    }

    /**
     * @param string $code
     * @return bool
     * @throws Exception
     */
    public static function setDefaultCurrency(string $code)
    {
        $exist = CurrencyList::query()->where('code', $code)->first();

        if (! $exist) {
            throw new AccidentException('当前货币代码不存在', Code::OPERATE_FAIL);
        }

        return self::query()->updateOrCreate(
            [
                'company_id' => auth()->user()->company_id,
            ],
            [
                'code' => $code,
                'name' => $exist->name,
            ]
        ) instanceof self;
    }
}
