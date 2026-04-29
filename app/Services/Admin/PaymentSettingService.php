<?php

namespace App\Services\Admin;

use App\Models\PaymentSetting;

class PaymentSettingService extends BaseService
{
    public $filterRules = [
        'name'     => ['=', 'name'],
    ];
    public function __construct(PaymentSetting $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->latest();
        return parent::index();
    }

    public function show($id)
    {
        return $this->model::query()->findOrFail($id);
    }

    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $data = $this->model::init($params);
        return $this->model::query()->create($data);
    }

    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        $payment = $this->model::query()->findOrFail($id);
        $data = $this->model::init($params);
        return $payment->update($data);
    }

    public function updateStatus($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int'
        ])->validate();
        return $this->model::query()->whereIn('id', $params['ids'])->update([
            'enabled' => $params['status']
        ]);
    }

    public function deletes()
    {
        validator($this->formData, ['ids' => 'required|array'], [], ['ids' => '支付方式'])->validate();

        return $this->model::whereIn('id', $this->formData['ids'])->delete();
    }

    private function rules()
    {
        return [
            'name' => 'required|string|max:50',
            'pay_logo' => 'required|string',
            'pay_qrcode' => 'sometimes|string|nullable',
            'pay_account' => 'sometimes|string|nullable',
            'remark' => 'sometimes|string|nullable',
            'currency' => 'sometimes|string|nullable'
        ];
    }
}
