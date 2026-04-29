<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DspCurrencyListTableSender extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('dsp_currency_list')->truncate();

        \DB::table('dsp_currency_list')->insert([
            [
                'name' => '人民币',
                'code' => 'CNY',
                'enabled' => 1,
            ],
            [
                'name' => '美元',
                'code' => 'USD',
                'enabled' => 1,
            ],
            [
                'name' => '日元',
                'code' => 'JPY',
                'enabled' => 0,
            ],
            [
                'name' => '欧元',
                'code' => 'EUR',
                'enabled' => 0,
            ],
            [
                'name' => '英镑',
                'code' => 'GBP',
                'enabled' => 0,
            ],
            [
                'name' => '韩元',
                'code' => 'KER',
                'enabled' => 0,
            ],
            [
                'name' => '港币',
                'code' => 'HKD',
                'enabled' => 0,
            ],
            [
                'name' => '澳大利亚元',
                'code' => 'AUD',
                'enabled' => 0,
            ],
            [
                'name' => '加拿大元',
                'code' => 'CAD',
                'enabled' => 0,
            ],
            [
                'name' => '阿尔及利亚第纳尔',
                'code' => 'DZD',
                'enabled' => 0,
            ],
            [
                'name' => '阿根廷比索',
                'code' => 'ARS',
                'enabled' => 0,
            ],
            [
                'name' => '爱尔兰镑',
                'code' => 'IEP',
                'enabled' => 0,
            ],
            [
                'name' => '埃及镑',
                'code' => 'EGP',
                'enabled' => 0,
            ],
            [
                'name' => '阿联酋迪拉姆',
                'code' => 'AED',
                'enabled' => 0,
            ],
            [
                'name' => '阿曼里亚尔',
                'code' => 'OMR',
                'enabled' => 0,
            ],
            [
                'name' => '奥地利先令',
                'code' => 'ATS',
                'enabled' => 0,
            ],
            [
                'name' => '澳门元',
                'code' => 'MOP',
                'enabled' => 0,
            ],
            [
                'name' => '百慕大元',
                'code' => 'BMD',
                'enabled' => 0,
            ],
            [
                'name' => '巴基斯坦卢比',
                'code' => 'PKR',
                'enabled' => 0,
            ],
            [
                'name' => '巴拉圭瓜拉尼',
                'code' => 'PYG',
                'enabled' => 0,
            ],
            [
                'name' => '巴林第纳尔',
                'code' => 'BHD',
                'enabled' => 0,
            ],
            [
                'name' => '巴拿马巴尔博亚',
                'code' => 'PAB',
                'enabled' => 0,
            ],
            [
                'name' => '保加利亚列弗',
                'code' => 'BGN',
                'enabled' => 0,
            ],
            [
                'name' => '巴西雷亚尔',
                'code' => 'BRL',
                'enabled' => 0,
            ],
            [
                'name' => '比利时法郎',
                'code' => 'BEF',
                'enabled' => 0,
            ],
            [
                'name' => '冰岛克朗',
                'code' => 'ISK',
                'enabled' => 0,
            ],
            [
                'name' => '博茨瓦纳普拉',
                'code' => 'BWP',
                'enabled' => 0,
            ],
            [
                'name' => '波兰兹罗提',
                'code' => 'PLN',
                'enabled' => 0,
            ],
            [
                'name' => '玻利维亚诺',
                'code' => 'BOB',
                'enabled' => 0,
            ],
            [
                'name' => '丹麦克朗',
                'code' => 'DKK',
                'enabled' => 0,
            ],
            [
                'name' => '德国马克',
                'code' => 'DEM',
                'enabled' => 0,
            ],
            [
                'name' => '法国法郎',
                'code' => 'FRF',
                'enabled' => 0,
            ],
            [
                'name' => '菲律宾比索',
                'code' => 'PHP',
                'enabled' => 0,
            ],
            [
                'name' => '芬兰马克',
                'code' => 'FIM',
                'enabled' => 0,
            ],
            [
                'name' => '哥伦比亚比索',
                'code' => 'COP',
                'enabled' => 0,
            ],
            [
                'name' => '古巴比索',
                'code' => 'CUP',
                'enabled' => 0,
            ],
            [
                'name' => '哈萨克坚戈',
                'code' => 'KZT',
                'enabled' => 0,
            ],
            [
                'name' => '荷兰盾',
                'code' => 'NLG',
                'enabled' => 0,
            ],
            [
                'name' => '加纳塞地',
                'code' => 'GHC',
                'enabled' => 0,
            ],
            [
                'name' => '捷克克朗',
                'code' => 'CZK',
                'enabled' => 0,
            ],
            [
                'name' => '津巴布韦元',
                'code' => 'ZWD',
                'enabled' => 0,
            ],
            [
                'name' => '卡塔尔里亚尔',
                'code' => 'QAR',
                'enabled' => 0,
            ],
            [
                'name' => '克罗地亚库纳',
                'code' => 'HRK',
                'enabled' => 0,
            ],
            [
                'name' => '肯尼亚先令',
                'code' => 'KES',
                'enabled' => 0,
            ],
            [
                'name' => '科威特第纳尔',
                'code' => 'KWD',
                'enabled' => 0,
            ],
            [
                'name' => '老挝基普',
                'code' => 'LAK',
                'enabled' => 0,
            ],
            [
                'name' => '拉脱维亚拉图',
                'code' => 'LVL',
                'enabled' => 0,
            ],
            [
                'name' => '黎巴嫩镑',
                'code' => 'LBP',
                'enabled' => 0,
            ],
            [
                'name' => '林吉特',
                'code' => 'MYR',
                'enabled' => 0,
            ],
            [
                'name' => '立陶宛立特',
                'code' => 'LTL',
                'enabled' => 0,
            ],
            [
                'name' => '卢布',
                'code' => 'RUB',
                'enabled' => 0,
            ],
            [
                'name' => '罗马尼亚新列伊',
                'code' => 'RON',
                'enabled' => 0,
            ],
            [
                'name' => '毛里求斯卢比',
                'code' => 'MUR',
                'enabled' => 0,
            ],
            [
                'name' => '蒙古图格里克',
                'code' => 'MNT',
                'enabled' => 0,
            ],
            [
                'name' => '孟加拉塔卡',
                'code' => 'BDT',
                'enabled' => 0,
            ],
            [
                'name' => '缅甸缅元',
                'code' => 'BUK',
                'enabled' => 0,
            ],
            [
                'name' => '秘鲁新索尔',
                'code' => 'PEN',
                'enabled' => 0,
            ],
            [
                'name' => '摩洛哥迪拉姆',
                'code' => 'MAD',
                'enabled' => 0,
            ],
            [
                'name' => '墨西哥比索',
                'code' => 'MXN',
                'enabled' => 0,
            ],
            [
                'name' => '南非兰特',
                'code' => 'ZAR',
                'enabled' => 0,
            ],
            [
                'name' => '挪威克朗',
                'code' => 'NOK',
                'enabled' => 0,
            ],
            [
                'name' => '葡萄牙埃斯库多',
                'code' => 'PTE',
                'enabled' => 0,
            ],
            [
                'name' => '瑞典克朗',
                'code' => 'SEK',
                'enabled' => 0,
            ],
            [
                'name' => '瑞士法郎',
                'code' => 'CHF',
                'enabled' => 0,
            ],
            [
                'name' => '沙特里亚尔',
                'code' => 'SAR',
                'enabled' => 0,
            ],
            [
                'name' => '斯里兰卡卢比',
                'code' => 'LKR',
                'enabled' => 0,
            ],
            [
                'name' => '索马里先令',
                'code' => 'SOS',
                'enabled' => 0,
            ],
            [
                'name' => '泰国铢',
                'code' => 'THB',
                'enabled' => 0,
            ],
            [
                'name' => '坦桑尼亚先令',
                'code' => 'TZS',
                'enabled' => 0,
            ],
            [
                'name' => '土耳其新里拉',
                'code' => 'TRY',
                'enabled' => 0,
            ],
            [
                'name' => '突尼斯第纳尔',
                'code' => 'TND',
                'enabled' => 0,
            ],
            [
                'name' => '危地马拉格查尔',
                'code' => 'GTQ',
                'enabled' => 0,
            ],
            [
                'name' => '委内瑞拉玻利瓦尔',
                'code' => 'VEB',
                'enabled' => 0,
            ],
            [
                'name' => '乌拉圭新比索',
                'code' => 'UYU',
                'enabled' => 0,
            ],
            [
                'name' => '西班牙比塞塔',
                'code' => 'ESP',
                'enabled' => 0,
            ],
            [
                'name' => '希腊德拉克马',
                'code' => 'GRD',
                'enabled' => 0,
            ],
            [
                'name' => '新加坡元',
                'code' => 'SGD',
                'enabled' => 0,
            ],
            [
                'name' => '新台币',
                'code' => 'TWD',
                'enabled' => 0,
            ],
            [
                'name' => '新西兰元',
                'code' => 'NZD',
                'enabled' => 0,
            ],
            [
                'name' => '匈牙利福林',
                'code' => 'HUF',
                'enabled' => 0,
            ],
            [
                'name' => '牙买加元',
                'code' => 'JMD',
                'enabled' => 0,
            ],
            [
                'name' => '义大利里拉',
                'code' => 'ITL',
                'enabled' => 0,
            ],
            [
                'name' => '印度卢比',
                'code' => 'INR',
                'enabled' => 0,
            ],
            [
                'name' => '印尼盾',
                'code' => 'IDR',
                'enabled' => 0,
            ],
            [
                'name' => '以色列谢克尔',
                'code' => 'ILS',
                'enabled' => 0,
            ],
            [
                'name' => '约旦第纳尔',
                'code' => 'JOD',
                'enabled' => 0,
            ],
            [
                'name' => '越南盾',
                'code' => 'VND',
                'enabled' => 0,
            ],
            [
                'name' => '智利比索',
                'code' => 'CLP',
                'enabled' => 0,
            ],
        ]);
    }
}
