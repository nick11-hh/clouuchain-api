<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AlibabaService;
use App\Services\ApiResponseService;
use Illuminate\Support\Facades\DB;

class AlibabaController extends Controller
{
    public AlibabaService $service;

    public function __construct(AlibabaService $service)
    {
        $this->service = $service;
    }

    public function alibabaAuthorize($id): array
    {
        return ApiResponseService::success($this->service->alibabaAuthorize($id));
    }

    public function auth()
    {
        $uuid = request()->get('uuid');

        $sql = "SELECT * FROM tenants WHERE uuid='$uuid'";
        $res = DB::connection('landlord')->select($sql);

        if ($this->service->generateAccessToken()) {
            return \view('success', ['url' => $res[0]->admin_domain]);
        }

        return \view('error', ['url' => $res[0]->admin_domain]);
    }
}
