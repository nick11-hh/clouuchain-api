<?php
namespace App\Http\Controllers\Admin;

use App\Http\Resources\QuotationTemplateList;
use App\Services\Admin\QuotationTemplateService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * 报价模板--控制器
 */
class QuotationTemplateController extends Controller
{
    /**
     * @var QuotationTemplateService
     */
    protected $service;

    /**
     * 初始化
     * @param QuotationTemplateService $service
     */
    public function __construct(QuotationTemplateService $service)
    {
        $this->service = $service;
    }

    /**
     * 列表
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return QuotationTemplateList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * 获取所有报价模板列表
     * @return AnonymousResourceCollection
     */
    public function list(): AnonymousResourceCollection
    {
        return QuotationTemplateList::collection($this->service->list())
            ->additional(ApiResponseService::success());
    }

    /**
     * 创建
     * @param Request $request
     * @return JsonResponse|array
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function createTemplate(Request $request): JsonResponse|array
    {
        if ($quote = $this->service->createTemplate($request->all())) {
            return ApiResponseService::success($quote);
        }
        return ApiResponseService::error();
    }

    /**
     * 更新
     * @param $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateTemplate($id, Request $request): JsonResponse|array
    {
        if ($quote = $this->service->updateTemplate($id, $request->all())) {
            return ApiResponseService::success($quote);
        }
        return ApiResponseService::error();
    }

    /**
     * 设置状态
     * @param int $id
     * @param int $status
     * @return JsonResponse|array
     */
    public function setStatus(int $id, int $status): JsonResponse|array
    {
        if ($this->service->setStatus($id, (bool) $status)) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    /**
     * 删除
     * @param int $id
     * @return JsonResponse|array
     */
    public function deleteTemplate(int $id): JsonResponse|array
    {
        if ($this->service->deleteTemplate($id)) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }
}
