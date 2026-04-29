<?php

namespace App\Services\ExpressCompanies\KuaiDi;

use Illuminate\Contracts\Support\DeferrableProvider;

class ServiceProvider extends \Illuminate\Support\ServiceProvider implements DeferrableProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->singleton(Express::class, function () {
            return new Express(config('services.express.id'), config('services.express.key'), config('services.express.type'));
        });

        $this->app->alias(Express::class, 'express');
    }

    public function provides()
    {
        return [Express::class, 'express'];
    }
}
