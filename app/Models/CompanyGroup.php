<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use App\Models\Traits\LikeScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CompanyGroup extends Model
{
    use Basis,
        HasValidateUnique,
        LikeScope;

    public const MAXIMUM_CACHE = 'Company:MaximumData';

    protected $table = 'dsp_company_groups';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 是否全都存在
     * @param array $ids
     * @return bool
     */
    public static function isValid(array $ids): bool
    {
        return self::whereIn('id', $ids)->count() === count(array_unique($ids));
    }

    /**
     * 公司
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function companies()
    {
        return $this->hasMany(Company::class, 'company_group_id', 'id');
    }

    /**
     * @return Collection
     */
    public function getGroupMaximum()
    {
        if (!Cache::has(self::MAXIMUM_CACHE)) {
            self::updateMaximumCache();
        }

        return Cache::get(self::MAXIMUM_CACHE)->first(function ($item) {
            return $item->id === $this->id;
        });
    }

    /**
     * @return bool
     */
    public static function updateMaximumCache()
    {
        $data = self::query()
            ->select(['id', 'max_employee', 'max_warehouse', 'max_express_line', 'max_agent'])
            ->get()
            ->toBase();

        return Cache::forever(self::MAXIMUM_CACHE, $data);
    }

    public static function boot()
    {
        static::bootTraits();

        static::saved(function (self $model) {
            if ($model->wasChanged()) {
                static::updateMaximumCache();
            }
        });
    }

    /**
     * 更新公司组下公司数量数
     *
     * @return bool
     */
    public static function updateCompanyCount()
    {
        static::all()->flatMap(function ($group) {
            $group->company_count = Company::query()
                ->where('company_group_id', $group->id)
                ->count();

            return $group->save();
        });

        info('公司组的公司数量更新完成');

        return true;
    }
}
