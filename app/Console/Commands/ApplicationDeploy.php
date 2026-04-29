<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class ApplicationDeploy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:deploy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '代码发版更新，清除缓存';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 清除 opcache 缓存
        Http::get(env('APP_URL') . '/api/admin/clear-opcache/dfa16c36dbe6470417f0c8c8d674bcdc');
        $this->info('清除opcache缓存成功');

        // 清除 laravel 路由缓存
        Artisan::call('route:clear');
        $this->info('清除laravel路由缓存');

        // 开启 laravel 路由缓存
        Artisan::call('route:cache');
        $this->info('重新生成laravel路由缓存');
    }
}
