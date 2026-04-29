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
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class CreateShopSetting extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:shop-setting {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成店铺配置数据';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        dump(Tenant::current()->name);
        $shopList = ShopModel::query()->get();
        $shopList->each(function ($shop) {
            ShopSetting::query()->updateOrCreate(['shop_id' => $shop->id], [
                'send_customer_email' => $params['send_customer_email'] ?? 1
            ]);
        });
    }
}
