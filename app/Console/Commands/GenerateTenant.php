<?php

namespace App\Console\Commands;

use App\Services\Base\TenantService;
use Illuminate\Console\Command;

class GenerateTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:tenant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成房客，并创建房客数据库';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $name = $this->ask('请输入房客的名称?');
        $clientDomain = $this->ask('请输入客户端域名?');
        $adminDomain = $this->ask('请输入管理端域名?');
        $username = $this->ask('请输入房客登录的username？', 'admin');
        $password = $this->ask('请输入房客登录的密码？', '12345678');
        $data = [
            'name' => $name,
            'client_domain' => $clientDomain,
            'admin_domain' => $adminDomain,
            'username' => $username,
            'password' => $password,
        ];
        $tenantService = new TenantService();
        $tenant = $tenantService->create($data);
        $this->info('生成房客成功，房客数据库名称为：' . $tenant->database);
    }
}
