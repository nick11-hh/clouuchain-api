<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AdminGroupModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ClientInit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:client_init {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '创建客户初始化';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->ask('请输入房客的名称?');
        $username = $this->ask('请输入房客登录的username？', 'admin');
        $password = $this->ask('请输入房客登录的密码？', '12345678');

        $id = $this->argument('id');

        $tenant = \App\Models\Landlord\Tenant::query()->where('id', $id)->first();
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

        $this->info('初始化完成！');
    }

    public function initAdmin($tenant)
    {
        Artisan::call('dsp:update-menu-route --tenant=' . $tenant->id);
        $data = [
            'name' => '超级管理员',
            'description' => '超级管理员用户组',
        ];
        AdminGroupModel::query()->create($data);
    }
}
