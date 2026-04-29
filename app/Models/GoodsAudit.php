<?php

namespace App\Models;

use App\Services\Base\SystemConfigService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class GoodsAudit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_goods_audit';

    protected $guarded = [];

    protected $casts = [
        'main_images' => 'array',
        'options'     => 'array',
        'props'       => 'array',
    ];


    //审核状态
    public const GOOD_AUDIT_WAITING  = 0;
    public const GOOD_AUDIT_APPROVED = 1;
    public const GOOD_AUDIT_REJECTED = 2;


    //提交状态
    public const GOOD_COMMIT_WAITING  = 0;
    public const GOOD_COMMIT_APPROVED = 1;
    //审核人员
    public function auditUser()
    {
        return $this->hasOne(Admin::class, 'id', 'audit_user_id');
    }

    public function commitUser()
    {
        return $this->hasOne(Admin::class, 'id', 'commit_user_id');
    }

    public function goods()
    {
        return $this->hasOne(Goods::class, 'id', 'goods_id');
    }


    public static function auditStatusList()
    {
        return [
            self::GOOD_AUDIT_WAITING  => __('待审核'),
            self::GOOD_AUDIT_APPROVED => __('审核通过'),
            self::GOOD_AUDIT_REJECTED => __('审核驳回'),
        ];
    }

    public static function commitStatusList()
    {
        return [
            self::GOOD_COMMIT_WAITING  => __('待提交审核'),
            self::GOOD_COMMIT_APPROVED => __('已提交审核')
        ];
    }

    public function getAuditStatusNameAttribute()
    {
        return self::auditStatusList()[$this->audit_status];
    }
    public function getCommitStatusNameAttribute()
    {
        return self::commitStatusList()[$this->commit_status];
    }
    public static function init($goods_id, $isImportType = 0)
    {
        $data = [
            'goods_id' => $goods_id,
        ];

        $currentTime = Carbon::now()->toDateTimeString();
        //初次默认已提交，拒绝时需要再次提交
        $data['commit_status'] = self::GOOD_COMMIT_APPROVED;
        $data['commit_user_id'] = auth('admin')->id() ?? 0;
        $data['commit_user_name'] = auth('admin')->user()->username ?? '系统';
        $data['commit_time'] = $currentTime;
        $data['audit_remark'] = '';

        //未开启审核，默认审核通过
        if (SystemConfigService::getConfigValue(SystemConfig::OPEN_PRODUCT_DEVELOP_AUDIT) == 0 || $isImportType == 1) {
            $data['audit_status'] = 1;
            $data['audit_user_id'] = auth('admin')->id();
            $data['audit_user_name'] = auth('admin')->user()->username;
            $data['audit_remark'] = $isImportType != 1 ? '产品审核未关闭状态，默认产品审核通过' : '批量导入产品，默认审核通过';
            $data['audit_time'] = $currentTime;
        }

        return $data;
    }



}
