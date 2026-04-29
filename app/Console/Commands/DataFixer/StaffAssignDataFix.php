<?php

namespace App\Console\Commands\DataFixer;

use App\Models\AssignDataPermission;
use App\Models\Custom;
use App\Models\Landlord\Tenant;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class StaffAssignDataFix extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:staff-assign-data {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '数据权限范围上线后修复员工分配的客户数据';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info(Tenant::current()->name);
        $customers = Custom::query()->where('staff_id', '!=', 0)->get();
        foreach ($customers as $customer) {
            if ($customer->staff_id <= 0) continue;
            AssignDataPermission::query()->firstOrCreate([
                'admin_id' => $customer->staff_id,
                'permission_type' => AssignDataPermission::CUSTOMER_PERMISSION,
                'permission_id' => $customer->id
            ]);
        }
    }
}
