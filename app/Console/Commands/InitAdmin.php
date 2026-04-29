<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AdminGroupModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;
use Spatie\Multitenancy\Models\Tenant;

class InitAdmin extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:init_admin {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '初始化用户信息';

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $name = $this->ask('请输入房客的名称?');
        $username = $this->ask('请输入房客登录的username？', 'admin');
        $password = $this->ask('请输入房客登录的密码？', '12345678');

        $tenant = Tenant::current();

        $tenant->makeCurrent();
        // 创建超级管理员账号
        $admin = new Admin();
        $admin->name = $name;
        $admin->username = $username;
        $admin->password = bcrypt($password);
        $admin->group_id = 1;
        $admin->super_admin = 1;
        $admin->save();

        $this->initAdmin($tenant);

        $this->info('初始化成功');
        return 1;
    }

    public function initAdmin($tenant)
    {
        // Artisan::call('dsp:update-menu-route --tenant=' . $tenant->id);
        $data = [
            'name' => '超级管理员',
            'description' => '超级管理员用户组',
        ];
        AdminGroupModel::query()->create($data);
    }
}
