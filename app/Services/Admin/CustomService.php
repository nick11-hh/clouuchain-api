<?php

namespace App\Services\Admin;

use App\Events\ClientCustomRegister;
use App\Jobs\BrevoEmailJob;
use App\Jobs\Export\CustomExport;
use App\Lib\Code;
use App\Models\AssignDataPermission;
use App\Models\BalanceRecord;
use App\Models\CreditCardTypes;
use App\Models\Custom;
use App\Models\CustomBalance;
use App\Models\CustomConfig;
use App\Models\CustomsQuoteConfig;
use App\Models\ExcelExport;
use App\Models\OauthClients;
use App\Models\ThirdPartySystemConfigModel;
use App\Models\User;
use App\Models\Admin;
use App\Models\AdminOperationLog;
use App\Models\CustomInvoiceAddressModel;
use App\Services\Base\BalanceService;
use App\Services\Base\PermissionBaseService;
use App\Services\Client\FortySeasService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use App\Services\PaymentPlatform\FortySeas\RequestApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Exceptions\AccidentException;
use function Symfony\Component\String\u;

class CustomService extends BaseService
{
    public $filterRules = [
        'custom_name,custom_phone,custom_email' => ['like', 'search'],
        'id' => ['=', 'id'],
        'status' => ['=', 'status'],
        'group_id' => ['=', 'group_id'],
        'invite_id' => ['=', 'invite_id'],
        'customer_number' => ['like', 'customer_number'],
    ];
    private $fortySeasService;
    private $requestApi;

    public function __construct()
    {
        $this->model = new Custom();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
        $this->fortySeasService = new FortySeasService();
        $this->requestApi = new RequestApi();
    }

    public function index()
    {
        //默认查询所有客户
//        if (!isset($this->formData['size'])) $this->formData['size'] = 1000;

        $this->query->with(['customGroup', 'inviter', 'mainUser', 'balance', 'config', 'staff', 'customsQuoteConfig', 'assignDataPermissions.admin:id,name']);

        if (isset($this->formData['staff_id'])) {
            if ($this->formData['staff_id'] == 0) {
                $this->query->whereDoesntHave('assignDataPermissions');
            } else {
                $this->query->whereHas('assignDataPermissions', function ($query) {
                    $query->where('admin_id', $this->formData['staff_id']);
                });
            }
        }
        if (isset($this->formData['quota'])) {
            switch ($this->formData['quota']) {
                case 1:
                    $this->query->whereHas('balance', function ($query) {
                        $query->where('balance', '>', 0);
                    });
                    break;
                case 2:
                    $this->query->where('credit_line', '>', 0);
                    break;
                case 3:
                    $this->query->where('frozen_limit', '>', 0);
                    break;
            }
        }
        if (isset($this->formData['is_count'])) {
            $this->query->where('is_count', $this->formData['is_count']);
        }

        if (!empty($this->formData['no_assign'])) {
            $this->query->withoutGlobalScope('customer_filter')->whereDoesntHave('assignDataPermissions');
        }

        $this->setFilterByIndex();

        $this->query->latest();

        return parent::index();
    }

    public function show($id)
    {
        return $this->model->findOrFail($id);
    }

    public function simple()
    {
        $this->query->latest()->select('id', 'custom_name', 'customer_number');
        return parent::index();
    }

    /**
     * @param $params
     * @return mixed
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($params) {
            $user = User::where('username', $params['username'])
                ->orWhere('email', $params['email'])
                ->orWhere('phone', $params['phone'])->first();
            if (!empty($user)) {
                if ($user->username === $params['username']) throw new AccidentException('用户名已存在', Code::OPERATE_FAIL);
                if ($user->email === $params['email']) throw new AccidentException('该邮箱已注册', Code::OPERATE_FAIL);
                if ($user->username === $params['phone']) throw new AccidentException('该手机号码已注册', Code::OPERATE_FAIL);
            }
            $data = Custom::init($params, null);

            $custom = Custom::create($data);

            $params['custom_id'] = $custom->id;
            $params['is_main'] = 1;

            $userData = User::init($params);
            $user = User::create($userData);
            $custom->main_user_id = $user->id;
            $custom->save();

            $product_quote_default_profit_rate = $params['product_quote_default_profit_rate'];
            if (!$product_quote_default_profit_rate) {
                $product_quote_default_profit_rate = 20;
            }
            $product_quote_review_profit_rate = $product_quote_default_profit_rate;

            $freight_quote_default_profit_rate = $params['freight_quote_default_profit_rate'];
            if (!$freight_quote_default_profit_rate) {
                $freight_quote_default_profit_rate = 20;
            }
            $freight_quote_review_profit_rate = $freight_quote_default_profit_rate;
            //保存报价配置
            $custom->customsQuoteConfig()->create([
                'product_quote_default_profit_rate' => $product_quote_default_profit_rate,
                'product_quote_review_profit_rate' => $product_quote_review_profit_rate,
                'product_quote_default_fixed_amount' => $params['product_quote_default_fixed_amount'] ?? null,
                'freight_quote_default_profit_rate' => $freight_quote_default_profit_rate,
                'freight_quote_review_profit_rate' => $freight_quote_review_profit_rate,
                'freight_quote_default_fixed_amount' => $params['freight_quote_default_fixed_amount'] ?? null,
                'admin_id' => getAdminId(),
            ]);

            //再把公司名、电话、邮箱保存进发票地址表
            CustomInvoiceAddressModel::updateOrCreate(
                ['customer_id' => $custom->id],
                [
                    'name' => $params['company_name'] ?? $params['username'],
                    'phone_area_code' => $params['phone_area_code'] ?? '',
                    'phone_number' => $params['phone'] ?? '',
                    'email' => $params['email'],
                ]
            );

            //将公司营业执照等内容保存进授权表
            $OauthModel = new OauthClients();
            $auth = $OauthModel::where('user_id', $custom->id)->first();
            if(empty($auth)) {
                $authData = [
                    'user_id' => $custom->id,
                    'name' => '',
                    'redirect'=> '',
                    'personal_access_client' => 0,
                    'password_client' => 0,
                    'revoked' => 0,
                    'license_url' => $params['business_license'] ?? ''
                ];
                $OauthModel::create($authData);
            }

            event(new ClientCustomRegister($user));
            return true;
        });
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        validator($params, $this->updateRule())->validate();
        $custom = $this->model::query()->findOrFail($id);

        $custom->custom_name = $params['custom_name'];
        $custom->custom_email = $params['custom_email'];
        $custom->custom_phone = $params['custom_phone'] ?? '';
        $custom->phone_area_code = $params['phone_area_code'] ?? '';
        $custom->group_id = $params['group_id'];
        $custom->commission_rate = $params['commission_rate'] ?: 0;
        $custom->commission_amount = $params['commission_amount'] ?: 0;
        $custom->default_language = $params['default_language'] ?? 'zh_CN';
        $custom->remark = $params['remark'] ?? '';
        $custom->customer_number = $params['customer_number'] ?? '';
        $custom->goods_once_price = $params['goods_once_price'] ?? 0;
        $custom->is_count = $params['is_count'];
        if (!isset($params['product_quote_default_profit_rate'])) {
            $params['product_quote_default_profit_rate'] = 20;
        }
        if (!isset($params['freight_quote_default_profit_rate'])) {
            $params['freight_quote_default_profit_rate'] = 20;
        }

        $opLogData = AdminOperationLog::make($custom);

        $custom->save();

        if ($opLogData) {
            AdminOperationLog::insert($opLogData);
        }

        $customsQuoteConfig = CustomsQuoteConfig::where('customer_id', $custom->id)->first();
        if (!in_array(getAdminId(), [1, 2, 5, 6, 15, 47, 63])) {
            $product_review_rate = $params['product_quote_default_profit_rate'];
            $freight_review_rate = $params['freight_quote_default_profit_rate'];
            if ($product_review_rate < 20 && $product_review_rate > 10 && in_array(getAdminId(), [2, 5, 6, 15, 47])) {
                $params['product_quote_default_profit_rate'] = $customsQuoteConfig->product_quote_default_profit_rate;
            } elseif ($product_review_rate < 10 && in_array(getAdminId(), [1, 63])) {
                $params['product_quote_default_profit_rate'] = $customsQuoteConfig->product_quote_default_profit_rate;
            }
            if ($freight_review_rate < 20 && $freight_review_rate > 10 && in_array(getAdminId(), [2, 5, 6, 15, 47])) {
                $params['freight_quote_default_profit_rate'] = $customsQuoteConfig->freight_quote_default_profit_rate;
            } elseif ($freight_review_rate < 10 && in_array(getAdminId(), [1, 63])) {
                $params['freight_quote_default_profit_rate'] = $customsQuoteConfig->freight_quote_default_profit_rate;
            }
        } else {
            $product_review_rate = $params['product_quote_review_profit_rate'] ?? $params['product_quote_default_profit_rate'];
            $freight_review_rate = $params['freight_quote_review_profit_rate'] ?? $params['freight_quote_default_profit_rate'];
            $params['product_quote_default_profit_rate'] = $product_review_rate;
            $params['freight_quote_default_profit_rate'] = $freight_review_rate;
        }
        if (!$customsQuoteConfig) {

            $customsQuoteConfig = CustomsQuoteConfig::create([
                'customer_id' => $custom->id,
                'product_quote_default_profit_rate' => $params['product_quote_default_profit_rate'],
                'product_quote_review_profit_rate' => $product_review_rate,
                'product_quote_default_fixed_amount' => $params['product_quote_default_fixed_amount'] ?? null,
                'freight_quote_default_profit_rate' => $params['freight_quote_default_profit_rate'],
                'freight_quote_review_profit_rate' => $freight_review_rate,
                'freight_quote_default_fixed_amount' => $params['freight_quote_default_fixed_amount'] ?? null,
                'admin_id' => getAdminId(),
            ]);

        } else {

            $customsQuoteConfig->product_quote_default_profit_rate = $params['product_quote_default_profit_rate'];
            $customsQuoteConfig->product_quote_review_profit_rate = $product_review_rate;
            $customsQuoteConfig->product_quote_default_fixed_amount = $params['product_quote_default_fixed_amount'] ?? null;
            $customsQuoteConfig->freight_quote_default_profit_rate = $params['freight_quote_default_profit_rate'];
            $customsQuoteConfig->freight_quote_review_profit_rate = $freight_review_rate;
            $customsQuoteConfig->freight_quote_default_fixed_amount = $params['freight_quote_default_fixed_amount'] ?? null;

            $opLogData2 = AdminOperationLog::make($customsQuoteConfig, optType: AdminOperationLog::OPT_TYPE_8);

            $customsQuoteConfig->save();

            if ($opLogData2) {
                AdminOperationLog::insert($opLogData2);
            }
        }


        if (isset($params['is_auto_payment'])) {
            $this->updateAutoPayment(['ids' => [$custom->id], 'is_auto_payment' => $params['is_auto_payment']]);
        }

        //再把公司名、电话、邮箱保存进发票地址表
        CustomInvoiceAddressModel::updateOrCreate(
            ['customer_id' => $custom->id],
            [
                'phone_area_code' => $params['phone_area_code'] ?? '',
                'phone_number' => $params['custom_phone'] ?? '',
                'email' => $params['custom_email'],
            ]
        );

        //推送brevo邮件营销
        $brevo = ThirdPartySystemConfigModel::getBrevoConfig();
        if ($custom->custom_email && $brevo) {
            $data = [
                'type' => 'createContact',
                'custom_ids' => [$id],
            ];

            dispatch(new BrevoEmailJob($data));
        }

        //将公司营业执照等内容保存进授权表
        $OauthModel = new OauthClients();
        $auth = $OauthModel::where('user_id', $custom->id)->first();
        if(empty($auth)) {
            $authData = [
                'user_id' => $custom->id,
                'name' => '',
                'redirect'=> '',
                'personal_access_client' => 0,
                'password_client' => 0,
                'revoked' => 0,
                'license_url' => $params['business_license'] ?? ''
            ];
            $OauthModel::create($authData);
        }else{
            $auth->license_url = $params['business_license'] ?? '';
            $auth->save();
        }

        return true;
    }

    public function updateStatus($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int'
        ])->validate();

        $customs = Custom::whereIn('id', $params['ids'])
            ->get();
        if ($customs->isNotEmpty()) {

            $optType = $params['status'] == Custom::STATUS_ENABLE ? AdminOperationLog::OPT_TYPE_5 : AdminOperationLog::OPT_TYPE_4;

            foreach ($customs as $key => $custom) {

                $opLogData = AdminOperationLog::make($custom, optType: $optType);

                if ($opLogData) {
                    AdminOperationLog::insert($opLogData);
                }
            }
        }

        return Custom::whereIn('id', $params['ids'])
            ->update(['status' => $params['status']]);
    }

    public function deletes($params)
    {
        $ids = $params['ids'];
        if (empty($ids)) throw new AccidentException('请选择需要删除的用户', Code::OPERATE_FAIL);

        return DB::transaction(function () use ($params) {
            User::query()->whereIn('custom_id', $params['ids'])->delete();

            Custom::query()->whereIn('id', $params['ids'])->delete();

            return true;
        });
    }

    /**
     * 调整信用额度
     */
    public function updateCreditLine($params)
    {
        validator($this->formData, [
            'id' => 'required|int',
            'type' => 'required|int',
            'original_credit_line' => 'required|numeric',//原始信用额度
            'residual_credit' => 'required|numeric',//剩余信用额度
            'adjusted_limit' => 'required|numeric',//调整额度
            'credit_line' => 'required|numeric',//调整后信用额度
        ])->validate();

        $formData = $this->formData;
        return DB::transaction(function () use ($formData) {
            $customInfo = $this->query->with(['balance'])->findOrFail($formData['id']);
            $customInfo->type = $formData['type'];
            $customInfo->adjusted_limit = $formData['adjusted_limit'];
            // 调整前余额
            $original_balance = $customInfo->balance['balance'] / 100;
            $customInfo->original_balance = $original_balance;

            if ($formData['type'] === 2 && $formData['adjusted_limit'] > $original_balance) {
                throw new AccidentException('调整降低信用额度不能大于账户余额', Code::OPERATE_FAIL);
            }
            $note = isset($formData['remark']) ? $formData['remark'] . '，' : '';

            $balanceService = new BalanceService($customInfo->id);
            // 调整后余额
            if ($formData['type'] === 1) {
                $remark = $note . '增加信用额度';
                $balanceService->setRemark($remark)
                    ->increase(
                        $customInfo->adjusted_limit,
                        BalanceRecord::SOURCE_ADJUST_CREDIT_LIMIT,
                        '',
                        ['operate_admin_id' => getAdminId()]
                    );
                $balance = $original_balance + $formData['adjusted_limit'];
            } else {
                $remark = $note . '减少信用额度';
                $balanceService->setRemark($remark)
                    ->deduction(
                        $customInfo->adjusted_limit,
                        BalanceRecord::SOURCE_ADJUST_CREDIT_LIMIT,
                        '',
                        ['operate_admin_id' => getAdminId()]
                    );
                $balance = $original_balance - $formData['adjusted_limit'];
            }
            // 调整后信用额度
            $customInfo->credit_line = $formData['credit_line'];
            // 调整后账户余额
            $customInfo->balance->balance = $balance * 100;

            $opLogData = AdminOperationLog::make($customInfo, optType: AdminOperationLog::OPT_TYPE_2);

            if ($opLogData) {
                AdminOperationLog::insert($opLogData);
            }

            unset($customInfo->type, $customInfo->adjusted_limit, $customInfo->original_balance);

            $customInfo->save();

            $user = User::query()->where('custom_id', $formData['id'])->first();
            if (!$user) {
                throw new AccidentException('用户不存在', Code::OPERATE_FAIL);
            }

            $CreditCardType =  CreditCardTypes::where('name',CreditCardTypes::TYPE_40SEAS)->where('status',CreditCardTypes::STATUS_NORMAL)->first();
            if (!empty($CreditCardType['client_id']) || !empty($CreditCardType['client_secret']) || !empty($CreditCardType['webhook_secret'])){
                if (empty($user['buyer_id'])) {
                    $buyerId = $this->fortySeasService->createBuyer($user);
                    if (!$buyerId) {
                        throw new AccidentException('创建用户40Seas买家失败', Code::OPERATE_FAIL);
                    }
                }else{
                    $buyerId = $user['buyer_id'];
                }

                if (empty($user['creditline_id'])){
                    $resultCreateCreditLine = $this->requestApi->createCreditLine($buyerId, $formData['credit_line']);
                    if (!$resultCreateCreditLine) {
                        throw new AccidentException('创建用户信用额度失败', Code::OPERATE_FAIL);
                    }
                    User::where('id', $user['id'])->update(['creditline_id' => $resultCreateCreditLine['id']]);
                    $user['creditline_id'] = $resultCreateCreditLine['id'];
                }
                $this->requestApi->updateCreditLine($user['creditline_id'], $buyerId,'USD',$formData['credit_line']);
            }
            return true;
        });
    }

    /**
     * 调整冻结额度
     */
    public function updateFrozenLimit($params)
    {
        validator($this->formData, [
            'id' => 'required|int',
            'type' => 'required|int',
            'original_frozen_limit' => 'required|numeric',//原始冻结额度
            'adjusted_limit' => 'required|numeric',//调整额度
            'frozen_limit' => 'required|numeric',//调整后冻结额度
        ])->validate();

        $formData = $this->formData;
        return DB::transaction(function () use ($formData) {
            $customInfo = $this->query->with(['balance'])->findOrFail($formData['id']);
            $customInfo->type = $formData['type'];
            $customInfo->adjusted_limit = $formData['adjusted_limit'];
            // 调整前余额
            $original_balance = $customInfo->balance['balance'] / 100;
            $customInfo->original_balance = $original_balance;

            if ($formData['type'] === 1 && $formData['adjusted_limit'] > $original_balance) {
                throw new AccidentException('调整冻结额度不能大于账户余额', Code::OPERATE_FAIL);
            }
            if ($formData['type'] === 2 && $formData['adjusted_limit'] > $formData['original_frozen_limit']) {
                throw new AccidentException('解冻额度不能大于冻结额度', Code::OPERATE_FAIL);
            }
            $note = isset($formData['remark']) ? $formData['remark'] . '，' : '';

            $balanceService = new BalanceService($customInfo->id);
            // 调整后余额
            if ($formData['type'] === 1) {
                $remark = $note . '增加冻结额度';
                $balanceService->setRemark($remark)->deduction(
                    $customInfo->adjusted_limit,
                    BalanceRecord::SOURCE_ADJUST_FROZEN_LIMIT,
                    '',
                    ['operate_admin_id' => getAdminId()]
                );
                $balance = $original_balance - $formData['adjusted_limit'];
                // 累计冻结金额
                $customInfo->cumulative_frozen += $formData['adjusted_limit'];
            } else {
                $remark = $note . '解冻冻结额度';
                $balanceService->setRemark($remark)->increase(
                    $customInfo->adjusted_limit,
                    BalanceRecord::SOURCE_ADJUST_FROZEN_LIMIT,
                    '',
                    ['operate_admin_id' => getAdminId()]
                );
                $balance = $original_balance + $formData['adjusted_limit'];
                // 累计解冻金额
                $customInfo->cumulative_unfrozen += $formData['adjusted_limit'];
            }
            // 调整后冻结额度
            $customInfo->frozen_limit = $formData['frozen_limit'];
            // 调整后账户余额
            $customInfo->balance->balance = $balance * 100;

            $opLogData = AdminOperationLog::make($customInfo, optType: AdminOperationLog::OPT_TYPE_9);

            if ($opLogData) {
                AdminOperationLog::insert($opLogData);
            }

            unset($customInfo->type, $customInfo->adjusted_limit, $customInfo->original_balance);

            $customInfo->save();

            return true;
        });

    }

    /**
     * 客户钱包详情
     */
    public function getWalletShow($id)
    {
        return $this->query->with(['customGroup', 'balance'])->findOrFail($id);
    }

    /**
     * 钱包金额统计
     */
    public function getWalletCount(): array
    {
        // 添加排除条件
        $notIn = Custom::query()->where('is_count', 2)->pluck('id')->toArray();
        $this->query->whereNotIn('id', $notIn);
        $balance = CustomBalance::query()->whereNotIn('custom_id', $notIn)->sum('balance');
        $credit_line = $this->query->sum('credit_line');
        $frozen_limit = $this->query->sum('frozen_limit');
        $cumulative_frozen = $this->query->sum('cumulative_frozen');
        $cumulative_unfrozen = $this->query->sum('cumulative_unfrozen');
        $cumulative_top_up = $this->query->sum('cumulative_top_up');
        $consume_amount = $this->query->sum('consume_amount');

        return [
            'balance' => round($balance / 100, 2),
            'credit_line' => $credit_line,
            'frozen_limit' => $frozen_limit,
            'cumulative_frozen' => $cumulative_frozen,
            'cumulative_unfrozen' => $cumulative_unfrozen,
            'cumulative_top_up' => $cumulative_top_up,
            'consume_amount' => $consume_amount,
        ];
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function updateAutoPayment($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'is_auto_payment' => 'required|int',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $customers = $this->model::query()->with('config')->whereIn('id', $params['ids'])->get();

            $customers->each(function ($customer) use ($params) {
                $data = [
                    'custom_id' => $customer->id,
                    'is_auto_payment' => $params['is_auto_payment'],
                ];

                if (empty($customer->config)) {
                    $data = CustomConfig::init($data);
                    CustomConfig::query()->create($data);
                } else {

                    //修改就保存日志
                    if ($customer->config->getOriginal('is_auto_payment') != $params['is_auto_payment']) {

                        $originalData = $customer->config->getOriginal('is_auto_payment');
                        $newData = $params['is_auto_payment'];

                        AdminOperationLog::create([
                            'type' => AdminOperationLog::TYPE_1,
                            'opt_type' => AdminOperationLog::OPT_TYPE_1,
                            'admin_id' => getAdminId(),
                            'custom_id' => $customer->id,
                            'description' => '自动支付由【' . Custom::$autoPaymentStatus[$originalData] . '】更新为【' . Custom::$autoPaymentStatus[$newData] . '】'
                        ]);
                    }

                    $customer->config->update($data);
                }
            });

            return true;
        });
    }

    /**
     * 分配员工
     * @return bool
     */
    public function assignStaff(): bool
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'staff_id' => 'required|int',
        ])->validate();

        $customers = Custom::query()->whereIn('id', $this->formData['ids'])->withoutGlobalScope('customer_filter')->get();
        if ($customers->isNotEmpty()) {
            $adminName = '无';
            if ($this->formData['staff_id'] > 0) {
                $adminName = Admin::query()->where('id', $this->formData['staff_id'])->value('name');
            }
            DB::transaction(function () use ($customers, $adminName) {
                foreach ($customers as $customer) {
                    // 分配权限
                    PermissionBaseService::assignDataPermission($this->formData['staff_id'], $customer->id, AssignDataPermission::CUSTOMER_PERMISSION);

                    $customer->assign_admin_name = $adminName;
                    $opLogData = AdminOperationLog::make($customer, optType: AdminOperationLog::OPT_TYPE_6);

                    if ($opLogData) {
                        AdminOperationLog::query()->insert($opLogData);
                    }
                }
            });

        }
        return true;
    }

    /**
     * 导出
     * @return bool
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 16:16
     */
    public function export(): bool
    {
//        $fileName = 'Custom_' . Carbon::now()->format('YmdHis') . '_' . Str::random(6) . '.xlsx';
//
//        $data = $this->getExportData();
//
//        /** @var $excelExport ExcelExport */
//        $excelExport = ExcelExport::query()->create([
//            'name' => $fileName,
//            'type' => ExcelExport::TYPE_CUSTOM,
//            'url' => '',
//        ]);
//
//        dispatch(new CustomExport($excelExport, $data));
        return true;
    }

    /**
     * 获取导出数据
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 17:18
     */
    public function getExportData(): array
    {
        $this->query->with([
            'customGroup',
            'inviter',
            'mainUser',
            'balance',
            'config',
            'staff'
        ]);

        $this->setFilter()->setOrderBy();

        $this->setFilterByIndex();

        $this->query->latest();

        $data = [];
        $this->query->chunk(100, function ($items) use (&$data) {
            foreach ($items as $item) {
                $balance = $item->balance->balance ?? '';

                $data[] = [
                    'id' => $item->id,
                    'custom_name' => $item->custom_name,
                    'customer_number' => $item->customer_number,
                    'custom_group' => $item->customGroup->group_name ?? '',
                    'mobile' => $item->custom_phone,
                    'email' => $item->custom_email,
                    'balance' => $balance ? bcdiv($balance, 100, 2) : '',
                    'credit_line' => $item->credit_line,
                    'residual_credit' => $item->residual_credit,
                    'consume_amount' => $item->consume_amount,
                    'commission_rate' => $item->commission_rate,
                    'commission_amount' => $item->commission_amount,
                    'invite_name' => $item->inviter->custom_name ?? '',
                    'last_login_time' => $item->mainUser->last_login_at ?? '',
                ];
            }
        });

        return $data;
    }

    /**
     * 设置首页搜索条件
     * @return void
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 16:47
     */
    public function setFilterByIndex()
    {
        if (isset($this->formData['search']) && $this->formData['search'] !== '') {
            $this->query->where('custom_name', 'like', '%' . $this->formData['search'] . '%')
                ->orWhere('custom_phone', 'like', '%' . $this->formData['search'] . '%')
                ->orWhere('custom_email', 'like', '%' . $this->formData['search'] . '%');
        }

        if (isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            switch ($this->formData['keyword_type']) {
                case 1:
                    $this->query->where('custom_name', 'like', '%' . $this->formData['keyword'] . '%');
                    break;
                case 2:
                    $this->query->where('custom_phone', 'like', '%' . $this->formData['keyword'] . '%');
                    break;
                case 3:
                    $this->query->where('custom_email', 'like', '%' . $this->formData['keyword'] . '%');
                    break;
            }
        }

//        $staff = auth()->guard('admin')->user();
//        if ($staff->group_id != 1) {
//            $this->query->where('staff_id', $staff->id);
//        }

    }

    /**
     * 获取客户端的邀请码
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/28 11:37
     */
    public function getInviteUrl()
    {
        $adminId = getAdminId();
        $admin = Admin::query()->findOrFail($adminId);
        $url = getClientDomain();
        $inviteCode = $admin->invite_code;
        return [
            'invite_link' => $url . '/login?admin_invite_code=' . $inviteCode,
        ];
    }

    /**
     * 客户端登录
     * @param array $params
     * @return array
     * @throws \Throwable
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/22 11:59
     */
    public function loginClient(array $params)
    {
        validator($params, [
            'main_user_id' => 'required|integer',
        ])->validate();

        $account = User::query()
            ->where('id', $params['main_user_id'])
            ->where('status', User::STATUS_ENABLE)
            ->first();

        throw_if(
            !$account,
            new AccidentException('账号不存在或已禁用', Code::OPERATE_FAIL)
        );

        $token = $this->clientGuard()->login($account);

        $account->last_login_at = now()->toDateTimeString();

        $opLogData = AdminOperationLog::make($account, optType: AdminOperationLog::OPT_TYPE_7);

        if ($opLogData) {
            AdminOperationLog::insert($opLogData);
        }

        $account->save();

        return [
            'id' => auth('client')->user()->id,
            'username' => auth('client')->user()->username,
            'email' => auth('client')->user()->email,
            'custom_id' => auth('client')->user()->custom_id,
            'avatar' => auth('client')->user()->avatar,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * 获取客户列表携带店铺
     * @param $params
     * @return mixed
     */
    public function getCustomWithShopList($params)
    {
        $customId = $params['custom_id'] ?? '';
        $size = $params['size'] ?? 10;

        $query = $this->model::query()->with(['shopList']);

        $query->when($customId, function ($query) use ($customId) {
            $query->where('id', $customId);
        });

        return $query->paginate($size);
    }

    /**
     * 客户端看守器
     * @return \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/22 11:55
     */
    protected function clientGuard()
    {
        return Auth::guard('client');
    }

    public function getQuoteConfig($id)
    {
        $customsQuoteConfig = CustomsQuoteConfig::where('customer_id', $id)->first();

        if ($customsQuoteConfig) {
            return $customsQuoteConfig->toArray();
        }

        return [];
    }


    public function rules()
    {
        return [
            'username' => 'required|string|between:2,30',
            'password' => 'required|string|between:6,20',
            'email' => 'required|email',
            'phone' => 'sometimes|nullable|string',
            'group_id' => 'required|int',
            'company_name' => 'required|string',
            'name' => 'sometimes|nullable|string',
            'default_language' => 'sometimes|nullable|string',
            'goods_once_price' => 'sometimes|nullable|string',
            'product_quote_default_profit_rate' => 'sometimes|nullable|numeric',
            'product_quote_default_fixed_amount' => 'sometimes|nullable|numeric',
            'freight_quote_default_profit_rate' => 'sometimes|nullable|numeric',
            'freight_quote_default_fixed_amount' => 'sometimes|nullable|numeric',
        ];
    }

    public function updateRule()
    {
        return [
            'custom_name' => 'required|string|between:2,30',
            'custom_email' => 'required|email',
            'custom_phone' => 'sometimes|nullable|string',
            'group_id' => 'required|int',
            'commission_amount' => 'sometimes|nullable|numeric|required_without:commission_rate',
            'commission_rate' => 'sometimes|nullable|int|between:0,100|required_without:commission_amount',
            'default_language' => 'sometimes|nullable|string',
            'product_quote_default_profit_rate' => 'sometimes|nullable|numeric',
            'product_quote_default_fixed_amount' => 'sometimes|nullable|numeric',
            'freight_quote_default_profit_rate' => 'sometimes|nullable|numeric',
            'freight_quote_default_fixed_amount' => 'sometimes|nullable|numeric',
        ];
    }

    /**
     * @throws AccidentException
     */
    public function OpenPlatformAuth($id)
    {

        $custom = (new OauthClients())::query()->where('user_id', $id)->first();
        if (empty($custom->license_url)) {
            throw new AccidentException('请联系客户上传营业执照', Code::OPERATE_FAIL);
        }

        if (empty($custom->secret) || empty($custom->name)) {
            $updateData = [
                'name'    => Str::random(10),
                'secret'  => Str::random(64),
            ];
            $custom->update($updateData);
            // 重新加载更新后的数据
            $custom->refresh();
        }

        return [
            'app_key'     => $custom->name,
            'app_secret'  => $custom->secret,
        ];

    }
}
