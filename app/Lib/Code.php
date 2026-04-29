<?php

namespace App\Lib;

class Code
{
    const SUCCESS               = 10000;                      // 成功
    const USER_NOT_AUTH         = 10401;                      // 用户未授权
    const SERVER_ERROR          = 10500;                      // 服务端错误
    const REQUEST_METHOD_ERROR  = 10405;                      // 请求的方法被禁止
    const OPERATE_FAIL          = 10400;                      // 操作失败
    const UNKOWN_ERROR          = 20000;                      // 未知错误
    const COMMAN_DATA_NOT_FOUND = 10100;                      // 没有找到数据
    const COMMAN_URL_NOT_FOUND  = 10101;                      // 没有找到路由

    const USER_LOGIN_ERROR       = 10001;                     // 账号或密码错误
    const USER_ILLEGAL_OPERATION = 10002;                     // 非法操作
    const USER_CAPTCHA_ERROR     = 10003;                     // 验证码输入错误
    const USER_INPUT_ERROR       = 10004;                     // 用户输入错误
    const USER_PERMISSION_DENIED = 10403;                     // 没有权限访问
    const THTOTTLE_REQUEST_ERROR = 10429;                     // 请求过于频繁
    const CUSTOM_ERROR           = 10888;                     // 自定义错误

}
