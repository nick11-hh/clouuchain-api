<?php

namespace App\Services\Base;

use App\Models\Admin;
use App\Models\AdminGroupModel;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class TenantService
{
    public function create($params)
    {
        validator($params, [
            'name' => 'required|string',
            'client_domain' => 'required|string',
            'admin_domain' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string'
        ])->validate();
        // 在房东数据库中创建租户
        $tenantExist = Tenant::query()->where('client_domain', $params['client_domain'])
            ->orWhere('admin_domain', $params['admin_domain'])
            ->first();
        if (!empty($tenantExist)) throw new AccidentException('客户端或管理端域名已存在');
        $tenantData = [
            'name' => $params['name'],
            'client_domain' => $params['client_domain'],
            'admin_domain' => $params['admin_domain'],
            'uuid' => getUuid(),
            'database' => config('database.connections.landlord.database')
        ];
        $tenant = \App\Models\Landlord\Tenant::query()->create($tenantData);
        $tenant->database = $tenant->database . '_' .$tenant->id;
        $tenant->save();

        dump('新增租户数据成功');

        // 创建租户数据库
        $query = "CREATE DATABASE {$tenant->database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        DB::connection('landlord')->statement($query);

        // 切换当前租户数据库信息
        Config::set('database.connections.tenant.database', $tenant->database);
        // 设置telescope的数据连接为当前租户
        Config::set('telescope.storage.database.connection', 'tenant');
        dump('正在执行迁移');
        // 数据库迁移
        Artisan::call('migrate --database=tenant --force');
        dump('迁移完成');
        sleep(5);
        dump('开始初始化数据');
        $tenant->makeCurrent();
        // 创建超级管理员账号
        $admin = new Admin();
        $admin->name = $params['name'];
        $admin->username = $params['username'];
        $admin->password = bcrypt($params['password']);
        $admin->group_id = 1;
        $admin->super_admin = 1;
        $admin->save();

        $this->initAdmin($tenant);
        return $tenant;
    }

    public function initAdmin($tenant)
    {
        Artisan::call('dsp:update-menu-route --tenant=' . $tenant->id);
        $data = [
            'name' => '超级管理员',
            'description' => '超级管理员用户组',
        ];
        AdminGroupModel::query()->create($data);
        dump('写入国家数据');
        Artisan::call('tenants:artisan  world:init --tenant=' . $tenant->id);
        dump('写入物流数据');
        Artisan::call('tenants:artisan dsp:update-express-companies --tenant=' . $tenant->id);
    }
}
