<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\LikeScope;
use function auth;

class ExcelExport extends Model
{
    use Basis,
        LikeScope;

    public const TYPE_ORDER = 1;
    public const TYPE_ORDER_DIANXIAOMI = 2;
    public const TYPE_SHIPMENT= 9;
    public const TYPE_PRICE_TABLE= 10;
    public const TYPE_SERVICE_PRICE_TABLE = 11;
    public const TYPE_COUPONS = 18;
    public const TYPE_CUSTOM = 19; //导出客户
    public const TYPE_BALANCE_RECORD = 20; //导出客户交易流水
    public const TYPE_OFFLINE_RECHARGE = 21; //导出线下充值记录
    public const TYPE_ONLINE_RECHARGE = 22; //导出在线充值记录
    public const TYPE_CREDIT_CARD_RECHARGE_RECORD = 23; //导出信用卡充值记录

    public const TYPE_STOCK_REPORT = 24; // 导出备货报表
    public const STATUS_EXPORTING = 0; //导出中
    public const STATUS_DONE = 1; //导出完成
    public const STATUS_FAILED = 2; //导出失败

    protected $table = 'dsp_excel_exports';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->owner_id = auth()->id();
        });
    }
}
