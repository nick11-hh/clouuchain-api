<?php

namespace App\Models;

use App\Models\Traits\Basis;

class PurchaseOrderLogs extends Model
{
    use Basis;

    protected $table = 'dsp_purchase_order_logs';

    // 日志类型 1-新增采购订单 2-状态更改 3-1688下单
    public const OPERATOR_TYPE_CREATE = 1;
    public const OPERATOR_TYPE_STATUS_CHANGE = 2;
    public const OPERATOR_TYPE_1688_ORDER = 3;

    public function admin()
    {
        return $this->hasOne(Admin::class, 'id', 'operator_id');
    }

    public static function init($params): array
    {
        return [
            'purchase_id' => $params['purchase_id'] ?? 0,
            'operator_type' => $params['operator_type'] ?? 1,
            'content' => $params['content'] ?? '',
            'operator_id' => auth('admin')->id(),
            'created_at' => $params['created_at'] ?? now(),
            'updated_at' => $params['updated_at'] ?? now(),
        ];
    }

    public static function addLog($params)
    {
        return self::query()->create(self::init($params));
    }

    public static function batchAddLog($purchaseIds, $type, $content)
    {
        $data = [];
        foreach ($purchaseIds as $id) {
            $param = [
                'purchase_id' => $id,
                'operator_type' => $type,
                'content' => $content,
            ];

            $data[] = self::init($param);
        }

        return self::query()->insert($data);
    }

}
