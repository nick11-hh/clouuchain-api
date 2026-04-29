<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/admin')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware(['api', 'language'])
                ->prefix('api/client')
                ->namespace($this->namespace)
                ->group(base_path('routes/client.php'));

            Route::middleware(['api', 'language'])
                ->prefix('api/open')
                ->namespace($this->namespace)
                ->group(base_path('routes/open.php'));

            Route::middleware('api')
                ->prefix('wp-json')
                ->name('wp-json.')
                ->namespace($this->namespace)
                ->group(base_path('routes/wp-json.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('api')
                ->prefix('api/client')
                ->namespace($this->namespace)
                ->group(base_path('routes/platform.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
        });
    }
}
