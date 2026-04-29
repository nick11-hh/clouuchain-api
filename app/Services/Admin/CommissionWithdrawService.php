<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\AgentCommission;
use App\Models\BalanceRecord;
use App\Models\CommissionWithdraw;
use App\Services\Base\BalanceService;
use App\Services\Base\CommissionService;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class CommissionWithdrawService extends BaseService
{
    public $filterRules = [
        'custom_id'         => ['=', 'custom_id'],
        'status'            => ['=', 'status'],
        'withdraw_type'     => ['=', 'withdraw_type'],
        'created_at'        => ['between', ['begin_date', 'end_date']]
    ];


    public function __construct()
    {
        $this->model = new CommissionWithdraw();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['custom'])->withCount('commission');
        if (isset($this->formData['status']) && $this->formData['status'] !== '') {
            $this->query->where('status', $this->formData['status']);
        }
        //流水号搜索
        if (isset($this->formData['serial_no']) && $this->formData['serial_no'] !== '') {
            $this->query->where('serial_no', $this->formData['serial_no']);
        }
        $this->query->latest();
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['custom', 'commission'])->findOrFail($id);
    }


    /** 提现申请审核
     * @param $id
     * @param $params
     * @return mixed
     */
    public function audit($id, $params)
    {
        validator($params, $this->auditRules())->validate();
        return DB::transaction(function () use ($id, $params) {
            $withdraw = $this->model::query()->with(['commission'])->findOrFail($id);
            if ($withdraw->status != $this->model::STATUS_APPLY) {
                throw new AccidentException('当前申请已审核', Code::OPERATE_FAIL);
            }
            $withdraw->status = $params['audit_status'];
            if ($withdraw->status == $this->model::STATUS_AUDIT) { // 审核成功
                if ($params['confirm_amount'] > $withdraw->withdraw_amount) {
                    throw new AccidentException("审核金额不能大于申请提现金额", Code::OPERATE_FAIL);
                }
                $withdraw->confirm_amount = $params['confirm_amount'] ?? 0;
                $withdraw->commission()->update([
                    'status' => AgentCommission::STATUS_WITHDRAW_SUCCESS,
                ]);
                // 减少佣金金额
                $commissionService = new CommissionService($withdraw->custom_id);
                $commissionService->deduction($withdraw->confirm_amount);
                if ($withdraw->withdraw_type === $this->model::WITHDRAW_TYPE_BALANCE) { // 余额收款
                    $balanceService = new BalanceService($withdraw->custom_id);
                    $balanceService->setRelationId($withdraw->id)->increase($withdraw->confirm_amount, BalanceRecord::SOURCE_WITHDRAW, $withdraw->serial_no);
                    $withdraw->withdraw_status = $this->model::WITHDRAW_SUCCESS;
                }
            } else {
                //审核失败
                $withdraw->commission()->update([
                    'status' => AgentCommission::STATUS_NOT_WITHDRAW,
                    'withdraw_id' => 0,
                ]);
            }
            $withdraw->confirm_images = $params['confirm_images'] ?? [];
            $withdraw->confirm_remark = $params['confirm_remark'] ?? '';
            $withdraw->save();
            return $withdraw;
        });
    }


    public function auditRules()
    {
        return [
            'audit_status' => 'required|int',
            'confirm_amount' => 'required_if:audit_status,2',
            'confirm_images' => 'sometimes|nullable|array',
            'confirm_remark' => 'sometimes|nullable|string',
        ];
    }

}
