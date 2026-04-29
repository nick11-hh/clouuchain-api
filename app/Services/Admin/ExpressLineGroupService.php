<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:40
 */

namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\ExpressLineGroup;
use App\Models\ExpressLineGroupsModel;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ExpressLineGroupService extends BaseService
{
    use HasGroupOperation;

    protected $filterRules = [
        'name' => ['like', 'keyword'],
    ];

    protected $orderBy = ['id' => 'asc'];

    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new ExpressLineGroupsModel();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->withCount('expressLines');

        return $this->setFilter()->setOrderBy()->pageSize(20);
    }

    public function show($id)
    {
        return $this->query->withCount('expressLines')
            ->findOrFail($id);
    }

    /**
     * @param array $data
     * @return Builder|Model
     * @throws ValidationException
     */
    public function create(array $data): Model|Builder
    {
        $data = validator($data, $this->rules())->validate();

        return $this->model::query()->create([
            'name' => $data['name'],
            'only_for_group' => $data['only_for_group'] ?? 0,
            'only_for_stg' => $data['only_for_stg'] ?? 0,
        ]);
    }

    /**
     * @param $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function update($id, array $data): bool
    {
        $data = validator($data, $this->rules())->validate();

        return $this->model::query()->findOrFail($id)->update([
            'name' => $data['name'],
            'only_for_group' => $data['only_for_group'] ?? 0,
            'only_for_stg' => $data['only_for_stg'] ?? 0,
        ]);
    }

    /**
     * 删除
     * @param $id
     * @return bool
     * @throws Exception
     */
    public function destroy($id): bool
    {
        $count = $this->model::query()
            ->withCount('expressLines')->findOrFail($id)->express_lines_count;

        if ($count > 0) {
            throw new AccidentException('线路下包含渠道，不能删除！', Code::OPERATE_FAIL);
        }

        return $this->model::whereKey($id)->delete();
    }

    /**
     * 开关
     *
     * @param $id
     * @param bool $status
     * @return bool
     */
    public function groupsStatus($id, bool $status):bool
    {
        $setting = $this->model::findOrFail($id);
        return $setting->update(
                [
                    'enabled' => (int) $status,
                ]
            ) !== false;
    }

    /**
     * 复制
     *
     * @param $id
     * @param array $data
     * @return Builder|Model
     * @throws ValidationException
     */
    public function groupsCopy($id, array $data): Model|Builder
    {
        validator($data, ['name' => 'required|string|max:50'])->validate();
        $lineData = $this->model::findOrFail($id)->toArray();

        return $this->query->create([
            'name' => $data['name'],
        ]);
    }

    /**
     * @param $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function updateTranslateData($id, array $data): bool
    {
        validator($data, $this->translateRules())->validate();

        /** @var ExpressLineGroupsModel $expressLineGroup */
        $expressLineGroup = ExpressLineGroupsModel::query()->findOrFail($id);

        $expressLineGroup->setTranslations('name', [$data['language'] => $data['name']]);

        return $expressLineGroup->save();
    }

    protected function translateRules(): array
    {
        return array_merge(
            parent::translateRules(),
            [
                'name' => 'required|string|max:50',
            ]
        );
    }

    /**
     * @return string[]
     */
    protected function rules(): array
    {
        return [
            'name' => 'required',
            'only_for_group' => 'sometimes|nullable|in:0,1',
            'only_for_stg' => 'sometimes|nullable|in:0,1',
        ];
    }
}
