<?php

namespace App\Exceptions;

use App\Lib\Code;
use App\Services\ApiResponseService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Spatie\Multitenancy\Exceptions\NoCurrentTenant;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
        //\Exception::class
        AccidentException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {

        if($request->is('api/*') && $e instanceof ValidationException) {
            return ApiResponseService::error(Code::OPERATE_FAIL, $e->getMessage());
        }

        if($e instanceof AuthenticationException) {
            return ApiResponseService::error(Code::USER_NOT_AUTH, __('请先登录'))->setStatusCode(401);
        }

        if($e instanceof NotFoundHttpException) {
            return ApiResponseService::error(Code::COMMAN_URL_NOT_FOUND)->setStatusCode(404);
        }

        if ($e instanceof ThrottleRequestsException) {
            return ApiResponseService::error(Code::THTOTTLE_REQUEST_ERROR)->setStatusCode(429);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return ApiResponseService::error(Code::REQUEST_METHOD_ERROR)->setStatusCode(405);
        }

        if($e instanceof RouteNotFoundException) {
            return ApiResponseService::error(Code::USER_NOT_AUTH);
        }

        if ($e instanceof ModelNotFoundException) {
            return ApiResponseService::errorMessage('数据不存在', Code::USER_NOT_AUTH);
        }

        if ($e instanceof NoCurrentTenant) {
            return ApiResponseService::errorMessage('无权限访问', Code::USER_PERMISSION_DENIED);
        }

        if ($request->is('api/*')) {
            if ($e->getCode() == Code::OPERATE_FAIL) {
                return ApiResponseService::error($e->getCode(), $e->getMessage());
            }

            if($e->getCode() == Code::USER_NOT_AUTH) {
                return ApiResponseService::error($e->getCode(), $e->getMessage())->setStatusCode(401);
            }

            return ApiResponseService::error(Code::SERVER_ERROR)->setStatusCode(500);
        }

        return parent::render($request, $e);
    }
}
