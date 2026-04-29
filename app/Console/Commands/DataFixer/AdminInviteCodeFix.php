<?php

namespace App\Console\Commands\DataFixer;

use App\Jobs\CreateShopifyWebhooks;
use App\Lib\Platform;
use App\Models\Admin;
use App\Models\Custom;
use App\Models\CustomInvoiceAddressModel;
use App\Models\Order;
use App\Models\ShopModel;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class AdminInviteCodeFix extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:admin-invite-code-fix {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修复报价数据';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Admin::query()->get()->each(function (Admin $admin) {
            $admin->invite_code = generateEmployeeInviteCode($admin->id);
            $admin->save();
        });
    }
}
