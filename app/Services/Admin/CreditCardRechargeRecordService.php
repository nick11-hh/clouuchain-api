<?php

namespace App\Services\Admin;


use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\CreditCardRechargeRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreditCardRechargeRecordService extends BaseService
{
    public function __construct(public CreditCardRechargeRecord $creditCardRechargeRecord)
    {

    }


    public function list($params, $isReturnQueryBuilder = false)
    {
        $pageSize = $params['size'] ?? 10;
        $outTradeNo = $params['serial_no'] ?? '';
        $beginDate = $params['begin_date'] ?? '';
        $endDate = $params['end_date'] ?? '';
        $customId = $params['custom_id'] ?? '';
        $groupId = $params['group_id'] ?? '';
        $customerNumber = $params['customer_number'] ?? '';
        $checkStatus = $params['check_status'] ?? '';
        $rechargeAmount = $params['recharge_amount'] ?? '';
        $rechargeAmount2 = $params['recharge_amount2'] ?? '';
        $comparisonOperators = $params['comparison_operators'] ?? '';

        $query = CreditCardRechargeRecord::query()->with(['custom.customGroup','admin'])->where('status', CreditCardRechargeRecord::STATUS_SUCCESS);
        $query->when($outTradeNo, function ($query) use ($outTradeNo) {
            return $query->where('transaction_id', 'like', '%' . $outTradeNo . '%');
        });
        //时间
        $query->when($beginDate && $endDate, function ($query) use ($beginDate, $endDate) {
            return $query->whereBetween('created_at', [$beginDate, Carbon::parse($endDate)->addDay()->toDate()]);
        });
        //客户主体id
        $query->when($customId, function ($query) use ($customId) {
            return $query->where('custom_id', $customId);
        });
        //状态
        $query->when($checkStatus, function ($query) use ($checkStatus) {
            return $query->where('check_status', $checkStatus);
        });
        //充值金额
        $query->when($comparisonOperators, function ($query) use ($comparisonOperators, $rechargeAmount, $rechargeAmount2) {

            if (in_array($comparisonOperators, ['=', '<>', '>', '>=', '<', '<=', 'between'])) {

                if ($comparisonOperators == 'between') {

                    return $query->whereBetween('amount', [floatval($rechargeAmount), floatval($rechargeAmount2)]);
                } else {

                    return $query->where('amount', $comparisonOperators, floatval($rechargeAmount));
                }
            }
        });
        //用户所属分组
        if ($groupId) {
            $query->whereHas('custom', function ($query) use ($groupId) {
                $query->where('group_id', $groupId);
            });
        }
        //用户编号
        if ($customerNumber) {
            $query->whereHas('custom', function ($query) use ($customerNumber) {
                $query->where('customer_number', 'like', '%' . $customerNumber . '%');
            });
        }

        $query->latest();

        return $isReturnQueryBuilder ? $query : $query->paginate($pageSize);

    }

    public function submitCheck($params)
    {
        $creditCardRechargeRecord = CreditCardRechargeRecord::where('id', $params['id'])->first();
        if (empty($creditCardRechargeRecord)) {
            throw new AccidentException('该记录不存在', Code::OPERATE_FAIL);
        }

        if ($creditCardRechargeRecord['check_status'] != 0) {
            throw new AccidentException('该记录已提交核账，请勿重复提交', Code::OPERATE_FAIL);
        }

        $creditCardRechargeRecord->check_admin_id = $params['check_admin_id'] ?: getAdminId();
        $creditCardRechargeRecord->check_desc = $params['check_desc'];
        $creditCardRechargeRecord->check_images = $params['check_images'];
        $creditCardRechargeRecord->check_status = CreditCardRechargeRecord::CHECK_STATUS_SUCCESS;
        $creditCardRechargeRecord->check_time = now();
        $creditCardRechargeRecord->save();
    }
}
