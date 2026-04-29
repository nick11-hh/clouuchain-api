<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\OrderTagsModel;
use App\Models\AdminOperationLog;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class OrderTagsService extends BaseService
{
    public $filterRules = [
        'name' => ['like', 'keyword'],
    ];

    public function __construct()
    {
        $this->model    = new OrderTagsModel();
        $this->formData = request()->all();
        $this->query    = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        return parent::index();
    }

    public function show($id)
    {
        return $this->model::query()->findOrFail($id);
    }

    /**
     * 新增
     * @return mixed
     */
    public function store()
    {
        validator($this->formData, $this->rules())->validate();

        return DB::transaction(function () {
            $data = $this->model::init($this->formData);
            $tag = $this->model::query()->create($data);

            // 记录操作日志
            AdminOperationLog::record(
                AdminOperationLog::ACTION_CREATE,
                AdminOperationLog::MODULE_SETTING,
                $data,
                $tag->id
            );

            return $tag;
        });
    }

    public function update($id)
    {
        validator($this->formData, $this->rules())->validate();

        return DB::transaction(function () use ($id) {
            $tag = $this->model::query()->findOrFail($id);
            $oldData = $tag->toArray();

            $data = $this->model::init($this->formData);
            $result = $tag->update($data);

            // 记录操作日志
            AdminOperationLog::record(
                AdminOperationLog::ACTION_UPDATE,
                AdminOperationLog::MODULE_SETTING,
                [
                    'old_data' => $oldData,
                    'new_data' => $data
                ],
                $id
            );

            return $result;
        });
    }

    public function deletes($params)
    {
        if (empty($params['ids'])) throw new AccidentException('请选择需要删除的数据', Code::OPERATE_FAIL);

        return DB::transaction(function () use ($params) {
            $list = $this->model::query()->with('mappings')->whereIn('id', $params['ids'])->get();

            $error = [];
            $deletedData = [];

            $list->each(function ($item) use (&$error, &$deletedData) {
                if ($item->order) {
                    $error[] = $item->name;
                } else {
                    $deletedData[] = $item->toArray();
                    $item->delete();
                }
            });

            if (!empty($error)) {
                throw new AccidentException('Failed to delete, tag already in use: ' . implode(',', $error), Code::OPERATE_FAIL);
            }

            // 记录操作日志
            AdminOperationLog::record(
                AdminOperationLog::ACTION_DELETE,
                AdminOperationLog::MODULE_SETTING,
                ['deleted_tags' => $deletedData]
            );

            return true;
        });
    }

    /**
     * 更新排序
     */
    public function sort($params)
    {
        validator($params, $this->sortRules())->validate();

        $tags = $params['tags'];
        return DB::transaction(function () use ($tags) {
            foreach ($tags as $tag) {
                $this->model::query()
                            ->findOrFail($tag['id'])
                            ->update(['sort' => $tag['sort']]);
            }

            return true;
        });
    }


    /**
     * 更新翻译字段
     */
    public function updateTranslate(int $id, array $params): bool
    {
        validator($params, $this->translateRules())->validate();

        $tag = $this->model::query()->findOrFail($id);

        $tag->setTranslation('name', $params['language'], $params['name']);

        return $tag->save();
    }

    protected function translateRules(): array
    {
        return parent::translateRules() + [
                'name' => 'required|string',
            ];
    }

    public function sortRules(): array
    {
        return [
            'tags' => 'array',
            'tags.*.id' => 'required|integer',
            'tags.*.sort' => 'required|integer',
        ];
    }

    protected function rules()
    {
        return [
            'name'        => 'required|string',
            'sort'        => 'sometimes|nullable|int',
            'color'       => 'sometimes|nullable|string',
            'font_color'  => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
        ];
    }

}
