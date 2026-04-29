<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Country;
use App\Models\CountryArea;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class UpdateCountryArea extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:update-country-areas {code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新国家或地区的区域信息';

    protected array $hk = [
        '香港岛' => ['中西区', '湾仔区', '东区', '南区'],
        '九龙' => ['九龙城区', '油尖旺区', '观塘区', '黄大仙区', '深水埗区'],
        '新界' => ['北区', '大埔区', '沙田区', '西贡区', '元朗区', '屯门区', '荃湾区', '葵青区', '离岛区'],
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $code = $this->argument('code');
        // $companyIds = $this->argument('companyId')
        //     ? Arr::wrap($this->argument('companyId'))
        //     : Admin::query()->whereNotNull('uuid')->get()->modelKeys();

        $data = isset($this->$code) ? $this->$code : null;

        if (! $data) {
            return false;
        }

        // foreach ($companyIds as $companyId) {
            $country = Country::query()
                ->whereDoesntHave('areas')
                ->where('code', 'hk')
                ->first();

            // if (! $country) {
            //     continue;
            // }

            foreach ($data as $key => $value) {
                /** @var CountryArea $area */
                $area = CountryArea::query()->create(
                    [
                        'country_id' => $country->id,
                        'name' => $key,
                        'parent_id' => null,
                        // 'company_id' => $companyId,
                    ]
                );

                foreach ($value as $item) {
                    CountryArea::query()->create(
                        [
                            'country_id' => $country->id,
                            'name' => $item,
                            'parent_id' => $area->getKey(),
                            // 'company_id' => $companyId,
                        ]
                    );
                }
            }
        // }
    }
}
