<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyExpressModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_express_companies';

    protected $casts = [
        'info' => 'array',
    ];

    const ENABLE = 1;
    const DISABLE = 0;

    public const CODE_YUNTU    = 'yuntu';  // 云途物流(老）
    public const CODE_YUNTU_NEW    = 'yuntu_new';  // 云途物流(新）
    public const CODE_YIDIDA   = 'itdida'; // 易抵达物流
    public const CODE_DISIFANG = 'disifang'; // 递四方
    public const CODE_YANWEN   = 'yanwen'; // 燕文
    public const CODE_UBI      = 'ubi'; // UBI
    public const CODE_JIEPUSI  = 'jiepusi'; // 捷普思
    public const CODE_YUNSUDI  = 'yunsudi'; // 云速递
    public const CODE_QUWULIU  = 'quwuliu'; // 趣物流
    public const CODE_CNE      = 'cne'; // cne
    public const CODE_HUALEI   = 'hua_lei'; // 华磊
    public const CODE_HUAHAN   = 'hh'; // 华翰
    public const CODE_YIDA     = 'yida'; // 义达
    public const CODE_FEITE    = 'feite'; // 飞特
    public const CODE_SUNYOU   = 'sunyou'; //顺友
    public const CODE_HAIOU    = 'haiou'; //海鸥集运
    public const CODE_TAIJIA   = 'taijia'; // 泰嘉物流(K5)
    public const CODE_JIAYOU_Q   = 'jiayou_q'; // 佳邮(全程)
    public const CODE_JIAYOU_W   = 'jiayou_w'; // 佳邮(尾程)
    public const CODE_WANBANG   = 'wanbang'; // 万邦速达
    public const CODE_WANBANG_NEW   = 'wanbang_new'; // 万邦速达(新）
    public const CODE_SANA   = 'sana'; // SANA
    public const CODE_HUA_HAI   = 'hua_hai'; // 花海物流
    public const CODE_HUA_LIAN_WAN_TONG   = 'hua_lian'; // 华联万通
    public const CODE_JI_SU_GUO_JI   = 'ji_su'; // 急速国际（新）
    public const CODE_QUAN_QIU_TONG   = 'quan_qiu_tong'; // 全酋通物流
    public const CODE_RONG_DING   = 'rong_ding'; // 容鼎
    public const CODE_SHAN_DIAN_HOU   = 'shan_dian_hou'; // 闪电猴
    public const CODE_SHANG_HAI_JU_XI   = 'ju_xi'; // 上海驹隙国际物流有限公司
    public const CODE_SHANG_HAI_SHOU_WU   = 'shou_wu'; // 上海守务国际物流有限公司
    public const CODE_SHEN_ZHEN_YOU_SHI_DA   = 'you_shi_da'; // 深圳市优时达供应链有限公司
    public const CODE_SHEN_ZHEN_ZHI_HE_MAI_LIAN   = 'zhi_he'; // 深圳智禾迈联信息技术有限公司
    public const CODE_SHUN_FENG_IBS   = 'ibs'; // 顺丰国际-IBS
    public const CODE_SHUN_FENG_KTS   = 'kts'; // 顺丰国际KTS
    public const CODE_SHUN_FENG   = 'shun_feng'; // 顺丰国内物流
    public const CODE_SHUN_YOU   = 'shun_you'; // 顺友物流（新）
    public const CODE_TONG_YOU   = 'tong_you'; // 通邮
    public const CODE_WAN_GUO_YOU_LIAN   = 'wan_guo'; // 万国优联
    public const CODE_YI_HAO   = 'yi_hao'; // 壹号
    public const CODE_KUA_YUE   = 'kua_yue'; // 跨越速运物流

    public function dockingInfo()
    {
        return $this->hasOne(CompanyDockingInfoModel::class, 'type', 'type');
    }

    public function LogisticsChannel()
    {
        return $this->hasMany(LogisticsChannelModel::class, 'express_companies_id', 'id');
    }

}
