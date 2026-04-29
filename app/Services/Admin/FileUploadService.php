<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Services\Base\SystemConfigService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Exceptions\AccidentException;

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
     * @param  array  $files
     * @return FileUploadService
     * @throws \Illuminate\Validation\ValidationException
     */
    public function files(array $files)
    {
        validator($files, $this->rules())->validate();

        foreach ($files['files'] as $key => $file) {
            $this->files[$key]['file'] = $file['file'];

            $this->files[$key]['name'] = $this->makeRuleName($file['file']);

            $this->files[$key]['subPath'] = '/admin';
        }

        return $this;
    }

    /**
     * @return array|bool
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
                        '员工ID：%s，上传文件成功：%s， 扩展名：%s',
                        auth('admin')->id(),
                        $file['name'],
                        $file['file']->getMimeType()
                    ));

                    $res[] = [
                        'name' => $file['name'],
                        'url' => Storage::disk()->url($file['subPath'] . '/' . $file['name']),
                        'path' => $file['subPath'] . '/' . $file['name']
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
     *
     * @return array
     */
    protected function rules()
    {
        return [
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

    public function file($data)
    {

        $this->file['file'] = $data['file'];

        $this->file['name'] = date('Ymd') . '-' . Str::random() . '.' . $data['file']->getClientOriginalExtension();

        $this->file['subPath'] = '/admin';

        return $this;
    }

}
