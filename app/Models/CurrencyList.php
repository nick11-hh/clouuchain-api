<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CurrencyList extends Model
{
    use Basis;

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $table = 'dsp_currency_list';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function enabledList()
    {
        return self::query()->where('enabled', 1)->get();
    }

    /**
     * @param  string  $code
     * @return string
     */
    public static function getSymbol(string $code)
    {
        $data = Cache::get('currency-symbol', function () {
            $data = \json_decode(Storage::disk('local')->get('currency-symbol.json'), true);
            Cache::forever('currency-symbol', $data);

            return $data;
        });

        return $data[strtoupper($code)] ?? '';
    }

    protected static function boot()
    {
        static::bootTraits();
    }
}
