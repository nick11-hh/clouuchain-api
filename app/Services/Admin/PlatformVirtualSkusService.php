<?php

namespace App\Services\Admin;

use App\Models\OrderLineItem;
use App\Models\PlatformVirtualSku;
use Illuminate\Validation\ValidationException;

class PlatformVirtualSkusService extends BaseService
{

    public $filterRules = [
        'platform_variant_id' => ['=', 'platform_variant_id'],
    ];

    public function __construct()
    {
        $this->model = new PlatformVirtualSku();
        $this->query = $this->model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->latest();
        return parent::index();
    }

    /**
     * 新增平台虚拟商品设置
     * @return bool
     * @throws ValidationException
     */
    public function store($params): bool
    {
        validator($params, $this->rules())->validate();
        $this->model::query()->firstOrCreate([
            'platform' => $params['platform'],
            'platform_variant_id' => $params['platform_variant_id'],
        ]);
        return true;
    }

    /**
     * 通过订单sku创建虚拟商品
     * @return bool
     * @throws ValidationException
     */
    public function storeByOrderItem($id)
    {
        $orderLineItem = OrderLineItem::query()->with('shopOrder')->findOrFail($id);
        return $this->store([
            'platform' => $orderLineItem->shopOrder->platform,
            'platform_variant_id' => $orderLineItem->variant_id,
        ]);
    }

    public function deletes()
    {
        validator($this->formData, ['ids' => 'required|array'], [], ['ids' => '虚拟商品'])->validate();

        return $this->model::whereIn('id', $this->formData['ids'])->delete();
    }

    private function rules()
    {
        return [
            'platform' => 'required|string',
            'platform_variant_id' => 'sometimes|string|nullable|max:250',
        ];
    }

}
