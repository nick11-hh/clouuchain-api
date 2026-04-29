<?php

namespace App\Services\Admin;


use App\Helper\CurrencyConverter;
use App\Jobs\Export\BalanceRecordExport;
use App\Jobs\Export\CreditCardRechargeRecordExport;
use App\Jobs\Export\OfflineRechargeRecordExport;
use App\Jobs\Export\OnlineRechargeRecordExport;
use App\Lib\Code;
use App\Models\BalanceRecord;
use App\Models\ChargePayMethod;
use App\Models\ClientGoods;
use App\Models\ClientGoodsSku;
use App\Models\CreditCardRechargeRecord;
use App\Models\DefaultRechargeAmount;
use App\Models\GoodsSku;
use App\Models\RechargeApply;
use App\Models\BalanceRecharge;
use App\Services\Base\BalanceService;
use App\Services\ThirdPart\HuaLei\Request;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Models\ExcelExport;
use Illuminate\Support\Str;
use App\Exceptions\AccidentException;
use Osiset\ShopifyApp\Storage\Models\Charge;

class RechargeApplyService extends BaseService
{
    public $filterRules = [
        'custom_id' => ['=', 'custom_id'],
        'created_at' => ['between', ['begin_date', 'end_date']],
        'custom:group_id' => ['=', 'group_id'],
        'custom:customer_number' => ['like', 'customer_number'],
        'check_status' => ['=', 'check_status'],
        'serial_no' => ['=', 'serial_no'],
        'status' => ['=', 'status'],
    ];

    private ClientGoodsSku $skuModel;

    public function __construct(private CreditCardRechargeRecordService $creditCardRechargeRecordService)
    {
        $this->model = new RechargeApply();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        //
        $this->query->with(['custom.customGroup', 'paymentType', 'revocationOperator', 'payMethod']);
        $rechargeAmount = isset($this->formData['recharge_amount']) ? floatval($this->formData['recharge_amount']) : 0;
        $rechargeAmount2 = isset($this->formData['recharge_amount2']) ? floatval($this->formData['recharge_amount2']) : 0;
        $comparisonOperators = $this->formData['comparison_operators'] ?? '';
        $this->query->when($comparisonOperators, function ($query) use ($comparisonOperators, $rechargeAmount, $rechargeAmount2) {

            if (in_array($comparisonOperators, ['=', '<>', '>', '>=', '<', '<=', 'between'])) {

                if ($comparisonOperators == 'between') {

                    return $query->whereBetween('apply_amount', [floatval($rechargeAmount * 100), floatval($rechargeAmount2 * 100)]);
                } else {

                    return $query->where('apply_amount', $comparisonOperators, floatval($rechargeAmount * 100));
                }
            }
        });
        $this->query->latest();
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['custom', 'paymentType', 'revocationOperator', 'payMethod'])->findOrFail($id);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $params['apply_amount'] = bcmul($params['apply_amount'], 100);
        $data = RechargeApply::init($params);
        $apply = $this->model->query()->create($data);
        if ($params['audit'] === 1) {
            $data['audit_status'] = RechargeApply::STATUS_AUDIT_SUCCESS;
            $data['confirm_amount'] = $apply->apply_amount / 100;
            $data['confirm_remark'] = '创建审核';
            $this->audit($apply->id, $data);
        }
        return $apply;
    }

    /** 充值申请审核
     * @param $id
     * @param $params
     * @return mixed
     */
    public function audit($id, $params)
    {
        validator($params, $this->auditRules())->validate();
        return DB::transaction(function () use ($id, $params) {
            $apply = RechargeApply::query()->findOrFail($id);
            if ($apply->status != RechargeApply::STATUS_DEFAULT) {
                throw new AccidentException('当前申请已审核', Code::OPERATE_FAIL);
            }
            $apply->status = $params['audit_status'];
            if ($apply->status == RechargeApply::STATUS_AUDIT_SUCCESS) { // 审核成功
                $apply->confirm_amount = bcmul($params['confirm_amount'], 100);
                $balanceService = new BalanceService($apply->custom_id);
                $record = $balanceService
                    ->setRemark($params['confirm_remark'] ?? '')
                    ->setRelationId($apply->id)
                    ->increase($params['confirm_amount'], BalanceRecord::SOURCE_RECHARGE, $apply->serial_no);

                $defaultRechargeAmountConfig = DefaultRechargeAmount::query()
                    ->where('amount', '<=', $params['confirm_amount'])
                    ->orderByDesc('amount')
                    ->first();
                if ($defaultRechargeAmountConfig && ($defaultRechargeAmountConfig->complimentary_amount > 0)) {
                    $balanceService
                        ->setRemark($params['confirm_remark'] ?? '')
                        ->setRelationId($apply->id)
                        ->increase($defaultRechargeAmountConfig->complimentary_amount, BalanceRecord::SOURCE_COMPLIMENTARY_RECHARGE, $apply->serial_no);
                }
                $apply->out_serial_no = $record->serial_no;
            }
            if (!empty($params['pay_method'])) $apply->pay_method = $params['pay_method'];
            $apply->confirm_operator = auth('admin')->user()->id ?? 0;
            $apply->confirm_images = $params['confirm_images'] ?? [];
            $apply->confirm_remark = $params['confirm_remark'] ?? '';
            $apply->save();
            return $apply;
        });
    }

    /**
     * 快速充值
     * @param $params
     * @return mixed
     */
    public function quickRecharge($params)
    {
        validator($params, $this->quickRechargeRules())->validate();

        return DB::transaction(function () use ($params) {
            $currency = $params['currency'] ?? 'USD';//支付币种
            $applyAmount = $params['pay_amount'];//申请金额 美元

            //支付金额 多币种
            if ($currency !== 'USD') {
                //将其他币种转换成人民币
                if ($currency !== 'CNY') {
                    $applyAmount = (new CurrencyConverter($currency))->convert($params['pay_amount']);
                }

                //人民币转美元
//                $applyAmount = (new CurrencyConverter())->reversedCurrenciesExchange($applyAmount);
            }

            $params['pay_amount'] = bcmul($params['pay_amount'], 100);//支付金额 转分
            $params['apply_amount'] = bcmul($applyAmount, 100);//申请金额 转美分
            $data = RechargeApply::init($params);

//            //审核通过数据
//            $data['confirm_amount'] = $data['apply_amount'];//确认金额
//            $data['confirm_images'] = $data['apply_images'];
//            $data['confirm_remark'] = '快速充值';
//            $data['status'] = RechargeApply::STATUS_AUDIT_SUCCESS;//审核通过

            $apply = $this->model->query()->create($data);

            /**
             * 审核流程
             */
//            //更新客户余额
//            $balanceService = new BalanceService($apply->custom_id);
//            //金额为美元
//            $record = $balanceService
//                ->setRemark('快速充值')
//                ->setRelationId($apply->id)
//                ->increase($applyAmount, BalanceRecord::SOURCE_RECHARGE, $apply->serial_no);
//
//            //查询充值赠送金额
//            $defaultRechargeAmountConfig = DefaultRechargeAmount::query()
//                ->where('amount', '<=', $applyAmount)
//                ->orderByDesc('amount')
//                ->first();
//            if ($defaultRechargeAmountConfig && ($defaultRechargeAmountConfig->complimentary_amount > 0)) {
//                $balanceService
//                    ->setRemark('充值赠送')
//                    ->setRelationId($apply->id)
//                    ->increase($defaultRechargeAmountConfig->complimentary_amount, BalanceRecord::SOURCE_COMPLIMENTARY_RECHARGE, $apply->serial_no);
//            }
//
//            //更新充值流水号
//            $apply->out_serial_no = $record->serial_no;
//            $apply->save();

            return true;
        });
    }

    /**
     * 撤销充值
     * @param $params
     * @return mixed pay_amount\apply_amount     confirm_amount
     */
    public function revocation($params)
    {
        validator($params, [
            'id' => 'required|int',
            'remark' => 'required',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $apply = $this->model->query()->findOrFail($params['id']);

            /**
             * 审核流程
             */
            //更新客户余额
            $balanceService = new BalanceService($apply->custom_id);
            //金额为美元
            $record = $balanceService
                ->setRemark('撤销充值')
                ->setRelationId($apply->id)
                ->deduction($apply->confirm_amount / 100, BalanceRecord::SOURCE_RECHARGE_REVOCATION, $apply->serial_no);

            //查询充值赠送金额
            $defaultRechargeAmountConfig = DefaultRechargeAmount::query()
                ->where('amount', '<=', $apply->confirm_amount / 100)
                ->orderByDesc('amount')
                ->first();
            if ($defaultRechargeAmountConfig && ($defaultRechargeAmountConfig->complimentary_amount > 0)) {
                $balanceService
                    ->setRemark('撤销赠送')
                    ->setRelationId($apply->id)
                    ->deduction($defaultRechargeAmountConfig->complimentary_amount, BalanceRecord::SOURCE_RECHARGE_REVOCATION, $apply->serial_no);
            }

            //更新撤销信息
            $apply->status = RechargeApply::STATUS_REVOCATION;//撤销充值
            $apply->revocation_operator = auth('admin')->user()->id ?? 0;//撤销操作员
            $apply->revocation_remark = $params['remark'];//撤销充值
            $apply->revocation_no = $record->serial_no;//撤销流水号
            $apply->revocation_at = now();//撤销时间
            $apply->save();

            return true;
        });
    }

    /**
     * 获取在线充值记录
     * @param $params
     * @param bool $isReturnQueryBuilder
     * @return \Illuminate\Database\Eloquent\Builder|LengthAwarePaginator
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/5 16:44
     */
    public function getOnlineRechargeList($params, $isReturnQueryBuilder = false)
    {
        $pageSize = $params['size'] ?? 10;
        $outTradeNo = $params['serial_no'] ?? '';
        $beginDate = $params['begin_date'] ?? '';
        $endDate = $params['end_date'] ?? '';
        $customId = $params['custom_id'] ?? '';
        $groupId = $params['group_id'] ?? '';
        $customerNumber = $params['customer_number'] ?? '';
        $checkStatus = $params['check_status'] ?? '';
        $rechargeAmount = isset($this->formData['recharge_amount']) ? floatval($this->formData['recharge_amount']) : 0;
        $rechargeAmount2 = isset($this->formData['recharge_amount2']) ? floatval($this->formData['recharge_amount2']) : 0;
        $comparisonOperators = $params['comparison_operators'] ?? '';

        $query = BalanceRecharge::query()->with(['custom.customGroup'])->where('status', BalanceRecharge::STATUS_SUCCESS);
        $query->when($outTradeNo, function ($query) use ($outTradeNo) {
            return $query->where('out_trade_no', 'like', '%' . $outTradeNo . '%');
        });
        $query->when($beginDate && $endDate, function ($query) use ($beginDate, $endDate) {
            return $query->whereBetween('created_at', [$beginDate, Carbon::parse($endDate)->addDay()->toDate()]);
        });
        $query->when($customId, function ($query) use ($customId) {
            return $query->where('custom_id', $customId);
        });
        $query->when($checkStatus >= 0, function ($query) use ($checkStatus) {
            return $query->where('check_status', $checkStatus);
        });
        $query->when($comparisonOperators, function ($query) use ($comparisonOperators, $rechargeAmount, $rechargeAmount2) {

            if (in_array($comparisonOperators, ['=', '<>', '>', '>=', '<', '<=', 'between'])) {

                if ($comparisonOperators == 'between') {

                    return $query->whereBetween('recharge_amount', [floatval($rechargeAmount), floatval($rechargeAmount2)]);
                } else {

                    return $query->where('recharge_amount', $comparisonOperators, floatval($rechargeAmount));
                }
            }
        });

        if ($groupId) {
            $query->whereHas('custom', function ($query) use ($groupId) {
                $query->where('group_id', $groupId);
            });
        }
        if ($customerNumber) {
            $query->whereHas('custom', function ($query) use ($customerNumber) {
                $query->where('customer_number', 'like', '%' . $customerNumber . '%');
            });
        }

        $query->latest();

        return $isReturnQueryBuilder ? $query : $query->paginate($pageSize);
    }

    /**
     * 提交核验
     * @param $params
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/7 15:16
     */
    public function submitCheck($params)
    {
        validator($params, [
            'type' => 'required|int|in:1,2',
            'id' => 'required|int',
            'check_admin_id' => 'required|int',
            'check_desc' => 'required|string|max:200',
            'check_images' => 'required|array',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $type = $params['type'] ?? 1;
            $id = $params['id'];

            unset($params['type']);
            //1线下充值 2在线充值
            if ($type == 1) {
                $record = $this->query->findOrFail($id);
                if (!empty($params['pay_method'])) $record->pay_method = $params['pay_method'];
            } else {
                $record = BalanceRecharge::query()->findOrFail($id);
            }

            if ($record->check_status != 0) {
                throw new AccidentException('该记录已提交核账，请勿重复提交', Code::OPERATE_FAIL);
            }


            $record->check_admin_id = $params['check_admin_id'] ?: getAdminId();
            $record->check_desc = $params['check_desc'];
            $record->check_images = $params['check_images'];
            $record->check_status = 1;
            $record->check_time = now();

            $record->save();
            return true;
        });

    }

    /**
     * 导出
     * @param $params
     * @return true
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/8 16:21
     */
    public function export($params)
    {
        validator($params, [
            'export_type' => 'required|int|in:21,22,23'
        ])->validate();
        $type = $params['export_type'];
        switch ($type) {
            case ExcelExport::TYPE_OFFLINE_RECHARGE:
                $prefix = 'OfflineRecharge';
                break;
            case ExcelExport::TYPE_ONLINE_RECHARGE:
                $prefix = 'OnlineRecharge';
                break;
            case  ExcelExport::TYPE_CREDIT_CARD_RECHARGE_RECORD:
                $prefix = 'CreditCardRecharge';
                break;
        }

        $fileName = $prefix . '_' . Carbon::now()->format('YmdHis') . '_' . Str::random(6) . '.xlsx';

        $data = $this->getExportData($type);

        /** @var $excelExport ExcelExport */
        $excelExport = ExcelExport::query()->create([
            'name' => $fileName,
            'type' => $type,
            'url' => '',
        ]);

        switch ($type) {
            case ExcelExport::TYPE_OFFLINE_RECHARGE:
                dispatch(new OfflineRechargeRecordExport($excelExport, $data));
                break;
            case ExcelExport::TYPE_ONLINE_RECHARGE:
                dispatch(new OnlineRechargeRecordExport($excelExport, $data));
                break;
            case ExcelExport::TYPE_CREDIT_CARD_RECHARGE_RECORD:
                dispatch(new CreditCardRechargeRecordExport($excelExport, $data));
                break;
        }

        return true;
    }

    /**
     * 获取导出数据
     * @param int $type
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/8 16:24
     */
    public function getExportData(int $type)
    {
        $data = [];
        switch ($type) {
            case ExcelExport::TYPE_OFFLINE_RECHARGE:

                $this->query->with(['custom.customGroup', 'paymentType', 'payMethod']);

                $this->setFilter();

                $this->query->latest();

                $this->query->chunk(100, function ($items) use (&$data) {
                    foreach ($items as $item) {
                        $data[] = [
                            'serial_no' => $item->serial_no,
                            'custom_id' => $item->custom_id,
                            'customer_number' => $item->custom->customer_number ?? '',
                            'custom_name' => $item->custom->custom_name ?? '',
                            'group_name' => $item->custom->customGroup ? $item->custom->customGroup->group_name : '',
                            'status_name' => $item->status_name,
                            'check_status_name' => $item->check_status_name,
                            'check_admin_name' => $item->check_admin_name,
                            'check_desc' => $item->check_desc,
                            'check_images' => $item->check_images,
                            // 'payment_type_name' => $item->payment_type_id ? '转账支付' : '其他支付',
                            'payment_type_name' => $item->paymentType ? $item->paymentType->name : '',
                            'currency' => $item->currency ?? 'USD',
                            'pay_amount' => bcdiv($item->pay_amount, 100, 2),
                            'apply_amount' => bcdiv($item->apply_amount, 100, 2),
                            'confirm_amount' => bcdiv($item->confirm_amount, 100, 2),
                            'apply_remark' => $item->apply_remark,
                            'created_at' => (string)$item->created_at,
                            'pay_method_name' => $item->payMethod->name ?? '',
                        ];
                    }
                });

                break;
            case ExcelExport::TYPE_ONLINE_RECHARGE:
                $params = $this->formData;

                $query = $this->getOnlineRechargeList($params, true);

                $query->chunk(100, function ($items) use (&$data) {
                    foreach ($items as $item) {
                        $data[] = [
                            'out_trade_no' => $item->out_trade_no,
                            'custom_id' => $item->custom_id,
                            'customer_number' => $item->custom->customer_number ?? '',
                            'custom_name' => $item->custom->custom_name ?? '',
                            'group_name' => $item->custom->customGroup ? $item->custom->customGroup->group_name : '',
                            'recharge_amount' => $item->recharge_amount,
                            'currency' => $item->currency ?? 'USD',
                            'pay_amount' => $item->pay_amount,
                            'check_status_name' => $item->check_status_name,
                            'check_admin_name' => $item->check_admin_name,
                            'check_desc' => $item->check_desc,
                            'check_images' => $item->check_images,
                            'type_name' => $item->type_name,
                            'created_at' => (string)$item->created_at,
                        ];
                    }
                });
                break;

            case ExcelExport::TYPE_CREDIT_CARD_RECHARGE_RECORD:
                $params = $this->formData;
                $query = $this->creditCardRechargeRecordService->list($params, true);
                $query->chunk(100, function ($items) use (&$data) {
                    foreach ($items as $item) {
                        switch ($item->type) {
                            case CreditCardRechargeRecord::TYPE_40SEAS:
                                $type_name = '40Seas';
                                break;
                            default:
                                $type_name = '';
                        }
                        $data[] = [
                            'transaction_id' => $item->transaction_id,//交易单号
                            'custom_id' => $item->custom_id,//客户ID
                            'customer_number' => $item->custom->customer_number ?? '',//客户编号
                            'custom_name' => $item->custom->custom_name ?? '',//客户名称
                            'group_name' => $item->custom->customGroup ? $item->custom->customGroup->group_name : '',//客户分组
                            'amount' => $item->amount,//充值金额
                            'currency' => $item->currency ?? 'USD',//支付货币
                            'check_status' => $item->check_status,//核账状态
                            'type' => $type_name,//转账方式
                            'check_admin_name' => $item->admin->name ?? '',//核账人
                            'check_desc' => $item->check_desc ?? '',//核账描述
                            'check_images' => $item->check_images ?? '',//核账图片
                            'created_at' => (string)$item->created_at,//充值时间
                        ];
                    }
                });
                break;
        }


        return $data;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function chargePayMethodList()
    {
        return ChargePayMethod::query()->when(isset($this->formData['status']), function ($query) {
            $query->where('status', $this->formData['status']);
        })->get();
    }

    /**
     * @param $params
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws AccidentException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function addChargePayMethod($params)
    {
        validator($params, [
            'name' => 'required|string'
        ])->validate();
        $exist = ChargePayMethod::query()->where('name', $params['name'])->first();
        if (!empty($exist)) {
            throw new AccidentException('该付款方式已存在');
        }
        return ChargePayMethod::query()->create([
            'name' => $params['name']
        ]);
    }


    /**
     * @param $id
     * @param $params
     * @return bool|int
     * @throws AccidentException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateChargePayMethod($id, $params)
    {
        validator($params, [
            'name' => 'required|string'
        ])->validate();
        $chargePayMethod = ChargePayMethod::query()->findOrFail($id);
        $exist = ChargePayMethod::query()->where('id', '!=', $id)->where('name', $params['name'])->first();
        if (!empty($exist)) {
            throw new AccidentException('该付款方式已存在');
        }
        return $chargePayMethod->update(['name' => $params['name']]);
    }


    public function updatePayMethodStatus($id, $params)
    {
        validator($params, [
            'status' => 'required|int'
        ])->validate();
        $chargePayMethod = ChargePayMethod::query()->findOrFail($id);
        return $chargePayMethod->update(['status' => $params['status']]);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function deleteChargePayMethod($id)
    {
        return ChargePayMethod::query()->where('id', $id)->delete();
    }


    public function rules()
    {
        return [
            'custom_id' => 'required|int',
            'payment_type_id' => 'required|int',
            'apply_amount' => 'required|int',
            'audit' => 'required|int',
            'pay_account' => 'sometimes|nullable|string',
            'apply_image' => 'sometimes|nullable|array',
            'apply_remark' => 'sometimes|nullable|string',
        ];
    }

    public function auditRules()
    {
        return [
            'audit_status' => 'required|int',
            'confirm_amount' => 'required_if:audit_status,1|numeric',
            'confirm_images' => 'sometimes|nullable|array',
            'confirm_remark' => 'sometimes|nullable|string',
        ];
    }

    public function quickRechargeRules()
    {
        return [
            'custom_id' => 'required|int',
            'payment_type_id' => 'required|int',
            'pay_amount' => 'required|numeric',
            'pay_account' => 'required|string',
            'apply_images' => 'required|array',
            'apply_remark' => 'sometimes|nullable|string',
            'currency' => 'required|string',
            'pay_method' => 'sometimes|nullable|numeric',
        ];
    }

}
