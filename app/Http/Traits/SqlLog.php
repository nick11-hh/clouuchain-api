<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\DB;

/**
 * 这是个用于打印 sql 语句的文件
 */
trait SqlLog
{
    public function sqlLog()
    {
        // 在需要打印SQL的语句前添加监听事件。
        DB::listen(function ($query) {
            $bindings = $query->bindings;
            $sql = $query->sql;
            foreach ($bindings as $replace) {
                $value = is_numeric($replace) ? $replace : "'" . $replace . "'";
                $sql = preg_replace('/\?/', $value, $sql, 1);
            }
            app('log')->channel('single')->info('数据库查询语句为：' . $sql);
        });
        // 要打印SQL的语句
    }
}
