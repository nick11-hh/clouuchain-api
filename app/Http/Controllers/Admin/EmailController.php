<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\EmailService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public EmailService $service;

    public function __construct(EmailService $service)
    {
        $this->service = $service;
    }


    public function getEmailSmtpConfig(): array
    {
        return ApiResponseService::success($this->service->getEmailSmtpConfig());
    }

    public function verifySmtpConfig(Request $request): array
    {
        return ApiResponseService::success($this->service->verifySmtpConfig($request->all()));
    }

    public function updateSmtpConfig(Request $request)
    {
        if ($this->service->updateSmtpConfig($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

}
