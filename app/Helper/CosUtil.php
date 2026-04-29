<?php

declare(strict_types=1);
/**
 * @Author: h9471
 * @Created: 2021/5/31 11:42
 */

namespace App\Helper;

use App\Lib\Code;
use Exception;
use Illuminate\Support\Facades\Storage;
use App\Exceptions\AccidentException;

class CosUtil
{
    public static function send(string $filePath, string $to = '/admin', string $disk = 'admin_public'): bool
    {
        $fileContent = Storage::disk($disk)->get($filePath);

        if (! $fileContent) {
            info('文件未找到', ['file' => $filePath]);

            throw new AccidentException('文件未找到', Code::OPERATE_FAIL);
        }

        $path = sprintf('%s/%s', $to, $filePath);

        return Storage::disk()->put($path, $fileContent);
    }

    /**
     * @param  string  $fileName
     * @param  string  $path
     * @param  string  $to
     * @return false|string
     */
    public static function download(string $fileName, string $path = '/admin', string $to = 'admin_public'): bool|string
    {

        $fileContent = Storage::disk()->get($path . '/' . $fileName);

        if (!$fileContent) {
            info('文件未找到', ['file' => $path . '/' . $fileName]);

            return false;
        }

        Storage::disk($to)->put($fileName, $fileContent);

        return Storage::disk($to)->path($fileName);
    }

    /**
     * @param string $imagePath
     * @param string $path
     * @param string $to
     * @return false|string
     */
    public static function downloadFromImagePath(string $imagePath, string $path = '/admin', string $to = 'admin_public'): bool|string
    {
        $name = last(explode('/', $imagePath));

        $fileContent = Storage::disk()->get($path . '/' . $name);

        if (!$fileContent) {
            info('文件未找到', ['file' => $path . '/' . $name]);

            return false;
        }

        Storage::disk($to)->put($name, $fileContent);

        return Storage::disk($to)->path($name);
    }

    /**
     * 从远程下载证书
     *
     * @param  string  $file
     * @param  string  $to
     * @return false|string
     */
    public static function downloadCert(string $file, string $to = 'admin_private_cert'): bool|string
    {
        if (!$file) {
            return Storage::disk($to)->path($file);
        }

        if (Storage::disk($to)->exists($file)) {
            return Storage::disk($to)->path($file);
        }

        try {
            $fileContent = Storage::disk('cos_private')->get('admin/cert/' . $file);
        } catch (\Exception $e) {
            info('文件未找到', ['msg' => $e->getMessage(), 'file' => 'admin/cert/' . $file]);

            return false;
        }

        if (!$fileContent) {
            info('文件未找到', ['file' => 'admin/cert/' . $file]);

            return false;
        }

        Storage::disk($to)->put($file, $fileContent);

        return Storage::disk($to)->path($file);
    }

    /**
     * @param $filePath
     * @return string
     * @throws AccidentException
     */
    public static function localUploadToCos($filePath)
    {
        $url = secure_asset(Storage::disk()->url('/admin' . $filePath));

        if (!config('app.local_storage')) {
            self::send($filePath);
            Storage::disk('admin_public')->delete($filePath);
        }
        return $url;
    }

}
