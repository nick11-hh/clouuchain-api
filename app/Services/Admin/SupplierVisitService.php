<?php

namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Http\Resources\Admin\SupplierVisitResource;
use App\Lib\Code;
use App\Models\Supplier;
use App\Models\SupplierVisit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierVisitService extends BaseService
{
    public $filterRules = [
        'product' => ['like', 'keyword'],
        'supplier_id' => ['=', 'supplier_id'],
        'visit_date_start' => ['>', 'visit_date_start'],
        'visit_date_end' => ['<', 'visit_date_end'],
    ];

    public function __construct()
    {
        $this->model = new SupplierVisit();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /**
     * 获取供应商拜访记录列表
     */
    public function index()
    {
        $this->query->with('supplier');
        return parent::index();
    }

    /**
     * 获取单个供应商拜访记录详情
     */
    public function show($id)
    {
        $visit = $this->model::with('supplier')->findOrFail($id);
        return new SupplierVisitResource($visit);
    }

    /**
     * 创建供应商拜访记录
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $data = $this->model::init($params);
        $visit = $this->model::query()->create($data);
        // 更新供应商拜访次数
        if ($visit) {
            $supplier = Supplier::query()->find($params['supplier_id']);
            if ($supplier) {
                $supplier->increment('visit_times');
            }
        }

        return $visit;
    }

    /**
     * 更新供应商拜访记录
     * @throws AccidentException|ValidationException
     */
    public function update($id, $params): bool|int
    {
        validator($params, $this->rules())->validate();
        $visit = $this->model::query()->findOrFail($id);
        $data = $this->model::init($params);
        return $visit->update($data);
    }

    /**
     * 删除供应商拜访记录
     */
    public function destroy($id)
    {
        $visit = $this->model::query()->findOrFail($id);
        return $visit->delete();
    }

    /**
     * 批量删除供应商拜访记录
     */
    public function batchDestroy($params)
    {
        if (empty($params['ids'])) {
            throw new AccidentException('请选择需要删除的记录', Code::OPERATE_FAIL);
        }

        return $this->model::query()->whereIn('id', $params['ids'])->delete();
    }

    /**
     * 验证并处理输入数据
     */
    private function rules()
    {
        return [
            'supplier_id' => 'required|integer|exists:dsp_suppliers,id',
            'visit_date_start' => 'required|date',
            'visit_date_end' => 'required|date',
            'product' => 'nullable|string|max:255',
            'key_results' => 'nullable|string',
        ];
    }
}
