<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:40
 */

namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\ExpressLine;
use App\Models\ExpressLineModel;
use App\Models\ExpressLineRule;
use App\Models\ExpressLineRuleCondition;
use App\Models\ExpressLineVAS;
use App\Models\Model;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpressLineRuleService extends BaseService
{
    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new ExpressLineRule();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /**
     * @param int $eplId
     * @return Builder[]|Collection
     */
    public function ruleIndex(int $eplId): Collection|array
    {
        $this->query->where('express_line_id', $eplId)
            ->with([
                'conditions',
                'regions',
                'conditions.userAddressTags',
                'conditions.remoteTypes'
            ]);

        return $this->all();
    }

    /**
     * @param int $eplId
     * @param array $data
     * @return bool
     */
    public function updateRemark(int $eplId, array $data): bool
    {
        /** @var ExpressLineModel $epl */
        $epl = ExpressLineModel::query()->findOrFail($eplId);

        if ($data['remark_trans'] ?? []) {
            $epl->setTranslations('rule_remark', $data['remark_trans']);
        }

        return $epl->save();
    }

    /**
     * @param int $eplId
     * @return array
     */
    public function getRemark(int $eplId): array
    {
        /** @var ExpressLineModel $epl */
        $epl = ExpressLineModel::query()->findOrFail($eplId);

        return ['remark_trans' => $epl->getTranslations('rule_remark')];
    }


    /**
     * @param $id
     * @return mixed
     */
    public function show($id): mixed
    {
        $this->query->with(['conditions', 'regions']);

        return parent::show($id);
    }

    /**
     * @param int $eplId
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function create(int $eplId, array $data): bool
    {
        $data = validator($data, $this->rules())->validate();

        DB::transaction(function () use ($eplId, $data) {
            $this->checkConditions($data);

            /** @var ExpressLineRule $rule */
            $rule = $this->model::query()->create([
                'express_line_id' => $eplId,
                'name' => $data['name'],
                'type' => $data['type'],
                'charge_mode' => $data['charge_mode'] ?? ExpressLineRule::CHARGE_MODE_FIXED,
                'value' => ($data['value'] ?? 0) * 100,
                'min_charge' => ($data['min_charge'] ?? 0) * 100,
                'max_charge' => ($data['max_charge'] ?? 0) * 100,
                'notice' => $data['notice'] ?? '',
                'is_and' => $data['is_and'] ?? 0,
                'condition' => $data['condition'] ?? '',
                'result' => $data['result'] ?? '',
                'else_result' => $data['else_result'] ?? '',
            ]);

            $this->updateTranslation(['name', 'notice'], $data, $rule);

            $rule->regions()->attach($data['region_ids']);

            $this->createRuleConditions($data['conditions'], $rule);
        });

        return true;
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function update(int $id, array $data): bool
    {
        $data = validator($data, $this->rules())->validate();

        DB::transaction(function () use ($id, $data) {
            /** @var ExpressLineRule $rule */
            $rule = $this->model::query()->findOrFail($id);

            $this->checkConditions($data);

            $rule->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'charge_mode' => $data['charge_mode'] ?? ExpressLineRule::CHARGE_MODE_FIXED,
                'value' => ($data['value'] ?? 0) * 100,
                'min_charge' => ($data['min_charge'] ?? 0) * 100,
                'max_charge' => ($data['max_charge'] ?? 0) * 100,
                'notice' => $data['notice'] ?? '',
                'is_and' => $data['is_and'] ?? 0,
                'condition' => $data['condition'] ?? '',
                'result' => $data['result'] ?? '',
                'else_result' => $data['else_result'] ?? '',
            ]);

            $this->updateTranslation(['name', 'notice'], $data, $rule);

            $rule->regions()->sync($data['region_ids']);

            $rule->conditions->each(function ($condition){
                $condition->userAddressTags()->detach();
            });

            $rule->conditions()->delete();

            $this->createRuleConditions($data['conditions'], $rule);
        });

        return true;
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function destroy(int $id): mixed
    {
        return DB::transaction(function () use ($id) {
            /** @var ExpressLineRule $rule */
            $rule = $this->model::query()->findOrFail($id);

            $rule->regions()->sync([]);
            // 取消关联地址标签
            $rule->conditions->each(function ($condition) {
                $condition->userAddressTags()->detach();
            });

            $rule->conditions()->delete();

            return $rule->delete();
        });
    }

    /**
     * @return array|string
     */
    public function conditions(): array|string
    {
        return collect(ExpressLineRuleCondition::rules())->map(function ($value, $key) {
            return [
                'id' => $key,
                'value' => $value,
            ];
        })->values()->all();
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateBaseConfig(int $id, array $data): bool
    {
        /** @var ExpressLineModel $exp */
        $exp = ExpressLineModel::query()->findOrFail($id);

        return $exp->update([
            'rule_fee_mode' => $data['rule_fee_mode'] ?? 0,
            'max_rule_fee' => ($data['max_rule_fee'] ?? 0) * 100,
        ]);
    }

    /**
     * @param int $id
     * @return array
     */
    public function getAdvanceConditions(int $id): array
    {
        $conditions = ['箱数', '计费重量', '运费', '会员等级', '限制出仓', '限制下单'];

        $expressLineService = ExpressLineVAS::query()
            ->where('express_line_id', $id)
            ->select('name')
            ->get()->pluck('name')->toArray();

        return array_merge($conditions, $expressLineService);
    }

    /**
     * @param array $data
     * @return void
     * @throws Exception
     */
    protected function checkConditions(array $data)
    {
        foreach (Arr::only($data, ['condition', 'result', 'else_result']) as $value) {
            if (Str::contains($value, ['\'', '"', '\\', '[', ']', '{', '}', '_', ':', ';', '`', ',',])) {
                throw new AccidentException('条件中不能包含不支持的特殊字符', Code::OPERATE_FAIL);
            }

            if (preg_match('/[A-Za-z@#$^?]+/', $value)) {
                throw new AccidentException('条件中不能包含英文字符', Code::OPERATE_FAIL);
            }
        }
    }

    /**
     * @param $rule
     * @param $v
     * @return mixed
     */
    protected function createCondition($rule, $v): mixed
    {
        $data = [
            'express_line_id' => $rule['express_line_id'],
            'param' => $v['param'],
            'comparison' => 'contains',
            'value' => 0,
        ];

        return $rule->conditions()->create($data);
    }

    /**
     * @param array|string $columns
     * @param array $data
     * @param Model $model
     * @return bool
     */
    protected function updateTranslation(array|string $columns, array $data, Model $model): bool
    {
        $columns = is_array($columns) ? $columns : [$columns];

        collect($columns)->each(function ($column) use ($data, $model) {
            if ($data[$column.'_translations'] ?? []) {
                $translations = collect($data[$column.'_translations'])
                    ->filter(fn($value) => $value)
                    ->all();

                if ($translations) {
                    $model->setTranslations($column, $translations);
                }
            }
        });

        return $model->save();
    }

    /**
     * @return string[]
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'region_ids' => 'required|array',
            'is_and' => 'required|bool',
            'conditions' => 'required|array|min:1',
            'conditions.*.param' => 'required|int',
            'conditions.*.comparison' => 'required|string',
            'conditions.*.value' => 'required_unless:conditions.*.param,16,17|numeric',
            'conditions.*.tag_ids' => 'required_if:conditions.*.param,16|nullable|array',
            'conditions.*.remote_ids' => 'required_if:conditions.*.param,17|nullable|array',
            'type' => 'required|integer|in:1,2,3,4,5',
            'charge_mode' => 'required_unless:type,4,5|nullable|in:1,2,3',
            'value' => 'required_unless:type,4,5|nullable|numeric|gte:0',
            'min_charge' => 'nullable|numeric|gte:0',
            'max_charge' => 'nullable|numeric|gte:0',
            'notice' => 'required_if:type,4|nullable|string|max:180',
            'name_translations' => 'sometimes|nullable|array',
            'notice_translations' => 'sometimes|nullable|array',
            'condition' => 'sometimes|nullable|string',
            'result' => 'sometimes|nullable|string',
            'else_result' => 'sometimes|nullable|string',
        ];
    }

    /**
     * @param $conditions
     * @param ExpressLineRule $rule
     * @return void
     */
    protected function createRuleConditions($conditions, ExpressLineRule $rule): void
    {
        collect($conditions)->each(function ($v) use ($rule) {
            switch ($v['param']) {
                case ExpressLineRuleCondition::PARAM_ADDRESS_TAG:
                    $condition = $this->createCondition($rule, $v);

                    $condition->userAddressTags()->attach($v['tag_ids']);
                    break;
                case ExpressLineRuleCondition::PARAM_REMOTE_AREA:
                    $condition = $this->createCondition($rule, $v);

                    $condition->remoteTypes()->attach($v['remote_ids']);
                    break;
                default:
                    $data = [
                        'express_line_id' => $rule['express_line_id'],
                        'param' => $v['param'],
                        'comparison' => $v['comparison'],
                        'value' => $v['value'] * 1000,
                    ];

                    $rule->conditions()->create($data);
            }
        });
    }
}
