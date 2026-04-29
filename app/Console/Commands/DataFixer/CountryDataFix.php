<?php

namespace App\Console\Commands\DataFixer;

use App\Models\Landlord\Tenant;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\Package;
use App\Models\ShopModel;
use App\Models\ShopSetting;
use App\Services\Admin\PackageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class CountryDataFix extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:country-data {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修复国家数据';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        dump(Tenant::current()->name);
//        $data = [
//            [
//                'country' => [
//                    'id' => 256,
//                    'continent_id' => 2,
//                    'name' => 'Ascension Island',
//                    'full_name' => 'Ascension Island',
//                    'capital' => 'Georgetown',
//                    'code' => 'ac',
//                    'code_alpha3' => 'ac',
//                    'code_numeric' => 247,
//                    'emoji' => '',
//                    'has_division' => 0,
//                    'currency_code' => 'GBP',
//                    'currency_name' => 'Pound sterling',
//                    'tld' => '.ac',
//                    'callingcode' => '44',
//                ],
//                'locals' => [
//                    [
//                        'country_id'    => 256,
//                        'name'          => 'Ascension Island',
//                        'alias'         => null,
//                        'abbr'          => null,
//                        'full_name'     => 'Ascension Island',
//                        'currency_name' => 'Pound sterling',
//                        'locale'        => 'en',
//                    ],
//                    [
//                        'country_id'    => 256,
//                        'name'          => '阿森松岛',
//                        'alias'         => null,
//                        'abbr'          => null,
//                        'full_name'     => '阿森松岛',
//                        'currency_name' => '英镑',
//                        'locale'        => 'zh-cn',
//                    ]
//                ]
//            ],
//            [
//                'country' => [
//                    'id' => 257,
//                    'continent_id' => 2,
//                    'name' => 'Anguilla',
//                    'full_name' => 'Anguilla',
//                    'capital' => 'The Valley',
//                    'code' => 'ai',
//                    'code_alpha3' => 'ai',
//                    'code_numeric' => 1264,
//                    'emoji' => '',
//                    'has_division' => 0,
//                    'currency_code' => 'GBP',
//                    'currency_name' => 'Pound sterling',
//                    'tld' => '.ac',
//                    'callingcode' => '1264',
//                ],
//                'locals' => [
//                    [
//                        'country_id'    => 257,
//                        'name'          => 'Anguilla',
//                        'alias'         => null,
//                        'abbr'          => null,
//                        'full_name'     => 'Anguilla',
//                        'currency_name' => 'Pound sterling',
//                        'locale'        => 'en',
//                    ],
//                    [
//                        'country_id'    => 257,
//                        'name'          => '‌安圭拉',
//                        'alias'         => null,
//                        'abbr'          => null,
//                        'full_name'     => '‌安圭拉',
//                        'currency_name' => '英镑',
//                        'locale'        => 'zh-cn',
//                    ],
//                ]
//            ],
//            [
//                'country' => [
//                    'id' => 258,
//                    'continent_id' => 2,
//                    'name' => 'Tristan da Cunha',
//                    'full_name' => 'Tristan da Cunha',
//                    'capital' => 'Tristan da Cunha',
//                    'code' => 'xb',
//                    'code_alpha3' => 'xb',
//                    'code_numeric' => 0,
//                    'emoji' => '',
//                    'has_division' => 0,
//                    'currency_code' => 'GBP',
//                    'currency_name' => 'Pound sterling',
//                    'tld' => '.ac',
//                    'callingcode' => '',
//                ],
//                'locals' => [
//                    [
//                        'country_id'    => 258,
//                        'name'          => 'Tristan da Cunha',
//                        'alias'         => null,
//                        'abbr'          => null,
//                        'full_name'     => 'Tristan da Cunha',
//                        'currency_name' => 'Pound sterling',
//                        'locale'        => 'en',
//                    ],
//                    [
//                        'country_id'    => 258,
//                        'name'          => '特里斯坦-达库尼亚群岛',
//                        'alias'         => null,
//                        'abbr'          => null,
//                        'full_name'     => '特里斯坦-达库尼亚群岛',
//                        'currency_name' => '英镑',
//                        'locale'        => 'zh-cn',
//                    ],
//                ]
//            ]
//        ];

        $data = [
            [
                'country' => [],
                'locals' => [
                    [
                        'country_id'    => 248,
                        'name'          => 'Montenegro',
                        'alias'         => null,
                        'abbr'          => null,
                        'full_name'     => 'Socialist republic of Montenegro',
                        'currency_name' => 'Pound sterling',
                        'locale'        => 'en',
                    ],
                    [
                        'country_id'    => 248,
                        'name'          => '黑山',
                        'alias'         => null,
                        'abbr'          => null,
                        'full_name'     => '黑山社会主义共和国',
                        'currency_name' => '英镑',
                        'locale'        => 'zh-cn',
                    ]
                ]
            ],
        ];

        foreach ($data as $value) {
            if (!empty($value['country'])) {
                DB::table('world_countries')->insert($value['country']);
            }
            if (!empty($value['locals'])) {
                DB::table('world_countries_locale')->insert($value['locals']);
            }
        }
    }
}
