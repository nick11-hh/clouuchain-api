<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\PaymentSetting;
use App\Models\PaymentSettingConnection;
use App\Models\PayOnDeliveryConfig;
use App\Models\PaypalPayment;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class PaymentService extends BaseService
{
    use HasStatusSetting;

    protected $orderBy = ['id' => 'asc'];

    public function __construct(PaymentSetting $paymentSetting)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $paymentSetting;
        $this->query = $paymentSetting->newQuery();
        $this->setFilterRules();
    }

    /**
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function index()
    {
        $this->query->with('PaymentSettingConnection');
        return $this->setFilter()->setOrderBy()->pageSize();
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|mixed
     */
    public function show($id)
    {
        $this->query->with('PaymentSettingConnection');
        return $this->query->where('id', $id)->firstOrFail();
    }

    /**
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function indexAccount(int $id)
    {
        return PaymentSettingConnection::query()->where('payment_settings_id', $id)->get();
    }

    /**
     * 获取线下支付配置
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function showAccount(int $id)
    {
        return PaymentSettingConnection::query()->where('id', $id)->firstOrFail();
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool|int
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateAccount(int $id, array $data)
    {
        validator($data, $this->rulesAccount())->validate();

        $row = PaymentSettingConnection::query()->where('id', $id)->firstOrFail();
        return $row->update([
            'payment_settings_id' => $data['payment_settings_id'],
            'name' => $data['name'],
            'content' => $data['content']
        ]);
    }

    /**
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeAccount(array $data)
    {
        validator($data, $this->rulesAccount())->validate();
        $params = [
            'name' => $data['name'],
            'content' => $data['content'],
            'payment_settings_id' => $data['payment_settings_id'],
        ];
        return PaymentSettingConnection::query()->create($params);
    }

    /**
     * @param int $id
     * @return bool|mixed|null
     */
    public function destroyAccount(int $id)
    {
        $row = PaymentSettingConnection::query()->where('id', $id)->firstOrFail();
        return $row->delete();
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateAccountTranslateData(int $id, array $data)
    {
        validator($data, $this->translateAccountRules())->validate();

        /** @var PaymentSettingConnection $setting */
        $setting = PaymentSettingConnection::query()->findOrFail($id);
        $setting->setTranslations('name', [$data['language'] => $data['name']]);
        $setting->setTranslations('content', [$data['language'] => $data['content']]);

        return $setting->save();
    }

    /**
     * 获取paypal支付配置信息
     * @return PaypalPayment|null
     */
    public function getPaypalPaymentConfiguration(): ?PaypalPayment
    {
        return PaypalPayment::first();
    }

    /**
     * 获取paypal支付状态
     *
     * @return array
     */
    public function getPaypalPaymentStatus()
    {
        $payment = PaypalPayment::query()->first();

        return [
            'enabled' => $payment ? $payment->enabled : 0,
        ];
    }

    /**
     * 更新paypal支付状态
     *
     * @param bool $status
     * @return bool
     * @throws \Exception
     */
    public function setPaypalPaymentStatus(bool $status): bool
    {
        $payment = PaypalPayment::first();

        if (! $payment) {
            throw new AccidentException('请先配置PayPal支付信息再设置状态', Code::OPERATE_FAIL);
        }

        $payment->enabled = $status;

        return $payment->save();
    }

    /**
     * 更新货到付款支付状态
     *
     * @param  bool  $status
     * @return bool
     */
    public function setPayOnDeliveryStatus(bool $status): bool
    {
        return PayOnDeliveryConfig::query()->updateOrCreate(
            [],
            [
                'status' => (int) $status
            ]
        ) !== false;
    }

    /**
     * 更新Paypal支付配置信息
     * @param array $data
     */
    public function updatePaypalPaymentConfiguration(array $data)
    {
        validator($data, $this->paypalRules())->validate();

        $data = [
            'account' => $data['account'],
            'client_id' => $data['client_id'],
            'secret' => $data['secret'],
            'sandbox' => $data['sandbox'],
            'minimum_payment' => $data['minimum_payment'] ?? 1,
            'service_charge_rate' => $data['service_charge_rate'] ?? 0,
            'service_charge_amount' => $data['service_charge_amount'] ?? 0,
        ];

        //只查询首条数据
        $paypal = PaypalPayment::query()->first();
        if ($paypal) {
            return $paypal->update($data);
        }

        return PaypalPayment::query()->create($data);
    }

    /**
     * 添加支付配置
     *
     * @param array $data
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function add(array $data)
    {
        validator($data, $this->rules())->validate();

        $this->model::validateUniqueOrFail('name', $data['name']);

        DB::transaction(function () use ($data) {
            $row = PaymentSetting::query()->create([
                'name' => $data['name'],
                'pay_logo' => $data['pay_logo'] ?? '',
                'pay_account' => $data['pay_account'] ?? '',
                'pay_qrcode' => $data['pay_qrcode'] ?? '',
                'remark' => $data['remark'] ?? '',
                'enabled' => $data['enabled'] ?? 1,
                'currency' => $data['currency'] ?? 'USD',
            ]);

            foreach ($data['payment_setting_connection'] ?? [] as $data) {
                PaymentSettingConnection::query()->create([
                    'name' => $data['name'],
                    'content' => $data['content'],
                    'payment_settings_id' => $row->id,
                ]);
            }
        });

        return true;
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(int $id, array $data)
    {
        validator($data, $this->updateRules())->validate();

        $setting = $this->model::findOrFail($id);

        $this->model::validateUniqueOrFail('name', $data['name'], $id);

        return $setting->update(
            [
                'name' => $data['name'],
                'pay_logo' => $data['pay_logo'] ?? '',
                'pay_account' => $data['pay_account'] ?? '',
                'pay_qrcode' => $data['pay_qrcode'] ?? '',
                'remark' => $data['remark'] ?? '',
                'enabled' => $data['enabled'] ?? 1,
                'currency' => $data['currency'] ?? 'CNY',
            ]
        ) !== false;
    }

    /**
     * 更新翻译字段
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateTranslateData(int $id, array $data): bool
    {
        validator($data, $this->translateRules())->validate();
        /** @var PaymentSetting $setting */
        $setting = $this->model::query()->findOrFail($id);

        $setting->setTranslation('name', $data['language'], $data['name']);
        $setting->setTranslation('remark', $data['language'], $data['remark'] ?? '');

        return $setting->save();
    }

    protected function translateRules()
    {
        return parent::translateRules() + [
            'name' => 'required|string',
            'remark' => 'sometimes|nullable|string',
        ];
    }

    protected function translateAccountRules()
    {
        return parent::translateRules() + [
                'name' => 'required|string',
                'content' => 'required|string',
            ];
    }

    private function paypalRules()
    {
        return [
            'sandbox' => 'required|int',
            'account' => 'required',
            'client_id' => 'required',
            'secret' => 'required',
            'minimum_payment' => 'sometimes|nullable|numeric|min:0.01',
            'service_charge_rate' => 'sometimes|nullable|numeric',
            'service_charge_amount' => 'sometimes|nullable|numeric',
        ];
    }

    private function updateRules()
    {
        return [
            'name' => 'required|string',
            'pay_logo' => 'sometimes|nullable|string|max:250',
            'pay_qrcode' => 'required_without:pay_logo|max:250',
            'pay_account' => 'sometimes|nullable|string|max:250',
            'remark' => 'sometimes|nullable|string',
            'currency' => 'sometimes|nullable|string',
            'enabled' => 'sometimes|nullable|int|0,1',
        ];
    }

    private function rules()
    {
        return [
            'name' => 'required|string',
            'pay_logo' => 'sometimes|nullable|string|max:250',
            'pay_qrcode' => 'required_without:pay_logo|max:250',
            'pay_account' => 'sometimes|nullable|string|max:250',
            'remark' => 'sometimes|nullable|string',
            'currency' => 'sometimes|nullable|string',
            'enabled' => 'sometimes|nullable|int|0,1',
            'payment_setting_connection' => 'sometimes|array',
            'payment_setting_connection.*.name' => 'required|string',
            'payment_setting_connection.*.content' => 'required|string',
        ];
    }

    private function rulesAccount()
    {
        return [
            'name' => 'required|string',
            'content' => 'required|string',
            'payment_settings_id' => 'required|int'
        ];
    }
}
