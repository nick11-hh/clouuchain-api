##   安装项目

安装项目相关依赖
```shell
composer install
```
在.env文件中配置房东和租客数据库
```shell
DB_CONNECTION=tenant
LANDLORD_HOST=127.0.0.1
LANDLORD_PORT=3306
LANDLORD_DATABASE=dropshipping
LANDLORD_USERNAME=root
LANDLORD_PASSWORD=root

TENANT_HOST=127.0.0.1
TENANT_PORT=3306
TENANT_USERNAME=root
TENANT_PASSWORD=root
```
迁移房东数据库表
```shell
php artisan migrate --path=database/migrations/landlord --database=landlord
```
创建一个租户
```shell
php artisan generate:tenant
# 需要输入租客的名称（公司名称），客户端和服务端的域名
```
```shell
php artisan jwt:secret
```

生成图片符合链接
```shell
php artisan storage:link
```

---

## 多租户注意事项
#### 1. 数据库迁移
多租户房东迁移文件在 database/migrations/landlord 目录下面， 迁移房东数据库需要指定目录

迁移租户的数据库：

批量迁移
```shell
php artisan tenants:artisan "migrate --database=tenant"
```
指定迁移
```shell
php artisan tenants:artisan "migrate --path=database/migrations --database=tenant"
```
单个租户迁移
```shell
php artisan tenant:migrate --tenant=1
```
#### 2. artisan命令
artisan 命令需要user TenantAware 类，并添加tenant参数，如果tenant产生不传则会循环操作每个租户
```php
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class YourFavouriteCommand extends Command
{
    use TenantAware;

    protected $signature = 'your-favorite-command {--tenant=*}';

    public function handle()
    {
        return $this->line('The tenant is '. Tenant::current()->name);
    }
}
```

#### 3. 代码中循环操作每个租户
```php
Tenant::all()->eachCurrent(function(Tenant $tenant) {
    // the passed tenant has been made current
    Tenant::current()->is($tenant); // returns true;
});
```

#### 4. 多租户数据填充命令
```shell
php artisan tenants:artisan "db:seed --database=tenant --class=指定sender文件类"
```

### 5.国家数据填充命令
```shell
php artisan tenants:artisan  world:init --tenant=1
```

### 6.更新物流公司
```shell

php artisan tenants:artisan dsp:update-express-companies --tenant=1
```

### 7.更新管理端菜单
--tenant=1 表示参数指定指定租户菜单，默认更新所有租户菜单。无需填入tenant参数。
```shell
php artisan dsp:update-menu-route {--tenant=*}
```

### 8.更新客户端菜单
customId=1 表示更新指定客户id的菜单，默认更新所有客户菜单。无需填入customId参数。

--tenant=1 表示参数指定指定租户菜单，默认更新所有租户菜单。无需填入tenant参数。

```shell
php artisan dsp:update-client-menu {customId=0}  {--tenant=*}
```


### 9.更新历史余额的变动后余额记录

默认可忽略--tenant参数

```shell
php artisan dsp:update_balance_record_after_change_balance {--tenant=*}
```
