<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\GoodsSupplier;
use App\Models\PurchaseOrdersItemsModel;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class GoodsSupplierService extends BaseService
{
    public $filterRules = [
        'goodsSku:sku_id,spec_name;goodsSku.goods:goods_name;purchase_goods_name,purchase_spec_name' => ['like', 'keyword'],
        'goods_name' => ['like', 'goods_name'],
        'spu' => ['=', 'spu'],
        'created_at' => ['between', ['begin_date', 'end_date']]
    ];


    public function __construct()
    {
        $this->model = new GoodsSupplier();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['goodsSku.goods', 'supplier'])
            ->whereHas('goodsSku', function ($query) {
                $query->whereNull('deleted_at');
            })->latest();
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['goodsSku.goods', 'supplier'])->findOrFail($id);
    }


    /**
     * @param $params
     * @return mixed
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($params) {
            foreach ($params['sku_list'] as $sku) {
                $goodsSupplier = $this->model::query()
                    ->where('goods_sku_id', $sku['goods_sku_id'])
                    ->where('supplier_id', $sku['supplier_id'])
                    ->first();
                if (!empty($goodsSupplier)) throw new AccidentException('商品已存在相同的供应商映射', Code::OPERATE_FAIL);
                if((isset($sku['purchase_goods_id']) && !empty($sku['purchase_goods_id'])) && (isset($sku['purchase_spec_id']) && !empty($sku['purchase_spec_id']))) {
                    $this->syncPurchase($sku);
                }
                $data = $this->model::init($sku);
                $this->model::query()->create($data);
            }
            return true;
        });
    }

    public function syncPurchase($sku)
    {
        PurchaseOrdersItemsModel::whereHas('order', function ($query) use($sku){
            $query->whereIn('status', [0,1])->where('provider_id', $sku['supplier_id']);
        })
            ->where('sku_id', $sku['goods_sku_id'])
            ->update(['offerId' => $sku['purchase_goods_id'], 'specId' => $sku['purchase_spec_id']]);
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($id, $params) {
            $goodsSupplier = $this->model::query()->findOrFail($id);
            $data = $this->model::init($params['sku_list'][0]);
            if((isset($data['purchase_goods_id']) && !empty($data['purchase_goods_id'])) && (isset($data['purchase_spec_id']) && !empty($data['purchase_spec_id']))) {
                $this->syncPurchase($data);
            }
            return $goodsSupplier->update($data);
        });
    }

    /**
     * @param $params
     * @return mixed
     */
    public function updateStatus($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int'
        ])->validate();
        $data = ['status' => $params['status']];
        return $this->model->whereIn('id', $params['ids'])->update($data);
    }


    public function deletes($params)
    {
        if (empty($params['ids'])) throw new AccidentException('请选择需要删除的产品', Code::OPERATE_FAIL);
        return $this->model::query()->whereIn('id', $params['ids'])->delete();
    }

    public function rules()
    {
        return [
            'sku_list' => 'required|array',
            'sku_list.*.goods_sku_id' => 'required|int',
            'sku_list.*.supplier_id' => 'required|int',
            'sku_list.*.price' => 'required|string',
            'sku_list.*.purchase_type' => 'required|int',
        ];
    }

    public function getDetailBySku()
    {
        validator($this->formData, [
            'goods_sku_id' => 'required|int',
            'supplier_id' => 'sometimes|nullable|int'
        ])->validate();

        $this->model::query()->with(['goodsSku.goods', 'supplier']);

        if (isset($this->formData['goods_sku_id']) && !empty($this->formData['goods_sku_id'])) {
            $this->query->where('goods_sku_id', $this->formData['goods_sku_id']);
        }

        if (isset($this->formData['supplier_id']) && !empty($this->formData['supplier_id'])) {
            $this->query->where('supplier_id', $this->formData['supplier_id']);
        }

        return  $this->query->first();
    }

}
