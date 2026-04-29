<?php

namespace App\Models;

use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 费用类型
 */
class ChargeTypesModel extends Model
{
    use SoftDeletes;
    use HasFactory;
    use CustomHasTranslations;

    protected $table = 'dsp_charge_types';

    public const TYPE_ORDER = 1; //订单类型项
    public const TYPE_ORDER_REFUND = 2; //订单退款类型
    
    //用于翻译
    public $translatable = ['name_translate'];

    protected $casts = [
        'name_translate' => 'array',
    ];


    public function order()
    {
        return $this->hasOne(Order::class, 'charge_type_id', 'id');
    }

    public static function init($params): array
    {
        return [
            'type'   => $params['type'] ?? self::TYPE_ORDER,
            'name'   => $params['name'] ?? '',
            'name_translate' => [
                'zh_CN' => $params['name'] ?? '',
                'en_US' => $params['name_en'] ?? $params['name'],
                'ru_RU' => $params['name_ru'] ?? $params['name'],
                'ar_SA' => $params['name_ar'] ?? $params['name'],
                'pt_PT' => $params['name_pt'] ?? $params['name'],
                'vi_VN' => $params['name_vi'] ?? $params['name'],
            ],
            'remark' => $params['remark'] ?? '',
            'status' => $params['status'] ?? 1,
        ];
    }
}
