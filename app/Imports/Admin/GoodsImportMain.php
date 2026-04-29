<?php

namespace App\Imports\Admin;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * 管理端-产品导入处理入口
 * Class GoodsImportMain
 * @package App\Imports\Admin
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/12/13 18:38
 */
class GoodsImportMain implements WithMultipleSheets
{
    public function sheets(): array
    {
        // TODO: Implement sheets() method.
        return  [
            0 => new GoodsImport(),
        ];
    }
}
