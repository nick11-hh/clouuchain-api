<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 区域通知
 */
class AreaNotification extends Model
{
    use Basis,
        CustomHasTranslations;

    //用于翻译
    public $translatable = ['content'];

    protected $table = 'dsp_area_notifications';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * 关联通知的区域
     *
     * @return HasMany
     */
    public function areas(): HasMany
    {
        return $this->hasMany(CountryArea::class, 'notification_id', 'id');
    }
}
