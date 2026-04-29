<?php

/**
 * @Author: h9471
 * @Created: 2019/10/24 11:40
 */

namespace App\Services\Admin;

use App\Models\Localization;
use Illuminate\Support\Facades\Cache;

class LocalizationService extends BaseService
{
    public function __construct(Localization $localization)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $localization;
        $this->query = $localization->newQuery();
        $this->setFilterRules();
    }

    public function getInfo()
    {
        return $this->model::first();
    }

    public function updateInfo(array $data): bool
    {
        validator($data, $this->rules())->validate();

        $res = $this->model::updateOrCreate(
            [
                'company_id' => auth()->user()->company_id,
            ],
            [
                'weight_name' => $data['weight_name'],
                'weight_symbol' => $data['weight_symbol'],
                'currency_name' => $data['currency_name'],
                'currency_symbol' => $data['currency_symbol'],
                'length_name' => $data['length_name'] ?? '',
                'length_symbol' => $data['length_symbol'] ?? '',
            ]
        );

        if ($res) {
            Cache::forget(mb_strtoupper('Localization' . auth()->user()->company_id));
        }

        return true;
    }

    public function getLocalizationList()
    {
        return [
            'currency' => $this->getCurrencyList(),
            'weight' => $this->getWeightList(),
            'length' => $this->getLengthList(),
        ];
    }

    /**
     * 获取本地化配置
     * @return mixed
     */
    public static function getCurrentLocalization()
    {
        return Cache::get(mb_strtoupper('Localization' . (auth()->user() ? auth()->user()->company_id : 0)), function () {
            $data = Localization::first();

            $local = [
                'weight_unit' => $data->weight_symbol ?? 'KG',
                'currency_unit' => $data->currency_symbol ?? '￥',
                'length_unit' => $data->length_symbol ?? 'CM',
            ];

            Cache::forever(mb_strtoupper('Localization' . (auth()->user() ? auth()->user()->company_id : 0)), $local);

            return $local;
        });
    }

    public static function getCurrencyCode()
    {
        $unit = self::getCurrentLocalization()['currency_unit'];

        $map = [
            'NZ$' => 'NZD',
        ];

        return $map[$unit] ?? $unit;
    }

    /**
     * 重量单位列表
     * @return array
     */
    protected function getWeightList()
    {
        return [
            [
                'id' => 1,
                'symbol' => 'KG',
                'name' => '千克 (KG)',
            ], [
                'id' => 2,
                'symbol' => 'G',
                'name' => '克 (G)',
            ], [
                'id' => 3,
                'symbol' => 'OZ',
                'name' => '盎司 (OZ)',
            ], [
                'id' => 4,
                'symbol' => 'LB',
                'name' => '磅 (LB)',
            ], [
                'id' => 5,
                'symbol' => 'T',
                'name' => '吨 (TON)',
            ],
        ];
    }

    /**
     * 获取货币列表
     */
    protected function getCurrencyList()
    {
        return [
            [
                'id' => 1,
                'symbol' => '¥',
                'name' => '人民币 (CNY)',
            ],
            [
                'id' => 2,
                'symbol' => '$',
                'name' => '美元 (USD) / 加元 (CAD)',
            ],
            [
                'id' => 3,
                'symbol' => '€',
                'name' => '欧元 (EUR)',
            ],
            [
                'id' => 4,
                'symbol' => 'JP¥',
                'name' => '日元 (JPY)',
            ],
            [
                'id' => 5,
                'symbol' => 'NZ$',
                'name' => '新西兰元 (NZD)',
            ],
            [
                'id' => 6,
                'symbol' => 'A$',
                'name' => '澳大利亚元 (AUD)',
            ],
            [
                'id' => 7,
                'symbol' => 'RM',
                'name' => '马来西亚元 (MYR)',
            ],
            [
                'id' => 8,
                'symbol' => 'HK$',
                'name' => '港币 (HKD)',
            ],
            [
                'id' => 9,
                'symbol' => '£',
                'name' => '英镑 (GBP)',
            ],
            [
                'id' => 10,
                'symbol' => '฿',
                'name' => '泰铢 (THB)',
            ],
            [
                'id' => 11,
                'symbol' => 'NT$',
                'name' => '新台币 (TWD)',
            ],
            [
                'id' => 12,
                'symbol' => '〒',
                'name' => '哈萨克斯坦坚戈 (KZT)',
            ],
            [
                'id' => 13,
                'symbol' => '₽',
                'name' => '卢布 (RUB)',
            ],
        ];
    }

    protected function getLengthList()
    {
        return [
            [
                'id' => 1,
                'symbol' => 'CM',
                'name' => '厘米 (CM)',
            ],
            [
                'id' => 2,
                'symbol' => 'IN',
                'name' => '英寸 (IN)',
            ],
            [
                'id' => 3,
                'symbol' => 'MM',
                'name' => '毫米 (MM)',
            ],[
                'id' => 4,
                'symbol' => 'M',
                'name' => '米 (M)',
            ],
        ];
    }

    private function rules()
    {
        return [
            'weight_name' => 'sometimes|string',
            'weight_symbol' => 'required_with:weight_name|string',
            'currency_name' => 'sometimes|string',
            'currency_symbol' => 'required_with:currency_name|string',
            'length_name' => 'sometimes|string',
            'length_symbol' => 'required_with:length_name|string',
        ];
    }
}
