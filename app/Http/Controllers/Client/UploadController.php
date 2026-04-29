<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\UploadService;
use App\Services\Client\FileUploadService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    public UploadService $service;

    public FileUploadService $fileUpload;

    public function __construct(UploadService $uploadService, FileUploadService $fileUpload)
    {
        $this->service = $uploadService;

        $this->fileUpload = $fileUpload;
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
     * 批量上传文件
     * @param Request $request
     * @return array
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/7 14:11
     */
    public function uploadFiles(Request $request)
    {
        $result = $this->fileUpload->uploadFiles($request->all())->save();
        return ApiResponseService::success($result);
    }

}
