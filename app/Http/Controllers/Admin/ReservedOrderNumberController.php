<?php
/**
 * 预留单号控制器
 */
namespace App\Http\Controllers\Admin;

use App\Exceptions\AccidentException;
use App\Http\Controllers\Controller;
use App\Services\Admin\ReservedOrderNumberService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReservedOrderNumberController extends Controller
{
    protected $service;

    public function __construct(ReservedOrderNumberService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $res = $this->service->index();
        return ['code'=> 10000, 'message' => '操作成功', 'data'=> $res['data'], 'meta' => $res['meta']];
    }

    /**
     * 导入预留单号
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function import(Request $request): JsonResponse|array
    {
        $file = $request->file('file');

        if (! $file) {
            return ApiResponseService::error();
        }

        return ApiResponseService::success($this->service->import($file));
    }

    /**
     * 添加预留单号
     * @return array|JsonResponse
     * @throws \Throwable
     */
    public function store(): array|JsonResponse
    {
        $res = $this->service->store();
        if($res['exp'] === 1) {
            return ['ret' => 0, 'msg' => '操作失败', 'data' => $res['data']];
        }else {
            return ApiResponseService::success($res['data']);
        }
    }

    /**
     * 将单号作废
     * @return JsonResponse|array
     * @throws Exception
     */
    public function isInvalid(): JsonResponse|array
    {
        if ($this->service->isInvalid()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 预留单号详情
     * @param $id
     * @return array
     * @throws Exception
     */
    public function show($id): array
    {
        return ApiResponseService::success($this->service->show($id));
    }


    public function numberItems($id)
    {
        $res = $this->service->numberItems($id);
        return ['code' => 10000, 'message' => '操作成功', 'data' => $res['data'], 'meta' => $res['meta']];
    }

    /**
     * 删除预留单号
     * @param $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function delNumber($id): JsonResponse|array
    {
        if($this->service->delNumber($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 下载导入预留单号模板
     * @return StreamedResponse
     */
    public function downloadTpl()
    {
        $fileName = 'reserved_tpl.xlsx';
        $file = Storage::get($fileName);
        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $fileName);
    }

    /**
     * 删除单个预留单号
     * @param $id
     * @return JsonResponse|array
     */
    public function delIitemNo($id): JsonResponse|array
    {
        if($this->service->delItemNo($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function getExpressCompanies(): array
    {
        return  ApiResponseService::success($this->service->getExpressCompanyList());
    }
}
