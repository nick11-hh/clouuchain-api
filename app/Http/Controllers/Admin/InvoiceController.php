<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AccidentException;
use App\Http\Controllers\Controller;
use App\Lib\Code;
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

    /**
     * 更新发票模板
     *
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function invoiceTemplate(Request $request)
    {
        $params = $request->all();
        $params['customer_id'] = $params['customer_id'] ?? $params['customerId'] ?? null;
        $params['order_mode'] = $params['order_mode'] ?? $params['orderMode'] ?? null;

        validator($params, [
            'customer_id' => 'required|integer|min:1',
            'order_mode' => 'required',
            'template' => 'required|array|min:1',
            'template.*' => 'integer|min:1',
        ], [
            'customer_id.required' => '请选择客户',
            'order_mode.required' => '请选择模板模式',
            'template.required' => '请选择模板字段',
        ])->validate();

        if (!$this->service->invoiceTemplate($params)) {
            throw new AccidentException('发票模板申请失败，请重试', Code::OPERATE_FAIL);
        }

        return ApiResponseService::successMessage('发票模板更新成功');
    }

    /**
     * 查看发票模板
     *
     * @param mixed $orderMode
     * @return array
     */
    public function invoiceTemplateGet($orderMode): array
    {
        return ApiResponseService::success($this->service->invoiceTemplateGet($orderMode));
    }

    /**
     * 查看被选中的发票模板
     *
     * @param mixed $orderMode
     * @param int $customerId
     * @return array
     */
    public function invoiceTemplateGetChecked($orderMode, int $customerId): array
    {
        return ApiResponseService::success($this->service->invoiceTemplateGetChecked($orderMode, $customerId));
    }
}
