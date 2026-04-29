<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use App\Observers\CompanyObserver;
use Illuminate\Support\Facades\Cache;

class Company extends Model
{
    use Basis,
        HasValidateUnique;

    public const CONTRACT_CACHE = 'Company:ContractEndData';
    public const UUID_CACHE = 'Company:UuidData';

    public const TYPE_NORMAL = 1;
    public const TYPE_SHARE = 2;
    public const TYPE_JOIN = 3;

    protected $table = 'dsp_companies';

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'contract_end_at' => 'datetime',
        'contract_created_at' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(CompanyGroup::class, 'company_group_id', 'id')
            ->withoutGlobalScopes();
    }

    public static function boot()
    {
        static::bootTraits();

        static::saved(function (Company $model) {
            if ($model->wasChanged('contract_end_at') || !static::isContractEndDataExist()) {
                static::cacheContractEndData();
            }

            static::cacheUuidData();
        });

        static::observe(CompanyObserver::class);
    }

    /**
     * @return bool
     */
    public static function cacheContractEndData()
    {
        $data = static::query()->select(['id', 'contract_end_at'])->get();

        return Cache::forever(self::CONTRACT_CACHE, $data);
    }

    /**
     * @return bool
     */
    protected static function isContractEndDataExist()
    {
        return Cache::has(self::CONTRACT_CACHE);
    }

    /**
     * @return mixed
     */
    public static function getUuidData()
    {
        return Cache::get(self::UUID_CACHE, function () {
            static::cacheUuidData();

            return Cache::get(self::UUID_CACHE);
        });
    }

    /**
     * @return bool
     */
    protected static function cacheUuidData()
    {
        $uuids = static::query()->select(['uuid'])->get()
            ->pluck('uuid')->flatten()
            ->values()->all();

        return Cache::forever(self::UUID_CACHE, $uuids);
    }
}
