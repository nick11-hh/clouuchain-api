<?php
/**
 * @Author: h9471
 * @Created: 2020/3/30 15:26
 */

namespace App\Http\Controllers;

use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;

trait HasTranslateOperator
{
    /**
     * @param  int  $id
     * @return JsonResponse|array
     */
    public function updateTrans(int $id): JsonResponse|array
    {
        if ($this->service->updateTranslateData($id, request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
