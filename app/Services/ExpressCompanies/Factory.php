<?php

namespace App\Services\ExpressCompanies;

use App\Lib\Code;
use App\Models\CompanyExpressModel;
use App\Services\ExpressCompanies\CNE\CNEService;
use App\Services\ExpressCompanies\FeiTe\FeiTeService;
use App\Services\ExpressCompanies\HuaHan\HuaHanService;
use App\Services\ExpressCompanies\HuaLei\HuaLei;
use App\Services\ExpressCompanies\JiaYou\JiaYouQService;
use App\Services\ExpressCompanies\JiaYou\JiaYouWService;
use App\Services\ExpressCompanies\JiePuSi\JiePuSiService;
use App\Services\ExpressCompanies\Quwuliu\QuwuliuService;
use App\Services\ExpressCompanies\HaiOu\HaiOuService;
use App\Services\ExpressCompanies\SunYou\SunYouService;
use App\Services\ExpressCompanies\UBI\UBIService;
use App\Services\ExpressCompanies\YanWen\YanWenService;
use App\Services\ExpressCompanies\DiSiFang\DiSiFangService;
use App\Services\ExpressCompanies\Itdida\ItdidaService;
use App\Services\ExpressCompanies\TaiJia\TaiJiaService;
use App\Services\ExpressCompanies\YiDa\YiDaService;
use App\Services\ExpressCompanies\YunSuDi\YunSuDiService;
use App\Services\ExpressCompanies\Yuntu\YuntuService;
use App\Services\ExpressCompanies\Yuntu\YuntuNewService;
use App\Services\ExpressCompanies\WanBang\WanBangService;
use App\Services\ExpressCompanies\WanBang\WanBangNewService;
use Exception;
use App\Exceptions\AccidentException;

class Factory
{
    private static array $providers = [
        CompanyExpressModel::CODE_YUNTU    => YuntuService::class,  // 云途物流(老）
        CompanyExpressModel::CODE_YUNTU_NEW=> YuntuNewService::class,// 云途物流(新）
        CompanyExpressModel::CODE_YIDIDA   => ItdidaService::class, // 易抵达物流
        CompanyExpressModel::CODE_DISIFANG => DiSiFangService::class, // 递四方
        CompanyExpressModel::CODE_YANWEN   => YanWenService::class, // 燕文
        CompanyExpressModel::CODE_UBI      => UBIService::class, // UBI
        CompanyExpressModel::CODE_JIEPUSI  => JiePuSiService::class, // 捷普思
        CompanyExpressModel::CODE_YUNSUDI  => YunSuDiService::class, // 云速递
        CompanyExpressModel::CODE_QUWULIU  => QuwuliuService::class, // 趣物流
        CompanyExpressModel::CODE_CNE      => CNEService::class, // cne
        CompanyExpressModel::CODE_HUALEI   => HuaLei::class, // 华磊
        CompanyExpressModel::CODE_HUAHAN   => HuaHanService::class, // 华翰
        CompanyExpressModel::CODE_YIDA     => YiDaService::class, // 义达
        CompanyExpressModel::CODE_FEITE    => FeiTeService::class, // 飞特
        CompanyExpressModel::CODE_SUNYOU   => SunYouService::class, // 顺友
        CompanyExpressModel::CODE_HAIOU    => HaiOuService::class, // 海鸥集运
        CompanyExpressModel::CODE_TAIJIA   => TaiJiaService::class, // 泰嘉物流
        CompanyExpressModel::CODE_JIAYOU_Q => JiaYouQService::class, // 佳邮（全程）
        CompanyExpressModel::CODE_JIAYOU_W => JiaYouWService::class, // 佳邮（尾程）
        CompanyExpressModel::CODE_WANBANG  => WanBangService::class, // 万邦速达
        CompanyExpressModel::CODE_WANBANG_NEW  => WanBangNewService::class, // 万邦速达
    ];

    /**
     * 根据传的物流商，创建对应物流商的实例
     * @param $platform string
     * @return mixed
     * @throws Exception
     */
    public static function create(string $platform): mixed
    {
        $platform = strtolower($platform);
        if (!isset(self::$providers[$platform])) {
            throw new AccidentException('操作失败，未对接该物流平台或物流平台不存在', Code::OPERATE_FAIL);
        }

        return new self::$providers[$platform];
    }
}
