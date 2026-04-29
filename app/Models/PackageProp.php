<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PackageProp extends Model
{
    use Basis, HasValidateUnique, CustomHasTranslations;

    public $translatable = ['cn_name', 'en_name', 'name'];

    protected $table = 'dsp_package_prop';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [
        'prop_name',
    ];

    public function getPropNameAttribute()
    {
        return $this->name;
    }

    /**
     * @return BelongsToMany
     */
    public function expressLines(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_express_line_props',
            'express_line_id',
            'prop_id'
        );
    }

    /**
     * 是否全都存在
     * @param array $ids
     * @return bool
     */
    public static function isValid(array $ids): bool
    {
        return self::whereIn('id', $ids)->count() === count(array_unique($ids));
    }
}
