<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * 日志中间件
 */
class RequestParamsLog
{
    protected $exceptPath = [];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $redirectToRoute
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        return $next($request);
    }

    /**
     * @param $request
     * @param $response
     * @return void
     */
    public function terminate($request, $response)
    {
        if ($request->method() === 'GET') return;
        $path = $request->path();
        $time = number_format(microtime(true) - request()->server('REQUEST_TIME_FLOAT'), 3) . ' s';

        if (!in_array($path, $this->exceptPath)) {
            // app('log')->debug('[请求记录]请求响应时间:'.$time.' 当前请求的方法为:' . $request->method() . ' 当前请求的 path 为: ' . $path . ' 当前请求的参数为: ', $request->all() ?? []);
            Log::channel('request_access')->debug('[请求记录]请求响应时间:'.$time.' 当前请求的方法为:' . $request->method() . ' 当前请求的 path 为: ' . $path . ' 当前请求的参数为: ', $request->all() ?? []);
        }
    }
}
