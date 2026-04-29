<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CompanyLimitChecker;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class WarehouseAddress
 * @package App\Models
 * @property int mode
 * @property string address
 * @property string province
 * @property string city
 * @property string district
 * @property array location_size
 * @property int big_rule
 * @property int custom_location
 * @property int location_weight
 * @property int size_rule
 * @property int weight_rule
 * @property int off_shelf_status
 */
class WarehouseAddress extends Model
{
    use Basis,
        CompanyLimitChecker,
        CustomHasTranslations;

    public array $search = ['{user_id}', '{{user_id}}', '{USER_ID}', '{{USER_ID}}'];

    //用于翻译
    public $translatable = ['warehouse_name', 'address', 'tips', 'receiver_name', 'province', 'city', 'district'];

    protected $table = 'dsp_warehouse_address';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'location_size' => 'array',
    ];

    const ENABLE = 1;
    const UNENABLE = 0;
    public const UNLOCKED = 0;
    public const LOCKED = 1;

    /**
     * 支持的国家
     * @return BelongsToMany
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(
            Country::class,
            'dsp_warehouse_country',
            'warehouse_id',
            'country_id'
        );
    }

    /**
     * 支持的线路
     * @return BelongsToMany
     */
    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_warehouse_express_line',
            'warehouse_id',
            'express_line_id'
        );
    }

    public function locationArea()
    {
        return $this->hasMany(WarehouseGoodsAllocationArea::class, 'warehouse_id', 'id');
    }

    /**
     * @param $value
     * @return array|string|string[]
     */
    public function getAddressAttribute($value)
    {
        $search = $this->search;

        if (starts_with(request()->path(), 'api/admin/warehouse-address')) {
            return $value;
        }

        // if (starts_with(request()->path(), 'api/client')) {
        //     $user = auth('api')->user();
        //
        //     $id = $user?->useUid()?->id;
        //
        //     if ($id) {
        //         $chId = sprintf('%s', number2Chs($id));
        //
        //         return str_replace($search, [$id, $id, $chId, $chId], $value);
        //     }
        // }

        return str_replace($search, '', $value);
    }

    /**
     * @param $value
     * @return array|string|string[]
     */
    public function getReceiverNameAttribute($value)
    {
        $search = $this->search;

        if (starts_with(request()->path(), 'api/admin/warehouse-address')) {
            return $value;
        }

        // if (starts_with(request()->path(), 'api/client')) {
        //     $user = auth('api')->user();
        //
        //     $id = $user?->useUid()?->id;
        //
        //     if ($id) {
        //         $chId = sprintf('%s', number2Chs($id));
        //
        //         return str_replace($search, [$id, $id, $chId, $chId], $value);
        //     }
        // }

        return str_replace($search, '', $value);
    }

    /**
     * @return array|mixed
     */
    public function getOriginalAddressAttribute()
    {
        return $this->getAttributeValue('address');
    }

    /**
     * @return array|mixed
     */
    public function getOriginalReceiverNameAttribute()
    {
        return $this->getAttributeValue('receiver_name');
    }

    /**
     * @return array|string|string[]
     */
    public function getPureReceiverNameAttribute()
    {
        $value = $this->getTranslationWithoutGetMutator('receiver_name', $this->getLocale(), $this->useFallbackLocale());

        return str_replace($this->search, '', $value);
    }

    /**
     * @return array|string|string[]
     */
    public function getPureAddressAttribute()
    {
        $value = $this->getTranslationWithoutGetMutator('address', $this->getLocale(), $this->useFallbackLocale());

        return str_replace($this->search, '', $value);
    }

    /**
     * 是否开启自动货位功能
     * @return bool
     */
    public function autoLocationEnabled()
    {
        return $this->auto_location === 1;
    }
}
