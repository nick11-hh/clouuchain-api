<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierVisit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_supplier_visits';

    protected $guarded = [];

    protected $casts = [
        'visit_date' => 'date',
    ];

    /**
     * 获取关联的供应商
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    /**
     * 初始化供应商拜访记录数据
     *
     * @param array $params 输入参数
     * @return array 处理后的数据
     */
    public static function init($params)
    {
        return [
            'supplier_id' => $params['supplier_id'],
            'visit_date_start' => $params['visit_date_start'],
            'visit_date_end' => $params['visit_date_end'],
            'product' => $params['product'] ?? null,
            'key_results' => $params['key_results'] ?? null,
        ];
    }
}
