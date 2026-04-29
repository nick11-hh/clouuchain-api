<?php

namespace App\Services\Client;

use App\Models\AgentCommission;
use App\Models\BalanceRecharge;
use App\Models\BalanceRecord;
use App\Models\CustomBalance;
use App\Models\Landlord\Tenant;
use App\Models\PaymentSetting;
use App\Models\PaypalPayment;
use App\Models\RechargeApply;
use App\Services\Base\PayPalService;
use Illuminate\Http\Request;

class BalanceService extends BaseService
{
    public $filterRules = [
        'type'           => ['=', 'type'],
        'source_type'    => ['=', 'source_type'],
        'serial_no'      => ['=', 'serial_no'],
        'order_sn'       => ['=', 'order_sn'],
        'created_at'     => ['between', ['begin_date', 'end_date']]
    ];

    public function __construct()
    {
        $this->model = new BalanceRecord();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function records()
    {
        $this->query->with('custom');
        $this->query->where('custom_id', getCustomId())->latest();
        $this->query->latest();

        return parent::index();
    }

    // 充值记录
    public function transferRecords($data)
    {
        $query = RechargeApply::query()->with(['custom', 'paymentType']);
        $query->where('custom_id', getCustomId())->latest();
        if ($data['orderNumber'] ?? '') {
            $serial_no = $data['orderNumber'];
            $query->where('serial_no', 'like', "%$serial_no");
        }
        if ($data['created_at'] ?? '') {
            $query->whereBetween('created_at', [$data['begin_date'], $data['end_date']]);
        }

        $query->latest();
        return $query->paginate($params['size'] ?? 10);
    }

    public function balance()
    {
        $data = CustomBalance::query()->with('custom')->where('custom_id', getCustomId())->first();

        //查询佣金结算金额
        $commissionList = AgentCommission::query()->where('agent_id', getCustomId())->get();

        $withdrawn = $noWithdrawal = $withdrawing = 0;
        if ($commissionList->isNotEmpty()) {
            //已结算
            $withdrawn = $commissionList->filter(function ($item) {
                return $item->status === AgentCommission::STATUS_WITHDRAW_SUCCESS;
            })->sum('commission_amount');

            //可结算
            $noWithdrawal = $commissionList->filter(function ($item) {
                return $item->status === AgentCommission::STATUS_NOT_WITHDRAW;
            })->sum('commission_amount');

            //申请中
            $withdrawing = $commissionList->filter(function ($item) {
                return $item->status === AgentCommission::STATUS_WITHDRAWING;
            })->sum('commission_amount');
        }

        if (!empty($data)) {
            $data->balance = $data->balance / 100;
            $data->commission = $data->commission / 100;

            $data->withdrawn = number_format($withdrawn, 2);
            $data->no_ithdrawal = number_format($noWithdrawal, 2);
            $data->withdrawing = number_format($withdrawing, 2);
        }

        return $data;
    }

    public function getPaymentList()
    {
        return PaymentSetting::query()
            ->where('enabled', PaymentSetting::STATUS_ENABLED)
            ->latest()->get();
    }

    /**
     * 支付
     * @param array $params
     * @return string[]
     * @throws \Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/2/11 15:50
     */
    public function payment(array $params)
    {
        $paypal = PaypalPayment::query()->first();
        $minimumPayment = $paypal->minimum_payment ?? 0;
        $minimumPayment = $minimumPayment <= 0 ? 0.01 : $minimumPayment;

        validator($params,[
            'amount'    => "required|numeric|min:$minimumPayment",
            'host'      => 'required|string',
        ])->validate();

        $host = urlencode($params['host']);
        $amount = $params['amount'];//充值金额
        $data = [
            'custom_id' => getCustomId(),
            'type' => BalanceRecharge::TYPE_PAYPAL,
            'out_trade_no' => '',
            'recharge_amount' => $amount,
            'currency' => 'USD',
        ];

        /**
         * 计算手续费
         * 充值金额：100
         *
         * 固定手续费金额+手续费比例场景
         * 手续费：2%+5
         * 付款金额：(100+5)/(100%-2%)=107.14
         * 到账金额(含固定金额的手续费)：107.14*(100%-2%)=105
         * 到账金额(不含固定金额的手续费)：105-5=100
         *
         * 手续费比例场景
         * 手续费：2%
         * 付款金额：100 + (100 * 2%) = 102
         * 到账金额：102 - (100 * 2%) = 100
         */
        $serviceChargeRate = $paypal->service_charge_rate ?? 0;//paypal 手续费比例
        $serviceChargeAmount = $paypal->service_charge_amount ?? 0;//paypal 手续费固定金额

        if ($serviceChargeAmount > 0) {
            // 支付金额 = (充值金额 + 固定手续费金额) / 手续费比例
            $payAmount = ($amount + $serviceChargeAmount) / ((100 - $serviceChargeRate) / 100);
        } else {
            // 支付金额 = 充值金额 + (充值金额 * 手续费比例)
            $payAmount = $amount + ($amount * ($serviceChargeRate / 100));
        }
        $finalServiceChargeAmount = $payAmount - $amount;

        $data['pay_amount'] = sprintf("%.2f", $payAmount);//支付金额
        $data['service_charge_amount'] = sprintf("%.2f", $finalServiceChargeAmount);//手续费

        $recharge = BalanceRecharge::query()->create($data);

        $returnUrl = config('app.url') . '/api/client/paypal/callback/' . Tenant::current()->uuid . "?host={$host}";
        $testMode = (boolean)($paypal->sandbox ?? 0);

        $paypalService = new PayPalService();
        $paypalService->withDBConfig($testMode);
        $result = $paypalService->pay($recharge->pay_amount, $recharge->currency, $returnUrl);

        $recharge->out_trade_no = $result['id'];
        $recharge->save();

        $url = $result['links'][1]['href'] ?? '';
        return ['url' => $url];
    }



}
