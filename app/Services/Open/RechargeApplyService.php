<?php

namespace App\Services\Open;

use App\Services\Open\BaseService;
use App\Models\RechargeApply;

class RechargeApplyService extends BaseService
{
    public $filterRules = [
        'custom_id'                 => ['=', 'custom_id'],
        'created_at'                => ['between', ['begin_date', 'end_date']],
        'custom:group_id'           => ['=', 'group_id'],
        'custom:customer_number'    => ['like', 'customer_number'],
        'check_status'              => ['=', 'check_status'],
        'serial_no'                 => ['=', 'serial_no'],
        'status'                    => ['=', 'status'],
    ];

    public function __construct()
    {
        $this->model = new RechargeApply();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['custom.customGroup', 'paymentType', 'revocationOperator', 'payMethod']);
        $rechargeAmount = $this->formData['recharge_amount'] ?? '';
        $rechargeAmount2 = $this->formData['recharge_amount2'] ?? '';
        $comparisonOperators = $this->formData['comparison_operators'] ?? '';
        $this->query->when($comparisonOperators, function ($query) use ($comparisonOperators, $rechargeAmount, $rechargeAmount2) {

            if(in_array($comparisonOperators, ['=', '<>', '>', '>=', '<', '<=', 'between'])){

                if($comparisonOperators == 'between'){

                    return $query->whereBetween('apply_amount', [floatval($rechargeAmount * 100), floatval($rechargeAmount2 * 100)]);
                }else{

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
}
