<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | has failed, such as for an invalid token or invalid new password.
    |
    */

    'reset'     => '密码重置成功！',
    'sent'      => '密码重置邮件已发送！',
    'throttled' => '请稍候再试。',
    'token'     => '密码重置令牌无效。',
    'user'      => '找不到该邮箱对应的用户。',
    'password_requirements' => [
        '[0-9]'         => '密码必须至少包含一个整数。',
        '[A-Za-z]'      => '密码必须至少包含一个字母。',
        '[a-z]'         => '密码必须至少包含一个小写字母。',
        '[A-Z]'         => '密码必须至少包含一个大写字母。',
        '[!@#$%^&*()]'  => '密码必须至少包含一个特殊字符。',
    ],
];
