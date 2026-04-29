<?php

namespace App\Providers;

use App\Services\RabbitMQService;
use Illuminate\Support\ServiceProvider;

class RabbitMQServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // 绑定RabbitMQ服务到服务容器
        $this->app->singleton(RabbitMQService::class, function ($app) {
            return new RabbitMQService();
        });
        
        // 创建别名以便更方便地使用
        $this->app->alias(RabbitMQService::class, 'rabbitmq');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}