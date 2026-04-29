<?php

namespace App\Models;

use App\Services\Admin\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

class ExchangeRateModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_exchange_rate';

    public static array $currencyUnitTypeSymbols = [
        #亚洲
        'CNY' => '¥', // 人民币
        'HKD' => 'HK$',
        'TWD' => 'NT$',
        'MOP' => 'MOP$',
        'KRW' => '₩', // 韩元
        'KPW' => '₩', // 朝鲜元
        'JPY' => '¥', // 日元
        'MNT' => '₮', // 蒙古图格里克
        'INR' => '₹', // 印度卢比
        'SGD' => 'S$', // 新加坡元
        'MYR' => 'RM', // 马来西亚林吉特
        'THB' => '฿', // 泰铢
        'MMK' => 'K', // 缅甸元
        'LAK' => '₭', // 老挝基普
        'IDR' => 'Rp', // 印尼卢比
        'PHP' => '₱', // 菲律宾比索
        'VND' => '₫', // 越南盾
        'LKR' => 'Rs', // 斯里兰卡卢比
        'PKR' => 'Rs', // 巴基斯坦卢比
        'BDT' => '৳', // 孟加拉塔卡
        'KZT' => '₸', // 哈萨克斯坦坚戈
        'ILS' => '₪', // 以色列新谢克尔
        'GEL' => '₾', // 格鲁吉亚拉里
        'UZS' => 'soʻm', // 乌兹别克斯坦苏姆
        'TJS' => 'SM', // 塔吉克斯坦索莫尼
        'TMT' => 'T', // 土库曼斯坦马纳特
        'KGS' => 'с', // 吉尔吉斯斯坦索姆
        'AED' => 'د.إ', // 阿联酋迪拉姆
        'QAR' => 'ر.ق', // 卡塔尔里亚尔
        'KWD' => 'د.ك', // 科威特第纳尔
        'BHD' => 'ب.د', // 巴林第纳尔
        'SAR' => 'ر.س', // 沙特里亚尔
        'JOD' => 'د.ا', // 约旦第纳尔
        'IRR' => '﷼', // 伊朗里亚尔
        'IQD' => 'ع.د', // 伊拉克第纳尔
        #北美
        'USD' => '$', // 美元
        'CAD' => 'C$', // 加拿大元
        'MXN' => 'MX$', // 墨西哥比索
        'CRC' => '₡', // 哥斯达黎加科朗
        'BSD' => 'B$', // 巴哈马元
        #欧洲
        'EUR' => '€', // 欧元
        'GBP' => '£', // 英镑
        'CHF' => 'CHF', // 瑞士法郎
        'NOK' => 'kr', // 挪威克朗
        'SEK' => 'kr', // 瑞典克朗
        'DKK' => 'kr', // 丹麦克朗
        'ISK' => 'kr', // 冰岛克朗
        'RUB' => '₽', // 俄罗斯卢布
        'UAH' => '₴', // 乌克兰格里夫纳
        'HUF' => 'Ft', // 匈牙利福林
        #南美洲
        'BRL' => 'R$', // 巴西雷亚尔
        'ARS' => 'AR$', // 阿根廷比索
        'COP' => 'COL$', // 哥伦比亚比索
        'CLP' => 'CLP$', // 智利比索
        'PEN' => 'S/', // 秘鲁新索尔
        'UYU' => '$U', // 乌拉圭比索
        'PYG' => '₲', // 巴拉圭瓜拉尼
        #大洋洲
        'AUD' => 'A$', // 澳大利亚元
        'NZD' => 'NZ$', // 新西兰元
        'FJD' => 'FJ$', // 斐济元
        'PGK' => 'K', // 巴布亚新几内亚基那
        'SBD' => 'SI$', // 所罗门群岛元
        #非洲
        'ZAR' => 'R', // 南非兰特
        'EGP' => 'E£', // 埃及镑
        'NGN' => '₦', // 尼日利亚奈拉
        'KES' => 'KSh', // 肯尼亚先令
        'TZS' => 'TSh', // 坦桑尼亚先令
        'UGX' => 'USh', // 乌干达先令
        'GHS' => 'GH₵', // 加纳塞地
        'MAD' => 'د.م.', // 摩洛哥迪拉姆
        'DZD' => 'د.ج', // 阿尔及利亚第纳尔
    ];

    public static function init($data)
    {
        return [
            'name'                 => $data['name'],
            'currency_code'        => $data['currency_code'],
            'symbol'               => $data['symbol'],
            'exchange_rate'        => $data['exchange_rate'],
            'custom_exchange_rate' => $data['custom_exchange_rate'],
        ];
    }

    public function getSupportCurrency()
    {
        return [
            [
                'label' => '人民币',
                'value' => 'CNY',
                'symbol' => '¥',
            ],
            [
                'label' => '美元',
                'value' => 'USD',
                'symbol' => '$',
            ],
            [
                'label' => '欧元',
                'value' => 'EUR',
                'symbol' => '€',
            ],
            [
                'label' => '泰铢',
                'value' => 'THB',
                'symbol' => '฿',
            ],
            [
                'label' => '英镑',
                'value' => 'GBP',
                'symbol' => '£',
            ],
            [
                'label' => '澳元',
                'value' => 'AUD',
                'symbol' => '$',
            ],
            [
                'label' => '卢布',
                'value' => 'RUB',
                'symbol' => '₽',
            ],
            [
                'label' => '雷亚尔',
                'value' => 'BRL',
                'symbol' => 'R$',
            ],
        ];
    }

    /**
     * @param  string  $to
     * @param  string  $from
     * @return mixed
     */
    /*public static function rate(string $to, string $from = 'USD')
    {
        $key = sprintf('ExchangeRate-%s-%s', $from, $to);

        return Cache::get($key, function () use ($key, $from, $to) {
            $i = 0;
            do {
                try {
                    $rate = ExchangeRateService::queryExchangeRate($to, $from);
                } catch (\Throwable $throwable) {
                    $rate = false;
                }
            } while ($rate === false && $i++ < 4);

            if ($rate === false) {
                return 0;
            }

            Cache::put($key, $rate['rate'], Carbon::parse('6 hours'));

            return $rate['rate'];
        });
    }*/
}
