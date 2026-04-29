<?php

namespace App\Services\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Models\InvoiceRecords;

/**
 * 发票相关Trait助手类
 * Trait InvoiceTrait
 * @package App\Services\Traits
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/20 14:39
 */
trait InvoiceTrait
{
    /**
     * 生成单号
     * @param int $customId
     * @param int $type
     * @return string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/20 14:39
     */
    public function generateInvoiceNo(int $customId, int $type)
    {
        $fixedSuffix =  date('Ymd') . str_pad($customId, 3, '0', STR_PAD_LEFT);
        $key = "invoice_no_type_". $type . '_' . $fixedSuffix;

        if (($id = Cache::get($key))) {
            $value = $id + 1;
            Cache::increment($key);
        } else {
            $value = 1;
            Cache::put($key, $value, (Carbon::now()->endOfDay()->unix() - Carbon::now()->unix()));
        }

        $prefix =  $type == InvoiceRecords::TYPE_ORDER ? "D" : "";

        return $prefix . $fixedSuffix . str_pad($value, 2, '0', STR_PAD_LEFT);
    }
}
