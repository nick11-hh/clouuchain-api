<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;

class ExpressCompany extends Model
{
    use Basis,
        CustomHasTranslations,
        HasValidateUnique;

    public $translatable = ['name'];

    protected $table = 'dsp_express_company';

    public const CODE_YI_DA = 'YDH';

    public const CODE_LT_EXP = 'LTEXP';

    public const CODE_DE_RUN = 'DE-RUN';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    public function crawlerConfig()
    {
        return $this->hasOne(CrawlerConfig::class, 'express_company_id', 'id');
    }

    public function getCrawlerCodeAttribute()
    {
        return self::crawlerCodeList($this->num);
    }

    public static function crawlerCodeList($num)
    {
        $codes = [
            'yuantong' => 'yto',
            'zhongtong' => 'zto',
            'ems' => 'ems',
            'debangwuliu' => 'deppon'
        ];

        return !empty($codes[$num]) ? $codes[$num] : $num;
    }


}
