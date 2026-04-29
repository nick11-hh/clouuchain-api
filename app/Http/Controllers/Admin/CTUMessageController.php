<?php


namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CTUMessageInfo as InfoResource;
use App\Http\Resources\Admin\CTUMessageList as ListResources;
use App\Services\Admin\CTUMessageService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class CTUMessageController extends Controller
{
    protected $service;

    public function __construct(CTUMessageService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return ListResources::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return InfoResource
     */
    public function show(int $id)
    {
        return InfoResource::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    /**
     * 更新
     * @param Request $request
     * @param int $id
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    /**
     * @param int $id
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function updateTrans(int $id)
    {
        if ($this->service->updateTrans($id, \request()->all())) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    /**
     * @param $id
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if ($this->service->destroy($id)) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    public function push($id)
    {
        if ($this->service->push($id)) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }
}
