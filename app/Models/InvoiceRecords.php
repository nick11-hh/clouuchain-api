<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 发票记录表
 * Class InvoiceRecords
 * @package App\Models
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/19 16:44
 */
class InvoiceRecords extends Model
{
    use HasFactory;

    public $table = 'dsp_invoice_records';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    public const TYPE_ORDER = 1; //订单发票
    public const TYPE_TRANSFER_RECHARGE = 2; //转账充值发票
    public const TYPE_ONLINE_RECHARGE = 3; //在线充值发票

    public const TYPE_CREDIT_CARD_RECHARGE = 4; //信用卡充值发票

    public const NORMAL_STATUS = 1; //正常状态
    public const INVALID_STATUS = 2; //作废状态

    public const WAIT_STATUS = 0; //生成中
    public const FAIL_STATUS = 3; //生成失败

    public const TYPE_ORDER_GENERATE_MAX_COUNT = 3000; //订单发票生成最大订单数量

    public const FILE_TYPE_PDF = 1; //PDF文件类型
    public const FILE_TYPE_WORD = 2; //Word文件类型
    public const FILE_TYPE_EXCEL = 3; //Excel文件类型

    /**
     * 关联客户表
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/19 16:46
     */
    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id','id');
    }

    /**
     * 获取发票类型
     * @return string[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/19 17:31
     */
    public static function getSourceTypeList()
    {
        return [
            self::TYPE_ORDER => __('订单发票'),
            self::TYPE_TRANSFER_RECHARGE => __('转账充值发票'),
            self::TYPE_ONLINE_RECHARGE => __('在线充值发票'),
            self::TYPE_CREDIT_CARD_RECHARGE => __('信用卡充值发票'),
        ];
    }

    /**
     * 获取发票类型名称数据
     * @return string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/19 17:34
     */
    public function getSourceTypeNameAttribute()
    {
        return self::getSourceTypeList()[$this->source_type] ?? '';
    }

    /**
     * 获取文件类型
     * @return string[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/31 16:23
     */
    public static function getFileTypeList()
    {
        return [
            self::FILE_TYPE_PDF     => __('PDF文件'),
            self::FILE_TYPE_WORD    => __('Word文件'),
            self::FILE_TYPE_EXCEL   => __('Excel文件'),
        ];
    }

    /**
     * 获取文件类型名称
     * @return string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/31 16:22
     */
    public function getFileTypeNameAttribute()
    {
        return self::getFileTypeList()[$this->file_type] ?? '';
    }


}
