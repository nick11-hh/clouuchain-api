<?php

namespace App\Console\Commands\DataFixer;

use App\Jobs\CreateShopifyWebhooks;
use App\Lib\Platform;
use App\Models\Custom;
use App\Models\CustomInvoiceAddressModel;
use App\Models\ShopModel;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class ShopifyWebhookFix extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:shopify-webhook-fix {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修复店铺webhook';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $shops = ShopModel::query()->where('platform', Platform::SHOPIFY)
            ->where('status', 1)->where('enable', 1)->get();
        foreach ($shops as $shop) {
            dispatch(new CreateShopifyWebhooks($shop));
        }
    }
}
