<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\HasBatchOperate;
use App\Http\Resources\OrderTagsList;
use App\Services\Admin\OrderTagsService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * 订单标签
 */
class OrderTagsController extends Controller
{
    use HasBatchOperate;

    protected OrderTagsService $service;

    public function __construct(OrderTagsService $service)
    {
        $this->service = $service;
    }

    /**
     * @param Request $request
     */
    public function index(Request $request)
    {
        return OrderTagsList::collection($this->service->index())->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        return OrderTagsList::make($this->service->show($id))->additional(ApiResponseService::success());
    }

    /**
     * 新增
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if ($this->service->store()) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /**
     * 修改
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        if ($this->service->update($id)) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function deletes(Request $request)
    {
        if ($this->service->deletes($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    /**
     * 自定义排序
     */
    public function sort(Request $request)
    {
        if ($this->service->sort($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * 更新翻译
     */
    public function updateTranslate(int $id, Request $request)
    {
        if ($this->service->updateTranslate($id, $request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

}
