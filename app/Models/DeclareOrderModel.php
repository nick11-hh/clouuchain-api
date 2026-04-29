<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeclareOrderModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_declare_order';

    protected $casts = [
        'data' => 'array',
    ];

    public const UNIT_MTR = 'MTR';
    public const UNIT_PCE = 'PCE';
    public const UNIT_SET = 'SET';

    public const CURRENCY_CNY = 'RMB';
    public const CURRENCY_USD = 'USD';
    public const CURRENCY_EUR = 'EUR';
    public const CURRENCY_HKD = 'HKD';
    public const CURRENCY_VND = 'VND';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function boxes()
    {
        return $this->hasMany(DeclareOrderBoxesModel::class, 'declare_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(DeclareOrderItemsModel::class, 'declare_id', 'id');
    }


    public static function unitList()
    {
        return [
            self::UNIT_MTR => '米',
            self::UNIT_PCE => '件',
            self::UNIT_SET => '套'
        ];
    }

    public static function currencyList()
    {
        return [
            self::CURRENCY_CNY => '人民币',
            self::CURRENCY_USD => '美元',
            self::CURRENCY_EUR => '欧元',
            self::CURRENCY_HKD => '港币',
            self::CURRENCY_VND => '越南盾'
        ];
    }

    /**
     * @return array[]
     */
    public static function ZTOEastAsiaData()
    {
        return [
            'th' => [
                'shipType' => [
                    1 => '海运',
                    5 => '快运',
                    6 => '普运',
                ],
                'expressType' => [
                    'EXPRESS' => '快运',
                    'GLOBAL' => '国际物流',
                    'IN_PRO' => '省内件',
                    'OUT_PRO' => '省外件',
                ],
                'deliveryType' => [
                    'PS' => '派送',
                    'ZT' => '自提',
                ],
                'payType' => [
                    'MAIL' => '寄付',
                    'CC' => '到付',
                    'MONTHLY' => '月结',
                ],
                'packageTyp' => [
                    12 => '文件',
                    13 => '物品',
                    14 => '混合',
                ],
            ],
            'vi' => [
                'shipType' => [
                    5 => '快运',
                    6 => '普运',
                ],
                'expressType' => [
                    'GLOBAL' => '国际物流',
                    'IN_PRO' => '省内件',
                    'OUT_PRO' => '省外件',
                ],
                'deliveryType' => [
                    'PS' => '派送',
                    'ZT' => '自提',
                ],
                'payType' => [
                    'MAIL' => '寄付',
                    'CC' => '到付',
                    'MONTHLY' => '月结',
                ],
                'packageTyp' => [
                    12 => '文件',
                    13 => '物品',
                    14 => '混合',
                ],
            ],
            'kh' => [
                'shipType' => [
                    2 => '陆运',
                    3 => '航空普快',
                    4 => '航空特快',
                    5 => '快运',
                ],
                'expressType' => [
                    'EXPRESS' => '快运',
                    'AIR_GEN' => '航空普快',
                    'AIR_EXP' => '航空特快',
                    'LAND' => '陆运',
                ],
                'deliveryType' => [
                    'LAND' => '陆运',
                    'AIR' => '空运',
                    'SPECIAL_EXP' => '特快',
                    'EXPRESS' => '快运',
                ],
                'payType' => [
                    ' CASH' => '现金',
                    'CC' => '到付',
                    'MONTHLY' => '月结',
                ],
                'packageTyp' => [
                    '12' => '文件',
                    '13' => '物品',
                    '15' => '贵重物品',
                    '16' => '香烟',
                ],
            ],
            'la' => [
                'shipType' => [
                    0 => '空运',
                    2 => '陆运',
                ],
                'expressType' => [
                    'GLOBAL' => '普通件',
                    'ORDINARY' => '国际物流',
                    'GLOBAL_EXP' => '国际快递',
                    'LA_INTE_EXP' => '老挝国内快递',
                    'LA_INTE' => '老挝国内',
                ],
                'deliveryType' => [
                    'DOOR' => '送货上门',
                    'ZT' => '自提',
                    'AGENT' => '代理派送',
                    'TRANSFER' => '转单派送',
                ],
                'payType' => [
                    'CASH' => '现金',
                    'CC' => '到付',
                    'MONTHLY' => '月结',
                    'AGENT' => '代收货款',
                ],
                'packageTyp' => [
                    '12' => '文件',
                    '13' => '物品',
                    '15' => '贵重物品',
                    '17' => '报价物品',
                    '18' => '易碎',
                ],
            ],
            'mm' => [
                'shipType' => [
                    0 => '空运',
                    2 => '陆运',
                ],
                'expressType' => [
                    'MM_INTE' => '缅甸国内件',
                    'GLOBAL' => '国际物流',
                ],
                'deliveryType' => [
                    'PS' => '派送',
                    'ZT' => '自提',
                ],
                'payType' => [
                    'CASH' => '现金',
                    'CC' => '到付',
                    'MONTHLY' => '月结',
                    'AGENT' => '代收货款',
                ],
                'packageTyp' => [
                    '12' => '文件',
                    '13' => '物品',
                ],
            ],
        ];
    }
}
