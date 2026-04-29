<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\InvoiceService;
use Illuminate\Http\Request;
use App\Services\ApiResponseService;
use App\Http\Resources\Admin\InvoiceList;
use App\Http\Resources\Admin\InvoiceInfo;
use Illuminate\Validation\ValidationException;

/**
 * 发票管理
 * Class InvoiceController
 * @package App\Http\Controllers\Client
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/19 18:43
 */
class InvoiceController extends Controller
{
    public $service;

    /**
     * 初始化
     * @param InvoiceService $service
     */
    public function __construct(InvoiceService $service)
    {
        $this->service = $service;
    }

    /**
     * 列表
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/19 19:13
     */
    public function index()
    {
        return InvoiceList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * 详情
     * @param int $id
     * @return InvoiceInfo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/19 19:13
     */
    public function detail(int $id)
    {
        return InvoiceInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 更新
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/19 19:13
     */
    public function update($id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    /**
     * 获取来源
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/20 14:00
     */
    public function getSourceTypeList()
    {
        return ApiResponseService::success($this->service->getSourceTypeList());
    }

    /**
     * 申请发票
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/20 17:45
     */
    public function requestInvoice(Request $request)
    {
        $params = $request->all();

        if ($this->service->requestInvoice($params)) {
            return ApiResponseService::successMessage('发票申请成功，请稍后在发票管理页面下载');
        }
        return ApiResponseService::errorMessage('发票申请失败，请重试');
    }
}
