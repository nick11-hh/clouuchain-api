<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDockingRecordModel extends Model
{
    use HasFactory;

    public const TYPE_SF_EXPRESS = 1; //顺丰速运

    public const TYPE_ROGAN = 2; //融港群慧顺丰速运

    public const TYPE_PCA_EXPRESS = 3; //PCA Express

    public const TYPE_JT_EXPRESS = 4; //PCA Express

    public const TYPE_LINE_CLEAR = 5; //LINE Clear

    public const TYPE_KERRY = 6; //Kerry

    public const TYPE_HUA_LEI = 7; //Hua lei

    public const TYPE_YI_DA = 8; //Yi Da

    public const TYPE_LT_EXP = 9; //乐天

    public const TYPE_BEST = 10; //百世快递

    public const TYPE_CHINA_POST_SHANDONG = 11; // 山东邮政省签商业渠道

    public const TYPE_HUA_LEI_NEW = 12; //华磊 新

    public const TYPE_ZTO_EAST_ASIA = 13; // ZTO East Asia

    public const TYPE_MA_BANG_ERP = 14; // 马帮ERP

    public const TYPE_SN_FREIGHT = 15; // 速鸟国际

    public const TYPE_JZX = 16; //新智慧TMS

    public const TYPE_FLASH_EXPRESS = 17; // FlashExpress

    public const TYPE_COM_ONE_EXPRESS = 18; // ComOneExpress

    public const TYPE_JT_EXPRESS_TH = 19; // JT_EXPRESS_TH

    public const TYPE_QQT = 20; //QQT Express

    public const TYPE_EMS = 21; // EMS

    public const TYPE_IN_TE_LINK = 22; // http://intelink.net/

    public const TYPE_I_OMS = 23; // https://www.i-oms.com

    public const TYPE_LION_PARCEL = 24; // https://lionparcelapi.docs.apiary.io

    public const TYPE_JUN_AN_EX = 25; // https://trade1.junanex.com/api/guide/v2_api_local

    public const TYPE_EMS_MA_CHAO_1 = 30;

    public const TYPE_EMS_YU_TU = 31;

    public const TYPE_EMS_BEEGO = 32; //EMS - BeegoPlus

    public const TYPE_AO_HUA = 33; // 澳华 和乐天格式一样

    public const TYPE_CBP = 34; // CBP 小包系统

    public const TYPE_AUSTWAY_CARGO = 35; // AUSTWAY_CARGO

    public const TYPE_XZH_TMS = 36; // 新智慧TMS

    public const TYPE_K5 = 37; // K5

    public const TYPE_HONG_LI = 38; //HONG LI

    public const TYPE_RUI_YUN = 39; // rui-y.com

    public const TYPE_HVLV = 40; // rui-y.com

    public const TYPE_TOUR_BELL = 41; // http://admin.logistics.tourbell.cn/

    public const TYPE_JFP = 42; // JFP

    public const TYPE_FBA_DIDI = 43; // FBA DIDI

    public const TYPE_BEST_CSA = 44; //百世快递-CSA

    public const TYPE_T6 = 45; // T6

    public const TYPE_JIN_BANG = 46; // 金邦 速递管家 和澳华、乐天格式一样

    public const TYPE_K5_STAR = 47; // K5 星途云

    public const TYPE_K5_YUN_TU = 48; // K5 云图

    public const TYPE_K5_HENG_AO = 49; // K5 恒澳

    public const TYPE_SLT = 50; // 丝路通

    public const TYPE_YEN = 52; // https://yen.itdida.com

    public const TYPE_JT_EXPRESS_NYT = 53; // JT_EXPRESS_NYT

    public const TYPE_K5_HH = 54; // K5 憨憨集运

    public const TYPE_SHENG_YUN = 55; // 昇云系统

    public const TYPE_YFA = 56; // https://yfa.itdida.com

    public const TYPE_YUN_TU_LOGISTICS = 57; // 云途物流（老）

    public const TYPE_EMS_YI_CHANG = 58; // 邮政 一畅科技

    public const TYPE_KART_VN = 59; // KART-VN 先领

    public const TYPE_BA_SHI = 60; // 巴适对接

    public const TYPE_SC_LOGISTICS = 61; // 橙帆

    public const TYPE_IT_DIDA = 62; // 易抵达

    public const TYPE_4PX = 63; // 递四方

    public const TYPE_YANWEN = 64; // 燕文

    public const TYPE_UBI = 65; // UBI

    public const TYPE_JIEPUSI = 66; // 捷普思

    public const TYPE_YUNSUDI = 67; // 云速递

    public const TYPE_QUWULIU = 68; // 趣物流

    public const TYPE_CNE = 69; // CNE

    public const TYPE_HH = 70; // 华翰

    public const TYPE_YUN_TU_NEW_LOGISTICS = 71; // 云途物流（新）

    public const TYPE_JIA_YOU_QUAN = 72; // 佳邮（全程）

    public const TYPE_JIA_YOU_WEI = 73; // 佳邮（尾程）

    public const TYPE_WAN_BANG = 74; // 万邦速达

    public const TYPE_WAN_BANG_NEW = 75; // 万邦速达（新）
    public const TYPE_SANA = 76; // SANA
    public const TYPE_HUA_HAI = 77; // 花海物流
    public const TYPE_HUA_LIAN_WAN_TONG = 78; // 华联万通
    public const TYPE_JI_SU_GUO_JI = 79; // 急速国际（新）
    public const TYPE_QUAN_QIU_TONG = 80; // 全酋通物流
    public const TYPE_RONG_DING = 81; // 容鼎
    public const TYPE_SHAN_DIAN_HOU = 82; // 闪电猴
    public const TYPE_SHANG_HAI_JU_XI = 83; // 上海驹隙国际物流有限公司
    public const TYPE_SHANG_HAI_SHOU_WU = 84; // 上海守务国际物流有限公司
    public const TYPE_SHEN_ZHEN_YOU_SHI_DA = 85; // 深圳市优时达供应链有限公司
    public const TYPE_SHEN_ZHEN_ZHI_HE_MAI_LIAN = 86; // 深圳智禾迈联信息技术有限公司
    public const TYPE_SHUN_FENG_IBS = 87; // 顺丰国际-IBS
    public const TYPE_SHUN_FENG_KTS = 88; // 顺丰国际KTS
    public const TYPE_SHUN_FENG = 89; // 顺丰国内物流
    public const TYPE_SHUN_YOU = 90; // 顺友物流（新）
    public const TYPE_TONG_YOU = 91; // 通邮
    public const TYPE_WAN_GUO_YOU_LIAN = 92; // 万国优联
    public const TYPE_YI_HAO = 93; // 壹号
    public const TYPE_KUA_YUE = 94; // 跨越速运物流
}
