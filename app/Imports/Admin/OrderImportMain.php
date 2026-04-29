<?php

namespace App\Imports\Admin;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * 管理端订单导入处理入口
 * Class OrderImportMain
 * @package App\Imports\Admin
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/13 15:49
 */
class OrderImportMain implements WithMultipleSheets
{

    public function sheets(): array
    {
        return [
            0 => new OrderImport(),
        ];
    }
}
