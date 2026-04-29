<?php
/**
 * @Author: h9471
 * @Created: 2019/9/10 11:37
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExcelExport;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;

class ExcelExportController extends Controller
{
    /**
     * @return JsonResponse|array
     */
    public function index()
    {
        $data = ExcelExport::query()
            ->where('owner_id', auth()->id())
            ->latest()
            ->limit(5)
            ->get();

        return ApiResponseService::success($data);
    }
}
