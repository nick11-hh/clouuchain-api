<?php

namespace App\Services\Client;


use App\Helper\CurrencyConverter;
use App\Lib\Code;
use App\Models\BalanceRecharge;
use App\Models\ClientGoods;
use App\Models\ClientGoodsSku;
use App\Models\CreditCardRechargeRecord;
use App\Models\ExchangeRateModel;
use App\Models\GoodsSku;
use App\Models\RechargeApply;
use Illuminate\Support\Facades\DB;

class RechargeApplyService extends BaseService
{
    public $filterRules = [
        'created_at'     => ['between', ['begin_date', 'end_date']]
    ];

    private ClientGoodsSku $skuModel;

    public function __construct()
    {
        $this->model = new RechargeApply();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['custom', 'paymentType']);
        if (isset($this->formData['status']) && $this->formData['status'] !== '') {
            $this->query->where('status', $this->formData['status']);
        }
        $this->query->where('custom_id', getCustomId())->latest();
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['custom'])->findOrFail($id);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function store($params)
    {
        //
        validator($params, $this->rules())->validate();

        $currency = $params['currency'] ?? '';

        //支付金额 多币种
        if ($currency !== 'USD') {
            //转其他币种
            $payAmount = (new CurrencyConverter($currency))->convert($params['apply_amount']);

            if ($payAmount) {
                $params['pay_amount'] = bcmul($payAmount, 100);
            }
        }

        //申请金额 USD
        $params['apply_amount'] = bcmul($params['apply_amount'], 100);

        $data = RechargeApply::init($params);
        return $this->model->query()->create($data);
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        $rechargeApply = $this->model->query()->findOrFail($id);
        $rechargeApply->payment_type_id = $params['payment_type_id'];
        $rechargeApply->apply_amount = $params['apply_amount'];
        $rechargeApply->apply_images = $params['apply_images'] ?? '';
        $rechargeApply->apply_remark = $params['apply_remark'] ?? '';
        $rechargeApply->pay_account = $params['pay_account'] ?? '';
        return $rechargeApply->save();
    }

    /**
     * @param $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getBalanceRecord($params)
    {
        $number  = $params['number'] ?? '';
        $query = BalanceRecharge::query()->where('status', BalanceRecharge::STATUS_SUCCESS);
        $query->when($number, function ($query) use ($number) {
            return $query->where('out_trade_no', $number);
        });

        $query->where('custom_id', getCustomId());

        return $query->latest()->paginate($params['size'] ?? 10);
    }

    public function creditCardRecharge($params)
    {
        $query = CreditCardRechargeRecord::query()->where('status', CreditCardRechargeRecord::STATUS_SUCCESS);

        $query->where('custom_id', getCustomId());

        return $query->latest()->paginate($params['size'] ?? 10);
    }

    public function rules()
    {
        return [
            'payment_type_id' => 'required|int',
            'apply_amount' => 'required|numeric',
            'apply_images' => 'sometimes|nullable|array',
            'apply_remark' => 'sometimes|nullable|string',
            'pay_account' => 'sometimes|nullable|string',
        ];
    }

}
