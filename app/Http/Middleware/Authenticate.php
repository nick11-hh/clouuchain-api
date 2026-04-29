<?php

namespace App\Http\Middleware;

use App\Lib\Code;
use App\Services\ApiResponseService;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return ApiResponseService::error(Code::OPERATE_FAIL, '请登录后操作')->setStatusCode(401);
        }
    }
}
