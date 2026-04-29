<?php

namespace App\Services;

use App\Lib\Code;

class ApiResponseService
{
    public static function success($data = [], $code = Code::SUCCESS, $message = '操作成功')
    {
        if (empty($data)) {
            $data = new \stdClass();
        }
        $message = __($message);
        return [
            'data'    => $data,
            'message' => $message,
            'code'    => $code,
            'status'  => true
        ];
    }

    public static function error($code = Code::UNKOWN_ERROR, $message = null, $data = new \stdClass())
    {
        if ($message == null) {
            $message = self::getStatusText($code);
        }
        $message = __($message);
        return response()->json([
            'data'    => $data,
            'message' => $message,
            'code'    => $code,
            'status'  => false
        ]);
    }

    /**
     * @param string $message
     * @param array $data
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public static function successMessage(string $message = 'success', array $data = [] , int $code = Code::SUCCESS)
    {
        return self::success($data, $code, $message);
    }

    /**
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public static function errorMessage(string $message = 'fail', int $code = Code::SUCCESS)
    {
        return self::error($code, $message);
    }

    /**
     * Status codes translation table.
     *
     * @var array
     */
    public static $statusTexts = [
        10000   => 'SUCCESS',
        10001   => '账号或密码错误',
        10002   => '非法操作',
        10003   => '验证码输入错误',
        10004   => '用户输入错误',

        10100   => '没有找到数据',
        10101   => '没有找到路由',

        10401   => '用户未授权',
        10403   => '没有权限访问',
        10404   => '用户未授权',
        10405   => '请求方式不允许',
        10429   => '请求过于频繁',
        10500   => '服务错误！',
        20000   => '未知错误',
    ];

    public static function getStatusText($code)
    {
        if (in_array($code, array_keys(self::$statusTexts))) {
            return self::$statusTexts[$code];
        }

        return '';
    }
}
