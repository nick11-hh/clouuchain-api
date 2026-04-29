<?php

namespace App\Services\Client;

use App\Services\Base\SystemConfigService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadService
{

    public function __construct()
    {
        SystemConfigService::setCosConfig();
    }

    public function uploadImages(array $files)
    {
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
                    'name'   => $image['name'],
                    'path'   => $image['subPath'] . '/' . $image['name'],
                    'url'   => Storage::disk()->url($image['subPath'] . '/' . $image['name']),
                    'width'  => getimagesize($image['file'])[0] ?? '', //图片宽度
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
