<?php

/**
 * 公司属性配置Service
 */
namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\HighValueInsuranceConfig;
use App\Models\CompanyProp;
use App\Models\InventoryItem;
use App\Models\ProhibitedWord;
use App\Models\UnpackingConfig;
use App\Models\WorkOrderType;
use Exception;
use Illuminate\Support\Arr;

class CompanyPropService extends BaseService
{
    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
    }

    /**
     * 获取拆包清点配置
     *
     * @return array
     */
    public function getUnpackingAndInventoryConfiguration(): array
    {
        $configuration = UnpackingConfig::query()->first();

        if (empty($configuration)) {
            $items = InventoryItem::query()->select(['name', 'code'])->get()->map(function ($item) {
                return [
                    'name' => $item->name,
                    'code' => $item->code,
                    'status' => 1,
                ];
            })->toArray();

            $spuStatus = CompanyProp::where('type', CompanyProp::ENABLE_PACKAGE_SPU)
                ->get()
                ->first();

            return [
                'items' => $items,
                'default' => [
                    'prop_id' => null,
                    'length' => null,
                    'width' => null,
                    'height' => null,
                    'weight' => null,
                    'name' => '',
                    'qty' => null,
                    'category' => null,
                    'price' => null,
                    'goods_status' => 0,
                ],
                'require_fields' => [],
                'spu_enabled' => (int) ($spuStatus !== null ? $spuStatus->prop : 0)
            ];
        } else {
            $inventoryItems = $configuration->inventory_items;
            $items = InventoryItem::query()->select(['name', 'code'])->get()->map(function ($item) use ($inventoryItems) {
                return [
                    'name' => $item->name,
                    'code' => $item->code,
                    'status' => Arr::first($inventoryItems, function ($value) use ($item) {
                        return $value['name'] == $item->name;
                    })['status'] ?? 1,
                ];
            })->toArray();

            $default = [
                'prop_id' => $configuration->prop_id,
                'length' => $configuration->length,
                'width' => $configuration->width,
                'height' => $configuration->height,
                'weight' => $configuration->weight,
                'name' => $configuration->name,
                'qty' => $configuration->qty,
                'category' => $configuration->category,
                'price' => $configuration->price,
                'goods_status' => $configuration->goods_status,
            ];

            return [
                'items' => $items,
                'default' => $default,
                'require_fields' => $configuration->require_fields,
                'spu_enabled' => $configuration->spu_enabled,
            ];
        }
    }

    /**
     * 更新或创建拆包清点配置
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|int
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateUnpackingAndInventoryConfig($data)
    {
        validator($data, $this->inventoryRules(), [], $this->InventoryColumnRules())->validate();

        $configuration = UnpackingConfig::query()->first();

        if (empty($configuration)) {
            $base = InventoryItem::query()->select(['name'])->get()->map(function ($item) use ($data) {
                return [
                    'name' => $item->name,
                    'status' => Arr::first($data['items'], function ($value) use ($item) {
                        return $value['name'] == $item->name;
                    })['status'] ?? 0,
                ];
            })->toArray();

            return UnpackingConfig::query()->create([
                'inventory_items' => $base,
                'prop_id' => $data['prop_id'] ?? null,
                'length' => $data['length'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'weight' => $data['weight'] ?? null,
                'name' => $data['name'] ?? '',
                'qty' => $data['qty'] ?? 0,
                'category' => $data['category'] ?? null,
                'price' => $data['price'] ?? null,
                'goods_status' => $data['goods_status'] ?? null,
                'status' => 1,
                'require_fields' => $data['require_fields'] ?? [],
                'spu_enabled' => $data['spu_enabled'] ?? 0,
            ]);
        } else {
            $base = InventoryItem::query()->select(['name'])->get()->map(function ($item) use ($data) {
                return [
                    'name' => $item->name,
                    'status' => Arr::first($data['items'] ?? [], function ($value) use ($item) {
                            return $value['name'] == $item->name;
                        })['status'] ?? 0,
                ];
            })->toArray();

            return UnpackingConfig::query()
                ->whereKey($configuration->id)
                ->update([
                    'inventory_items' => $base,
                    'prop_id' => $data['prop_id'] ?? null,
                    'length' => $data['length'] ?? null,
                    'width' => $data['width'] ?? null,
                    'height' => $data['height'] ?? null,
                    'weight' => $data['weight'] ?? null,
                    'name' => $data['name'] ?? '',
                    'qty' => $data['qty'] ?? 0,
                    'category' => $data['category'] ?? null,
                    'price' => $data['price'] ?? null,
                    'goods_status' => $data['goods_status'] ?? null,
                    'require_fields' => $data['require_fields'] ?? [],
                    'spu_enabled' => $data['spu_enabled'] ?? 0,
                ]);
        }
    }

    protected function inventoryRules()
    {
        return [
            'items' => 'required|array',
            'items.*.name' => 'required|string',
            'items.*.status' => 'required|in:0,1',
            'prop_id' => 'nullable|int',
            'length' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'name' => 'nullable|string',
            'qty' => 'nullable|int',
            'category' => 'nullable|array',
            'category.id' => 'nullable|int',
            'category.name' => 'nullable|string',
            'price' => 'nullable|numeric',
            'goods_status' => 'nullable|in:0,1',
            'spu_enabled' => 'sometimes|nullable|int',
            'require_fields' => 'sometimes|nullable|array',
            'require_fields.*.id' => 'sometimes|nullable|string',
            'require_fields.*.status' => 'sometimes|nullable|int',
        ];
    }

    protected function InventoryColumnRules()
    {
        return [
            'items' => '清点项目',
            'prop_id' => '货物属性',
            'length' => '长度',
            'width' => '宽度',
            'height' => '高度',
            'weight' => '货品重量',
            'name' => '货品名称',
            'qty' => '货品数量',
            'category' => '货品分类',
            'price' => '货品价格',
            'goods_status' => '货物状态',
            'require_fields' => '必填项目'
        ];
    }

    /**
     * 获取违禁词配置
     * @return array
     */
    public function getProhibitedWordsConfig()
    {
        $config = ProhibitedWord::query()->first();

        if (empty($config)) {
            return [
                'id' => 0,
                'prohibited_words' => '',
                'match_type' => 0
            ];
        } else {
            return [
                'id' => $config->id,
                'prohibited_words' => $config->prohibited_words,
                'match_type' => $config->match_type
            ];
        }
    }

    /**
     * 更新或创建违禁词配置
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|int
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateProhibitedWordsConfig($data)
    {
        validator($data, $this->prohibitedWordsRules(), [], $this->prohibitedWordsColumnRules())->validate();

        $config = ProhibitedWord::query()->first();
        // 防止有中文的逗号， 统一更换为英文逗号
        if (!empty($data['prohibited_words'])) {
            $data['prohibited_words'] = strtr($data['prohibited_words'], ['，' => ',']);
        }

        if (empty($config)) {
            return ProhibitedWord::query()->create([
                'prohibited_words' => $data['prohibited_words'],
                'match_type' => $data['match_type'] ?? 0
            ]);
        } else {
            return ProhibitedWord::query()
                ->whereKey($config->id)
                ->update([
                    'prohibited_words' => $data['prohibited_words'],
                    'match_type' => $data['match_type'] ?? 0
                ]);
        }
    }

    protected function prohibitedWordsRules()
    {
        return [
            'prohibited_words' => 'nullable|string',
            'match_type' => 'nullable|in:0,1'
        ];
    }

    protected function prohibitedWordsColumnRules()
    {
        return [
            'prohibited_words' => '违禁词',
            'match_type' => '匹配类型'
        ];
    }

    /**
     * 获取工单类型配置
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getWordOrderTypeConfig()
    {
        $query = WorkOrderType::query()->with(['creator:id,name', 'assign:id,name']);
        if (isset($this->formData['type'])) {
            $query->where('type', $this->formData['type']);
        }

        return $query->where('is_del', 0)->orderByDesc('id')->get();
    }

    /**
     * 获取工单类型详情
     * @param $id
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null
     */
    public function getWordOrderTypeConfigById($id)
    {
        return WorkOrderType::query()->findOrFail($id);
    }

    /**
     * 创建工单类型配置
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws Exception
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createWordOrderTypeConfig($data)
    {
        validator($data, [
            'name' => 'required|string',
            'remark' => 'nullable|string'
        ], [], [
            'name' => '工单类型',
            'remark' => '备注'
        ])->validate();

        $config = WorkOrderType::query()->where(['name' => $data['name'], 'is_del' => 0, 'type' => 1])->first();
        if (!empty($config)) {
            throw new AccidentException('当前工单类型已存在, 请更换!', Code::OPERATE_FAIL);
        }

        return WorkOrderType::query()->create([
            'name' => $data['name'],
            'remark' => $data['remark'] ?? '',
            'is_del' => 0
        ]);
    }

    /**
     * 修改工单类型
     * @param $id
     * @param $data
     * @return int
     * @throws Exception
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateWordOrderTypeConfig($id, $data)
    {
        validator($data, [
            'name' => 'required|string',
            'remark' => 'nullable|string'
        ], [], [
            'name' => '工单类型',
            'remark' => '备注'
        ])->validate();

        $workOrderType = WorkOrderType::query()->findOrFail($id);
        if ($workOrderType->type == 2) {
            throw new AccidentException('当前工单为系统工单, 不可修改!', Code::OPERATE_FAIL);
        }

        $config = WorkOrderType::query()->where('id', '!=', $id)->where(['name' => $data['name'], 'is_del' => 0, 'type' => 1])->first();
        if (!empty($config)) {
            throw new AccidentException('当前工单类型已存在, 请更换!', Code::OPERATE_FAIL);
        }

        return WorkOrderType::query()->whereKey($id)->update([
            'name' => $data['name'],
            'remark' => $data['remark'] ?? '',
        ]);
    }

    /**
     * 删除工单类型
     * @param $id
     * @return bool|int
     */
    public function delWordOrderTypeConfig($id)
    {
        $config = WorkOrderType::query()->findOrFail($id);

        return $config->update(['is_del' => 1]);
    }

    /**
     * 设置工单类型状态
     * @param $id
     * @param $status
     * @return bool|int
     */
    public function setWordOrderTypeConfigStatus($id, $status)
    {
        $config = WorkOrderType::query()->findOrFail($id);

        return $config->update(['status' => $status]);
    }

    /**
     * 修改系统工单类型数据
     * @param $id
     * @param $data
     * @return bool|int
     * @throws Exception
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateSystemWordOrderTypeConfig($id, $data)
    {
        validator($data, [
            'priority' => 'required|int',
            'creator_id' => 'required|int',
            'assigned_by' => 'required|int',
            'copy_to' => 'nullable|array'
        ], [], [
            'priority' => '优先级',
            'creator_id' => '默认创建人',
            'assigned_by' => '默认指派人',
            'copy_to' => '抄送'
        ])->validate();

        $config = WorkOrderType::query()->findOrFail($id);
        if ($config->type != 2){
            throw new AccidentException('当前工单类型不是系统工单，不可在此处修改！', Code::OPERATE_FAIL);
        }

        return $config->update([
            'priority' => $data['priority'],
            'creator_id' => $data['creator_id'],
            'assigned_by' => $data['assigned_by'],
            'copy_to' => $data['copy_to'] ?? []
        ]);
    }

    /**
     * 设置高货值创建工单配置
     * @param $data
     * @return bool|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|int
     * @throws \Illuminate\Validation\ValidationException
     */
    public function setHighValueInsuranceConfig($data)
    {
        validator($data, [
            'enabled' => 'required|int',
            'value' => 'required|numeric',
        ], [], [
            'enabled' => '状态',
            'value' => '临界价值',
        ])->validate();
        $config = HighValueInsuranceConfig::query()->first();
        if ($config) {
            // 即已存在高货值配置
            return $config->update([
                'enabled' => $data['enabled'] ?? 0,
                'value' => $data['value'] ?? 0,
            ]);
        } else {
            // 即不存在高货值配置
            $workOrderType = WorkOrderType::query()->where('service_type', 5)->first();// 高货值工单配置

            return HighValueInsuranceConfig::query()->create([
                'enabled' => $data['enabled'],
                'value' => $data['value'],
                'work_order_type_id' => $workOrderType->id,
            ]);
        }
    }

    /**
     * 获取高货值工单配置
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getHighValueInsuranceConfig()
    {
        return HighValueInsuranceConfig::query()->with('workOrderType')->first();
    }
}
