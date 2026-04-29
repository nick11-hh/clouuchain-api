<?php


namespace App\Http\Controllers\Client;


use App\Http\Controllers\Controller;
use App\Http\Resources\Client\CTUUserMessageInfo as InfoResource;
use App\Http\Resources\Client\CTUUserMessageList as ListResources;
use App\Services\ApiResponseService;
use App\Services\Client\CTUUserMessageService;
use Illuminate\Http\Request;

class CTUUserMessageController extends Controller
{
    protected $service;

    public function __construct(CTUUserMessageService $service)
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

    public function destroy(Request $request)
    {
        if ($this->service->destroy($request->input('ids'))) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    public function read(Request $request)
    {
        if ($this->service->read($request->input('ids'))) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    public function readAll()
    {
        if ($this->service->readAll()) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    public function destroyReadAll()
    {
        if ($this->service->destroyReadAll()) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }
}
