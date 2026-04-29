<?php

namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\CompanyExpress;
use App\Models\CompanyExpressModel;
use App\Models\ReservedOrderNumberItem;
use App\Models\ReservedOrderNumberModel;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Vtiful\Kernel\Excel;

class ReservedOrderNumberService extends BaseService
{
    /**
     * @param ReservedOrderNumberModel $model
     */
    public function __construct(ReservedOrderNumberModel $model)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $model = $this->model::with(['items', 'express' => function ($query) {
            $query->select('id', 'name');
        }]);

        if (isset($this->formData['keyword'])) {
            $model = $model->where('batch', 'like', $this->formData['keyword'] . '%');
        }

        $size = $this->formData['size'] ?? 10;
        $res = $model->where('company_id', auth()->user()->company_id)
            ->orderByDesc('id')
            ->paginate($size)
            ->toArray();

        $data = $res['data'];
        unset($res['data']);
        $meta = $res;
        foreach ($data as &$item) {
            $item['total_numbers'] = count($item['items']);
            // 统计已使用单号数量
            $isUsedNumbers = array_filter($item['items'], function ($val) {
                return $val['is_used'];
            });
            $item['used_number'] = count($isUsedNumbers);
            // 统计已作废单号数量
            $isInvalidNumbers = array_filter($item['items'], function ($val) {
                return $val['is_invalid'];
            });
            $item['invalid_number'] = count($isInvalidNumbers);

            $item['is_available'] = $item['total_numbers'] - $item['used_number'] - $item['invalid_number'];

            if (count($item['items']) == 0) {
                $item['begin_number'] = '';
                $item['end_number'] = '';
            } else {
                $item['begin_number'] = $item['items'][0]['order_sn'];
                $index = count($item['items']) - 1;
                $item['end_number'] = $item['items'][$index]['order_sn'];
            }

            unset($item['items']);
        }

        return ['data' => $data, 'meta' => $meta];
    }

    /**
     * 添加预留单号
     * @return array
     * @throws \Throwable
     */
    public function store()
    {
        validator($this->formData, [
            'express_company_id' => 'required',
            'uuid' => 'required'
        ], [], [
            'express_company_id' => '发货快递公司',
            'uuid' => '预留单号文件'
        ])->validate();

        $res = ReservedOrderNumberItem::where('uuid', $this->formData['uuid'])->get()->toArray();

        throw_unless($res, new AccidentException('添加失败，预留单号文件不存在！', Code::OPERATE_FAIL));

        $saveData = [
            'batch' => $this->generateBatch(),
            'express_company_id' => $this->formData['express_company_id'],
            'company_id' => auth()->user()->company_id,
            'remark' => $this->formData['remark'] ?? ''
        ];

        DB::beginTransaction();
        try {
            $result = $this->model::create($saveData);

            $exceptionData = [];
            $is_exp = false;
            foreach ($res as $item) {
                try {
                    $updateRes = ReservedOrderNumberItem::where(['id' => $item['id'], 'reserved_order_number_id' => 0])
                        ->update([
                            'express_company_id' => $this->formData['express_company_id'],
                            'reserved_order_number_id' => $result->id
                        ]);

                    if (!$updateRes) {
                        throw new AccidentException('无更新数据', Code::OPERATE_FAIL);
                    }
                    $item['exp'] = 0;
                    $exceptionData[] = $item;
                } catch (\Exception) {
                    $item['exp'] = 1;
                    $exceptionData[] = $item;
                    $is_exp = true;
                }
            }
            if ($is_exp) {
                throw new AccidentException('', Code::OPERATE_FAIL);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ['data' => $exceptionData, 'exp' => 1];
        }

        $this->cacheReserved($this->formData['express_company_id']);
        return ['data' => $exceptionData, 'exp' => 0];
    }

    /**
     * 缓存预留单号
     * @param $express_company_id 快递公司id
     * @return void
     */
    public function cacheReserved($express_company_id)
    {
        $company_id = auth()->user()->id;
        $key = 'reserved_' . $company_id . '_' . $express_company_id;
        $count = Redis::scard($key);

        if ($count === 0) {
            $res = ReservedOrderNumberItem::where([
                'company_id' => $company_id,
                'express_company_id' => $express_company_id,
                'is_used' => ReservedOrderNumberItem::UNUSED,
                'is_invalid' => ReservedOrderNumberItem::STATUS_NORMAL
            ])
                ->select('order_sn')
                ->offset(0)
                ->limit(2000)
                ->get()
                ->toArray();

            $snArr = array_column($res, 'order_sn');
            Redis::sadd($key, ...$snArr);
        }
    }

    /**
     * 导出预留单号文件
     * @param UploadedFile $file
     * @return array
     * @throws Exception
     */
    public function import(UploadedFile $file)
    {
        $fileUuid = md5_file($file);

        // $saveData = [
        //     'batch' => $this->generateBatch(),
        //     'express_company_id' => $this->formData['express_company_id'],
        //     'company_id' => auth()->user()->company_id,
        //     'remark' => $this->formData['remark'] ?? ''
        // ];

        $res = ReservedOrderNumberItem::where(['uuid' => $fileUuid, 'reserved_order_number_id' => 0])->get()->toArray();
        if ($res) {
            return ['list' => $res, 'uuid' => $fileUuid];
        }

        $data = $this->parseData($file);

        DB::beginTransaction();
        try {
            // $result = $this->model::create($saveData);
            $data = $data->toArray();
            $data = array_column($data, 0);

            $inserData = [];
            foreach ($data as $item) {
                $inserData[] = [
                    'reserved_order_number_id' => 0,
                    'order_sn' => $item,
                    'express_company_id' => 0,
                    'company_id' => auth()->user()->company_id,
                    'uuid' => $fileUuid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            ReservedOrderNumberItem::insert($inserData);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new AccidentException('导入预留单号失败', Code::OPERATE_FAIL);
        }
        return ['list' => $inserData, 'uuid' => $fileUuid];
    }

    /**
     * @param UploadedFile $file
     * @return Collection
     * @throws Exception
     */
    protected function parseData(UploadedFile $file)
    {
        try {
            $config = ['path' => $file->getPath()];

            $excel = (new Excel($config))
                ->openFile($file->getFilename())
                ->openSheet()->setType([
                    0 => Excel::TYPE_STRING,
                ]);

            $items = collect([]);
            while (($row = $excel->nextRow()) !== null) {
                if (!$row[0]) {
                    break;
                }
                $items->push(collect($row));
            }
            //删除第二行说明性数据
            unset($items[0]);

            return $items;
        } catch (\Throwable $throwable) {
            throw new AccidentException('模板数据异常，请检查模板数据是否填写完整与正确', Code::OPERATE_FAIL);
        }
    }

    /**
     * 生成批次
     * @return string
     */
    protected function generateBatch()
    {
        $batch = $this->model::where('batch', 'like', date('Ymd') . '%')
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('id', 'desc')
            ->value('batch');

        if (empty($batch)) {
            return date('Ymd') . '-01';
        }

        $res = explode('-', $batch);

        $res[1] += 1;

        if (strlen($res[1]) === 1) {
            $res[1] = str_pad($res[1], 2, 0, STR_PAD_LEFT);
        }

        return $res[0] . '-' . $res[1];
    }

    /**
     * 将单号作废
     * @return bool|int
     * @throws Exception
     */
    public function isInvalid()
    {
        if (!isset($this->formData['ids'])) {
            throw new AccidentException('请选择单号', Code::OPERATE_FAIL);
        }

        if (!is_array($this->formData['ids'])) {
            throw new AccidentException('单号必须为一个数组', Code::OPERATE_FAIL);
        }

        $res = ReservedOrderNumberItem::whereIn('id', $this->formData['ids'])
            ->where('is_used', ReservedOrderNumberItem::IS_USED)
            ->get()->toArray();

        if (count($res) > 0) {
            throw new AccidentException('操作失败，选中的单号中有已使用的单号！', Code::OPERATE_FAIL);
        }

        return ReservedOrderNumberItem::whereIn('id', $this->formData['ids'])
            ->update(['is_invalid' => ReservedOrderNumberItem::IS_INVALID]);
    }

    /**
     * @param $id
     * @return array
     * @throws Exception
     */
    public function show($id)
    {
        $res = $this->model::with(['items', 'items.order:id,logistics_sn,order_sn', 'express' => function ($query) {
            $query->select('id', 'name');
        }])->where('id', $id)->first();

        if (!$res) {
            throw new AccidentException('预留单号不存在！', Code::OPERATE_FAIL);
        }

        $data = $res->toArray();

        $data['total_numbers'] = count($data['items']);
        // 统计已使用单号数量
        $isUsedNumbers = array_filter($data['items'], function ($val) {
            return $val['is_used'];
        });
        $data['used_number'] = count($isUsedNumbers);
        // 统计已作废单号数量
        $isInvalidNumbers = array_filter($data['items'], function ($val) {
            return $val['is_invalid'];
        });
        $data['invalid_number'] = count($isInvalidNumbers);

        $data['is_available'] = $data['total_numbers'] - $data['used_number'] - $data['invalid_number'];

        if (count($data['items']) == 0) {
            $item['begin_number'] = '';
            $item['end_number'] = '';
        } else {
            $data['begin_number'] = $data['items'][0]['order_sn'];
            $index = count($data['items']) - 1;
            $data['end_number'] = $data['items'][$index]['order_sn'];
        }

        return $data;
    }

    /**
     * @param $id
     * @return array
     */
    public function numberItems($id)
    {
        $model = ReservedOrderNumberItem::with('order:id,order_sn,logistics_sn')
            ->where('reserved_order_number_id', $id)
            ->where('is_invalid', ReservedOrderNumberItem::STATUS_NORMAL);

        if (isset($this->formData['keyword'])) {
            $model = $model->where('order_sn', 'like', trim($this->formData['keyword']) . '%');
        }

        $size = $this->formData['size'] ?? 10;
        $res = $model->paginate($size)->toArray();

        $data = $res['data'];
        unset($res['data']);

        return ['data' => $data, 'meta' => $res];
    }

    /**
     * 删除预留单号
     * @param $id
     * @return true
     * @throws Exception
     */
    public function delNumber($id)
    {
        $res = ReservedOrderNumberItem::where('reserved_order_number_id', $id)
            ->where('is_used', ReservedOrderNumberItem::IS_USED)
            ->get()
            ->toArray();

        if (count($res)) {
            throw new AccidentException('操作失败，预留单号已被使用', Code::OPERATE_FAIL);
        }

        DB::beginTransaction();
        try {

            ReservedOrderNumberItem::where('reserved_order_number_id', $id)->delete();
            $this->model::where('id', $id)->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new AccidentException('操作失败', Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 删除单个预留单号
     * @param $id
     * @return int
     */
    public function delItemNo($id)
    {
        return ReservedOrderNumberItem::where('id', $id)->delete();
    }

    /**
     * @return CompanyExpressModel[]
     */
    public function getExpressCompanyList()
    {
        $ids = $this->model::query()
            ->select('express_company_id')
            ->get()->pluck('express_company_id')
            ->flatten()->values()->all();

        return CompanyExpressModel::whereKey($ids)->select(['id', 'name'])->get();
    }

    /**
     * 根据快递公司 id 获取预留单号
     * @param int $expressCompanyId 快递公司id
     * @return string
     */
    public static function getReservedNo(int $expressCompanyId, int $companyId = null)
    {
        if (!$companyId) {
            $companyId = auth()->user()->id;
        }

        $key = 'reserved_' . $companyId . '_' . $expressCompanyId;

        $sn = Redis::spop($key);

        $res = ReservedOrderNumberItem::where([
            'order_sn' => $sn,
            'express_company_id' => $expressCompanyId,
            'company_id' => $companyId,
            'is_invalid' => ReservedOrderNumberItem::IS_INVALID
        ])->first();

        if ($res) {
            $sn = self::getReservedNo($expressCompanyId);
        }

        if (empty($sn)) {
            $result = ReservedOrderNumberItem::where([
                'company_id' => $companyId,
                'express_company_id' => $expressCompanyId,
                'is_used' => ReservedOrderNumberItem::UNUSED,
                'is_invalid' => ReservedOrderNumberItem::STATUS_NORMAL
            ])
                ->select('order_sn')
                ->offset(0)
                ->limit(2000)
                ->get()
                ->toArray();

            if (!count($result)) {
                return '';
            }

            $snArr = array_column($result, 'order_sn');
            Redis::sadd($key, ...$snArr);
            $sn = self::getReservedNo($expressCompanyId);
        }

        ReservedOrderNumberItem::where([
            'order_sn' => $sn,
            'express_company_id' => $expressCompanyId,
            'company_id' => $companyId,
        ])->update(['is_used' => ReservedOrderNumberItem::IS_USED]);

        return $sn;
    }
}
