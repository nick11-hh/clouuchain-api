<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\EmailTemplateList;
use App\Models\EmailTemplate;
use App\Services\Admin\EmailTemplateService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public EmailTemplateService $service;

    public function __construct(EmailTemplateService $service)
    {
        $this->service = $service;
    }

    /**
     * @desc 获取模板列表
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return EmailTemplateList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * 删除邮件模板
     * @param $id
     * @return array
     */
    public function delete($id)
    {
        return ApiResponseService::success($this->service->delete([$id]));
    }

    /**
     * @desc 获取邮件模板类型列表
     * @return array
     */
    public function getEmailTemplateTypeList(): array
    {
        return ApiResponseService::success(EmailTemplate::typeList());
    }

    /**
     * @desc 保存邮件模板
     * @param Request $request
     * @return array
     */
    public function saveEmailTemplate(Request $request): array
    {
        return ApiResponseService::success($this->service->saveEmailTemplate($request->all()));
    }

    /**
     * @desc 保存邮件模板
     * @param Request $request
     * @return array
     */
    public function setEmailTemplateStatus(Request $request): array
    {
        return ApiResponseService::success($this->service->setEmailTemplateStatus($request->all()));
    }


}
