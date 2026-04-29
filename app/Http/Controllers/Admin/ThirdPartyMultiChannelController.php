<?php
/**
 * @Author: h9471
 * @Created: 2019/9/10 11:38
 */

namespace App\Http\Controllers\Admin;

use App\Http\Resources\ThirdPartyMultiChannelList as ListResources;
use App\Services\Admin\ThirdPartyMultiChannelService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Throwable;

class ThirdPartyMultiChannelController extends Controller
{
    protected $service;

    public function __construct(ThirdPartyMultiChannelService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return ListResources::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return ListResources
     */
    public function show(int $id): ListResources
    {
        return ListResources::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 更新
     * @param Request $request
     * @param int $id
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function update(Request $request, int $id)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 批量删除
     * @param $id
     * @return JsonResponse|array
     */
    public function destroy($id): JsonResponse|array
    {
        if ($this->service->delete($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

}
