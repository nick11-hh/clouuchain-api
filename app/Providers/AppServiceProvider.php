<?php

namespace App\Providers;

use App\Services\ReisService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('reis', function ($app) {
            return new ReisService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 开启SQL查询日志记录
        if (config('app.debug')) {
            DB::listen(function ($query) {
                Log::channel('sql')->info('SQL Query Executed', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                    'connection_name' => $query->connectionName,
                ]);
            });
        }
    }
}
