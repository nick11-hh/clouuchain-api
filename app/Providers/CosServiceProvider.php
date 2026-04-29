<?php

namespace App\Providers;

use App\Services\Base\SystemConfigService;
use Illuminate\Support\ServiceProvider;

class CosServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        /**
         * 设置cos配置
         */
        // SystemConfigService::setCosConfig();
    }
}
