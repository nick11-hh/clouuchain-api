<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FileUploadService;
use App\Services\Admin\UploadService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public UploadService $service;
    protected $fileUpload;

    public function __construct(UploadService $uploadService, FileUploadService $fileUploadService,)
    {
        $this->service = $uploadService;
        $this->fileUpload = $fileUploadService;
    }

    /** 上传图片
     * @param Request $request
     * @return array
     */
    public function uploadImages(Request $request)
    {
        $data = $this->service->uploadImages($request->all());
        return ApiResponseService::success($data);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function uploadFiles(Request $request)
    {
        $res = $this->fileUpload->files($request->all())->save();

        return ApiresponseService::success($res);
    }

}
