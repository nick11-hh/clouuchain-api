<?php

namespace App\Services\Client;

use App\Lib\Code;
use App\Services\Base\SystemConfigService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Exceptions\AccidentException;

/**
 * 文件上传服务类
 * Class FileUploadService
 * @package App\Services\Client
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/7 13:58
 */
class FileUploadService
{
    protected $files = [];

    protected $file = [];

    public function __construct()
    {
        SystemConfigService::setCosConfig();
    }

    /**
     *
     * @param array $files
     * @return FileUploadService
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function uploadFiles(array $files)
    {
        validator($files, $this->rules())->validate();

        $maxFileUploads = $files['max_file_uploads'] ?? ini_get('max_file_uploads');

        if ($maxFileUploads > 0 && count($files) > $maxFileUploads) {
            throw new AccidentException('目前最多支持同时上传'. $maxFileUploads .'个文件，请移除上传文件重试', Code::OPERATE_FAIL);
        }

        foreach ($files['files'] as $key => $file) {
            $this->files[$key]['file'] = $file['file'];

            $this->files[$key]['name'] = $this->makeRuleName($file['file']);

            $this->files[$key]['subPath'] = '/client';
        }

        return $this;
    }

    /**
     * 保存上传文件
     * @return array|bool
     * @throws \Exception
     */
    public function save()
    {
        try {
            $res = [];
            foreach ($this->files as $file) {
                if (Storage::disk()
                    ->putFileAs(
                        $file['subPath'],
                        $file['file'],
                        $file['name']
                    )
                ) {
                    info(sprintf(
                        '客户ID：%s，上传文件成功：%s， 扩展名：%s',
                        getCustomId(),
                        $file['name'],
                        $file['file']->getMimeType()
                    ));

                    $res[] = [
                        'name' => $file['name'],
                        'url' => Storage::disk()->url($file['subPath'] . '/'. $file['name']),
                        'path' => $file['subPath'] . '/' . $file['name'],
                    ];
                } else {
                    return [];
                }
            }
        } catch (\Exception $e) {
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }

        return $res ?? [];
    }

    /**
     * 上传验证规则
     * @return array
     */
    protected function rules()
    {
        return [
            'max_file_uploads' => "sometimes|nullable|int",
            'files' => 'required|array',
            'files.*.name' => 'sometimes|nullable|string',
            'files.*.subPath' => 'sometimes|nullable|string',
            'files.*.file' => 'required|file',
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
     * @param UploadedFile $file
     * @return string
     */
    protected function makeRuleName(UploadedFile $file): string
    {
        return date('Ymd') . '-' . Str::random() . '.' . $file->getClientOriginalExtension();
    }

    public function uploadFile($data)
    {

        $this->file['file'] = $data['file'];

        $this->file['name'] = date('Ymd') . '-' . Str::random() . '.' . $data['file']->getClientOriginalExtension();

        $this->file['subPath'] = '/client';

        return $this;
    }
}
