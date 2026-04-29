<?php

namespace App\Services\Admin;


use App\Imports\Admin\GoodsImportMain;
use App\Imports\SupplierImport;
use App\Lib\Code;
use App\Models\Supplier;
use App\Models\SupplierVisit;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class SupplierService extends BaseService
{
    public $filterRules = [
        'supplier_name,supplier_code' => ['like', 'keyword'],
        'status' => ['=', 'status'],
        'type' => ['=', 'type'],
        'main_category' => ['=', 'main_category'],
        'shipping_address' => ['like', 'shipping_address'],
        'overall_rating' => ['=', 'overall_rating'],
        'person_in_charge' => ['=', 'person_in_charge'],
        'developer' => ['=', 'developer'],
        'factory_scale' => ['=', 'factory_scale'],
        'visit_times' => ['=', 'visit_times'],
    ];


    public function __construct()
    {
        $this->model = new Supplier();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
        // 添加基于developer字段的数据隔离
//        $this->applyDeveloperDataIsolation();
    }

    public function index()
    {
        $this->query->withCount('goodsSupplier')->latest();
        return parent::index();
    }

    public function getAllEnable()
    {
        $this->query->where('status', Supplier::STATUS_ENABLE);

        return parent::index();
    }

    public function show($id)
    {
        return $this->model::query()->findOrFail($id);
    }


    /**
     * @param $params
     * @return mixed
     * @throws ValidationException
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $data = $this->model::init($params);
        //自动生成供应商编号
        if (empty($data['supplier_code'])) {
            $data['supplier_code'] = Supplier::generateSupplierCode();
        }

        // 设置developer字段为当前登录账户ID
        $data['developer'] = auth()->id();

        return $this->model::query()->create($data);
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     * @throws ValidationException
     */
    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        $supplier = $this->model::query()->findOrFail($id);
        $data = $this->model::init($params);
        // 检查是否有尝试修改developer字段
        if (isset($params['developer']) && $params['developer'] != $supplier->developer) {
            // 只有ID为1的管理员可以修改developer字段
            if (auth()->id() != 1 || auth()->id() != 95) {
                unset($data['developer']); // 移除developer字段的更新
            }
        }
        return $supplier->update($data);
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
        if (empty($params['ids'])) throw new AccidentException('请选择需要删除的供应商', Code::OPERATE_FAIL);

        return DB::transaction(function () use ($params) {
            // 先删除相关的供应商访问记录
            SupplierVisit::query()->whereIn('supplier_id', $params['ids'])->delete();

            // 再删除供应商
            return $this->model::query()->whereIn('id', $params['ids'])->delete();
        });
    }

    /**
     * @throws AccidentException
     */
    public function import()
    {
        $file = request()->file('file');

        if (empty($file)) {
            throw new AccidentException('导入文件不能为空', Code::OPERATE_FAIL);
        }

        $extension = $file->getClientOriginalExtension();

        if (!in_array($extension, ['xls', 'xlsx', 'csv'])) {
            throw new AccidentException('文件格式错误，请上传excel文件', Code::OPERATE_FAIL);
        }

        return DB::transaction(function () use ($file) {
            $import = new SupplierImport();
            Excel::import($import, $file);
            return $import->getImportResults();
        });
    }

    protected function rules()
    {
        return [
            'supplier_name' => 'required|string',
            'type' => 'required|int',
            'supplier_qualification' => 'required|string',
            'main_category' => 'required|string',
            'shipping_address' => 'required|string',
            'payment_terms' => 'required|string',
            'cooperation_level' => 'required|int',
            'service_and_after_sales' => 'required|string',
            'tax_point' => 'required|int',
            'face_value' => 'required|int',
            'overall_rating' => 'required|int',
            'person_in_charge' => 'required|string',
            'contact_info' => 'required|string',
            'factory_scale' => 'required|int',
        ];
    }

    /**
     * 应用基于developer字段的数据隔离
     * 只显示当前登录管理员开发的供应商
     */
    protected function applyDeveloperDataIsolation()
    {
        // 获取当前登录的管理员ID
        $adminId = auth()->id();
        // 如果管理员ID存在，则添加developer字段过滤条件
        if ($adminId) {
            $this->query->where('developer', $adminId);
        }
    }

}
