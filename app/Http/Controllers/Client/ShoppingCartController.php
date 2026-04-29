<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ShoppingCartList as ListResource;
use App\Services\ApiResponseService;
use App\Services\Client\ShoppingCartService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShoppingCartController extends Controller
{
    public ShoppingCartService $service;
    public function __construct(ShoppingCartService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        return ListResource::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function add()
    {
        if($res = $this->service->add()) {
            return ApiResponseService::success($res);
        }

        return ApiResponseService::error();
    }

    public function storeorder()
    {
        if($res = $this->service->storeOrder()) {
            return ApiResponseService::success($res);
        }

        return ApiResponseService::error();
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function deletes(Request $request)
    {
        if ($res = $this->service->deletes($request->all())) {
            return ApiResponseService::success($res);
        }
        return ApiResponseService::errorMessage('删除失败');
    }

}
