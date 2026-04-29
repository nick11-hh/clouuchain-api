<?php
/**
 * @Author: h9471
 * @date 2020-03-03
 */

namespace App\Http\Controllers\Admin;

use App\Exceptions\AccidentException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RemoteDestinationList;
use App\Services\Admin\RemoteDestinationService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class RemoteDestinationController extends Controller
{
    protected $service;

    public function __construct(RemoteDestinationService $service)
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
        return RemoteDestinationList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse| mixed
     * @throws AccidentException
     * @throws \Throwable
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse| mixed
     * @throws AccidentException
     * @throws \Throwable
     */
    public function batchStore(Request $request)
    {
        if ($this->service->batchStore($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return RemoteDestinationList
     */
    public function show(int $id)
    {
        return RemoteDestinationList::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse|array
     * @throws AccidentException
     */
    public function update(int $id)
    {
        if ($this->service->update($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return \Illuminate\Http\JsonResponse|array
     * @throws AccidentException
     */
    public function destroy(Request $request)
    {
        if ($this->service->destroy($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
