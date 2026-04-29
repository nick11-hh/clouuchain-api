<?php

namespace App\Services\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\Base\SystemConfigService;

class UploadService
{
    public function __construct()
    {
        SystemConfigService::setCosConfig();
    }

    /**
     * @throws \Exception
     */
    public function uploadImages(array $files)
    {
        // 检查数据是否符合预期
        if (!isset($files['images'])) {
            throw new \Exception('缺少images字段');
        }

        if (!is_array($files['images'])) {
            throw new \Exception('images必须是数组');
        }

        foreach ($files['images'] as $index => $image) {
            if (!isset($image['file'])) {
                throw new \Exception("images[$index] 缺少file字段");
            }

            if (!$image['file'] instanceof \Illuminate\Http\UploadedFile) {
                throw new \Exception("images[$index].file 必须是上传文件");
            }

            if (!$image['file']->isValid()) {
                $error = $image['file']->getError();
                $tempDir = sys_get_temp_dir();

                // 检查临时目录的详细信息
                $isTempDirWritable = is_writable($tempDir);
                $isTempDirReadable = is_readable($tempDir);
                $uploadTmpDir = ini_get('upload_tmp_dir');
                $sysGetTempDir = sys_get_temp_dir();

                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
                    UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
                    UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                    UPLOAD_ERR_NO_FILE => '没有文件被上传',
                    UPLOAD_ERR_NO_TMP_DIR => "找不到临时文件夹，系统临时目录: ". $sysGetTempDir ."，PHP上传临时目录: ". ($uploadTmpDir ?: '未设置') ."，可读: " . ($isTempDirReadable ? '是' : '否') . "，可写: " . ($isTempDirWritable ? '是' : '否'),
                    UPLOAD_ERR_CANT_WRITE => '文件写入失败',
                    UPLOAD_ERR_EXTENSION => '文件被扩展程序阻止',
                ];
                $errorMessage = $errorMessages[$error] ?? '未知上传错误，系统临时目录: ' . $sysGetTempDir . '，PHP上传临时目录: ' . ($uploadTmpDir ?: '未设置') . '，可读: ' . ($isTempDirReadable ? '是' : '否') . '，可写: ' . ($isTempDirWritable ? '是' : '否');
                throw new \Exception("images[$index].file 上传无效: " . $errorMessage);
            }
        }

        validator($files, $this->rules())->validate();
        $res = [];

        foreach ($files['images'] as $key => $image) {
            $image['name'] = $this->makeRuleName($image['file']);
            $image['subPath'] = '/admin';

            if (Storage::disk()
                ->putFileAs(
                    $image['subPath'],
                    $image['file'],
                    $image['name']
                )
            ) {
                $res[] = [
                    'name' => $image['name'],
                    'path' => $image['subPath'] . '/' . $image['name'],
                    'url' => Storage::disk()->url($image['subPath'] . '/' . $image['name']),
                    'width' => getimagesize($image['file'])[0] ?? '', //图片宽度
                    'height' => getimagesize($image['file'])[1] ?? '', //图片高度
                ];
            } else {
                return [];
            }
        }
        return $res;
    }


    protected function rules()
    {
        return [
            'images' => 'required|array',
            'images.*.name' => 'sometimes|nullable|string',
            'images.*.subPath' => 'sometimes|nullable|string',
            'images.*.file' => 'required|image',
        ];
    }

    /**
     * 获得相对路径
     * @param string $url
     * @return string
     */
    protected function getRelativeUrl(string $url): string
    {
        return str_replace(config('app.url'), '', $url);
    }

    /**
     * 规则的文件名
     *
     * @param  UploadedFile  $file
     * @return string
     */
    protected function makeRuleName(UploadedFile $file): string
    {
        return date('Ymd') . '-' . Str::random() . '.' . $this->guessFileExtension($file);
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    protected function guessFileExtension(UploadedFile $file)
    {
        $ext = $file->getClientOriginalExtension();

        return $ext ?: 'jpg';
    }
}
