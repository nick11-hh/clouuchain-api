<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpressLineGroupsModel extends Model
{
    use Basis,
        CustomHasTranslations;

    protected $table = 'dsp_express_line_groups';

    //用于翻译
    public $translatable = ['name'];

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 包含的线路
     * @return HasMany
     */
    public function expressLines(): HasMany
    {
        return $this->hasMany(ExpressLineModel::class, 'group_id', 'id');
    }
}
