<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class TenantMigrate extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $tenant = Tenant::current();
        if (empty($tenant)) {
            $this->error('租户不存在');
        }
        $this->line('----------------------------------------------');
        $this->info("迁移租户: {$tenant->name} (id: {$tenant->id})");
        Config::set('database.connections.tenant.database', $tenant->database);
        Config::set('telescope.storage.database.connection', 'tenant');
        Artisan::call('migrate --database=tenant');
        $this->info('迁移成功');
    }
}
