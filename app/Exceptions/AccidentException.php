<?php

namespace App\Exceptions;

use App\Lib\Code;
use App\Services\ApiResponseService;
use Exception;

class AccidentException extends Exception
{

    protected $message;

    protected $code;

    public function __construct($message = 'fail', $code = Code::OPERATE_FAIL)
    {
        $this->message = $message;
        $this->code = $code;
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        if ($this->code == Code::OPERATE_FAIL || $this->code == Code::CUSTOM_ERROR) {
            return ApiResponseService::error($this->code, $this->message);
        }

        if($this->code == Code::USER_NOT_AUTH) {
            return ApiResponseService::error($this->code , $this->message)->setStatusCode(401);
        }

        return ApiResponseService::error($this->code)->setStatusCode(500);
    }
}
