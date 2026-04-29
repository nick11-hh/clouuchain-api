<?php

namespace App\Console\Commands;

use App\Models\OrderDockingRecordModel;
use App\Models\CompanyExpressModel;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class ExpressCompanies extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:update-express-companies {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新快递公司';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // type 值见 OrderDockingRecordModel 中定义，如果没定义可自行定义
        $express_companies = [
            [
                'name' => '云途(老）',
                'type' => OrderDockingRecordModel::TYPE_YUN_TU_LOGISTICS,
                'info' => [
                    'url' => '请求url',
                    'username' => '客户编号',
                    'apiSecret' => 'ApiSecret'
                ],
                'code' => 'yuntu'
            ],
            [
                'name' => '易抵达',
                'type' => 62,
                'info' => [
                    'url' => '请求url',
                    'username' => 'appkey',
                    'password' => '密钥'
                ],
                'code' => 'itdida'
            ],
            [
                'name' => '递四方（新）',
                'type' => OrderDockingRecordModel::TYPE_4PX,
                'info' => [
                    'url' => '请求url',
                    'appKey' => 'AppKey',
                    'appSecret' => 'AppSecret'
                ],
                'code' => 'disifang'
            ],
            [
                'name' => '燕文物流(新）',
                'type' => OrderDockingRecordModel::TYPE_YANWEN,
                'info' => [
                    'url' => '请求url',
                    'user_id' => '账号',
                    'api_token' => '秘钥',
                    'track_url' => '物流轨迹查询url',
                    'authorization' => '物流轨迹查询授权码'
                ],
                'code' => 'yanwen'
            ],
            [
                'name' => 'ubi新',
                'type' => OrderDockingRecordModel::TYPE_UBI,
                'info' => [
                    'url' => '请求url',
                    'token' => 'Token',
                    'key' => 'Key',
                ],
                'code' => 'ubi'
            ],
            [
                'name' => '捷普思',
                'type' => OrderDockingRecordModel::TYPE_JIEPUSI,
                'info' => [
                    'url' => '请求url',
                    'token' => 'appToken',
                    'key' => 'appKey',
                ],
                'code' => 'jiepusi'
            ],
            [
                'name' => '云速递',
                'type' => OrderDockingRecordModel::TYPE_YUNSUDI,
                'info' => [
                    'url' => '请求url',
                    'token' => 'appToken',
                    'key' => 'appKey',
                ],
                'code' => 'yunsudi'
            ],
            [
                'name' => '趣物流',
                'type' => OrderDockingRecordModel::TYPE_QUWULIU,
                'info' => [
                    'url' => '请求url',
                    'token' => 'appToken',
                ],
                'code' => 'quwuliu'
            ],
            [
                'name' => 'CNE',
                'type' => OrderDockingRecordModel::TYPE_CNE,
                'info' => [
                    'url' => '请求url',
                    'token' => 'appToken',
                    'icid' => 'IcID',
                ],
                'code' => 'cne'
            ],
            [
                'name' => '华磊',
                'type' => OrderDockingRecordModel::TYPE_HUA_LEI,
                'info' => [
                    'url' => '请求url',
                    'label_url' => '标签url',
                    'username' => 'username',
                    'password' => 'password',
                ],
                'code' => 'hua_lei'
            ],
            [
                'name' => '华翰物流',
                'type' => OrderDockingRecordModel::TYPE_HH,
                'info' => [
                    'url' => '请求url',
                    'app_token' => 'API账号',
                    'app_key' => 'API密码',
                ],
                'code' => 'hh'
            ],
            [
                'name' => '云途(新）',
                'type' => OrderDockingRecordModel::TYPE_YUN_TU_NEW_LOGISTICS,
                'info' => [
                    'url' => '请求url',
                    'username' => '客户编号',
                    'apiSecret' => 'ApiSecret'
                ],
                'code' => 'yuntu_new'
            ],
            [
                'name' => '佳邮(全程)',
                'type' => OrderDockingRecordModel::TYPE_JIA_YOU_QUAN,
                'info' => [
                    'url' => '请求url',
                    'code' => 'API账号',
                    'api_key' => '授权码',
                ],
                'code' => 'jiayou_q'
            ],
            [
                'name' => '佳邮(尾程)',
                'type' => OrderDockingRecordModel::TYPE_JIA_YOU_WEI,
                'info' => [
                    'url' => '请求url',
                    'code' => 'API账号',
                    'api_key' => '授权码',
                ],
                'code' => 'jiayou_w'
            ],
            [
                'name' => '万邦速达',
                'type' => OrderDockingRecordModel::TYPE_WAN_BANG,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'wanbang'
            ],
            [
                'name' => '万邦速达(新）',
                'type' => OrderDockingRecordModel::TYPE_WAN_BANG_NEW,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'wanbang_new'
            ],
            [
                'name' => 'SANA',
                'type' => OrderDockingRecordModel::TYPE_SANA,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'sana'
            ],
            [
                'name' => '花海物流',
                'type' => OrderDockingRecordModel::TYPE_HUA_HAI,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'hua_hai'
            ],
            [
                'name' => '华联万通',
                'type' => OrderDockingRecordModel::TYPE_HUA_LIAN_WAN_TONG,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'hua_lian'
            ],
            [
                'name' => '急速国际（新）',
                'type' => OrderDockingRecordModel::TYPE_JI_SU_GUO_JI,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'ji_su'
            ],
            [
                'name' => '全酋通物流',
                'type' => OrderDockingRecordModel::TYPE_QUAN_QIU_TONG,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'quan_qiu_tong'
            ],
            [
                'name' => '容鼎',
                'type' => OrderDockingRecordModel::TYPE_RONG_DING,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'rong_ding'
            ],
            [
                'name' => '闪电猴',
                'type' => OrderDockingRecordModel::TYPE_SHAN_DIAN_HOU,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'shan_dian_hou'
            ],
            [
                'name' => '上海驹隙国际物流有限公司',
                'type' => OrderDockingRecordModel::TYPE_SHANG_HAI_JU_XI,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'ju_xi'
            ],
            [
                'name' => '上海守务国际物流有限公司',
                'type' => OrderDockingRecordModel::TYPE_SHANG_HAI_SHOU_WU,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'shou_wu'
            ],
            [
                'name' => '深圳市优时达供应链有限公司',
                'type' => OrderDockingRecordModel::TYPE_SHEN_ZHEN_YOU_SHI_DA,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'you_shi_da'
            ],
            [
                'name' => '深圳智禾迈联信息技术有限公司',
                'type' => OrderDockingRecordModel::TYPE_SHEN_ZHEN_ZHI_HE_MAI_LIAN,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'zhi_he'
            ],
            [
                'name' => '顺丰国际-IBS',
                'type' => OrderDockingRecordModel::TYPE_SHUN_FENG_IBS,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'ibs'
            ],
            [
                'name' => '顺丰国际KTS',
                'type' => OrderDockingRecordModel::TYPE_SHUN_FENG_KTS,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'kts'
            ],
            [
                'name' => '顺丰国内物流',
                'type' => OrderDockingRecordModel::TYPE_SHUN_FENG,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'shun_feng'
            ],
            [
                'name' => '顺友物流（新）',
                'type' => OrderDockingRecordModel::TYPE_SHUN_YOU,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'shun_you'
            ],
            [
                'name' => '通邮',
                'type' => OrderDockingRecordModel::TYPE_TONG_YOU,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'tong_you'
            ],
            [
                'name' => '万国优联',
                'type' => OrderDockingRecordModel::TYPE_WAN_GUO_YOU_LIAN,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'wan_guo'
            ],
            [
                'name' => '壹号',
                'type' => OrderDockingRecordModel::TYPE_YI_HAO,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'yi_hao'
            ],
            [
                'name' => '跨越速运物流',
                'type' => OrderDockingRecordModel::TYPE_KUA_YUE,
                'info' => [
                    'url' => '请求url',
                    'account' => '客户代码',
                    'token' => '开发者令牌',
                    'warehouse_code' => '仓库代码',
                ],
                'code' => 'kua_yue'
            ],
        ];

        collect($express_companies)->each(function($item) {
            $company = CompanyExpressModel::where('type', $item['type'])->first();

            if($company) {
                CompanyExpressModel::where('id', $company->id)->update($item);
            } else {
                CompanyExpressModel::create($item);
            }
        });

        $this->info('物流公司更新成功！');
    }
}
