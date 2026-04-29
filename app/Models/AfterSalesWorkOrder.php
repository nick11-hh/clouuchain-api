<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 售后工单模型
 * Class AfterSalesWorkOrder
 * @package App\Models
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/6 15:24
 */
class AfterSalesWorkOrder extends Model
{
    use HasFactory;

    protected $table = 'dsp_after_sales_work_order';

    protected $guarded = [];

    protected $casts = [
        'attachment_url' => 'json'
    ];

    protected $hidden = [];

    public const PENDING_HANDLE_STATUS = 1; //待处理
    public const HANDING_STATUS = 2; //处理中
    public const HANDLE_COMPLETE_STATUS = 3; //处理完成
    public const CLOSED_STATUS = 4; //已关闭

    /**
     * 关联客户
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/6 15:26
     */
    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    /**
     * 处理人
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/6 15:26
     */
    public function handleAdmin()
    {
        return $this->belongsTo(Admin::class, 'handle_admin_id', 'id');
    }

    /**
     * 获取工单类型
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/6 17:36
     */
    public static function getTypeList()
    {
        return [
            [
                'id' => 1,
                'name' => __('包裹丢失'),
            ],
            [
                'id' => 2,
                'name' => __('包裹破损'),
            ],
            [
                'id' => 3,
                'name' => __('货物不对'),
            ],
            [
                'id' => 4,
                'name' => __('更换商品'),
            ],
            [
                'id' => 5,
                'name' => __('其他问题'),
            ],
        ];
    }

    /**
     * 获取类型名称
     * @param $id
     * @return mixed|string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/7 11:31
     */
    public static function getTypeName($id)
    {
        $typeList = self::getTypeList();
        $resultKey = array_search($id, array_column($typeList, 'id'));

        return $resultKey !== false ? $typeList[$resultKey]['name'] : '';
    }

}
