<?php

/**
 * @Author: h9471
 * @Created: 2021/07/29 11:40
 */

namespace App\Services\Admin;

use App\Models\ExpressLineGroupsModel;
use App\Models\SalePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalePriceService extends BaseService
{
    use HasStatusSetting;

    protected $filterRules = [
        'name' => ['like', 'keyword'],
    ];

    protected $orderBy = ['index' => 'asc'];

    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new SalePrice();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /**
     * 列表
     * @return LengthAwarePaginator
     */
    public function index(): LengthAwarePaginator
    {
        $this->query->with([
            'expressLines:id,name',
            'users:id,name',
            'userGroups:id,group_name',
            'userTags:id,name',
            'memberLevels:id,name',
        ]);

        return parent::index();
    }

    /**
     * 详细
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        return $this->model::findOrFail($id);
    }

    /**
     * 删除
     *
     * @param int $id
     * @return bool
     */
    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            /** @var SalePrice $sp */
            $sp = SalePrice::query()->findOrFail($id);

            $sp->expressLines()->detach();
            $sp->users()->detach();
            $sp->userGroups()->detach();
            $sp->memberLevels()->detach();
            $sp->userTags()->detach();

            return $sp->delete();
        });
    }

    /**
     * 更新
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function update(int $id, array $data): bool
    {
        $data = validator($data, $this->rules())->validate();

        DB::transaction(function () use ($id, $data) {
            /** @var SalePrice $salePrice */
            $salePrice = $this->model::query()->findOrFail($id);

            $salePrice->update([
                'name' => $data['name'],
                'scope' => $data['scope'],
                'index' => $data['index'] ?? 1,
                'discount' => $data['discount'],
                'discount_type' => $data['discount_type'],
                'effect_at' => $data['effect_at'],
                'expire_at' => $data['expire_at'],
            ]);

            $this->sync($salePrice, $data);

            return true;
        });

        return true;
    }

    /**
     * 新建
     *
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function create(array $data): bool
    {
        $data = validator($data, $this->rules())->validate();

        DB::transaction(function () use ($data) {
            /** @var SalePrice $salePrice */
            $salePrice = $this->model::query()
                ->create([
                    'name' => $data['name'],
                    'scope' => $data['scope'],
                    'index' => $data['index'] ?? 1,
                    'discount' => $data['discount'],
                    'discount_type' => $data['discount_type'],
                    'effect_at' => $data['effect_at'],
                    'expire_at' => $data['expire_at'],
                ]);

            $this->attach($salePrice, $data);

            return true;
        });

        return true;
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function copy(int $id, array $data): bool
    {
        $data = validator($data, $this->copyRule())->validate();

        DB::transaction(function () use ($id, $data) {
            /** @var SalePrice $salePrice */
            $salePrice = $this->model::query()->findOrFail($id);

            $data = collect($salePrice->getAttributes())
                ->reject(fn ($value, $key) => $key === 'id')
                ->merge($data)
                ->all();

            ($new = new SalePrice($data))->save();

            $new->users()->attach($salePrice->users->modelKeys());
            $new->userGroups()->attach($salePrice->userGroups->modelKeys());
            $new->memberLevels()->attach($salePrice->memberLevels->modelKeys());
            $new->expressLines()->attach($salePrice->expressLines->modelKeys());
            $new->userTags()->attach($salePrice->userTags->modelKeys());

            return true;
        });

        return true;
    }

    /**
     * @return Builder[]|Collection
     */
    public function expressGroupList()
    {
        return ExpressLineGroupsModel::query()
            ->with('expressLines:id,name,group_id')
            ->select(['id', 'name'])
            ->get();
    }

    /**
     * @param SalePrice $salePrice
     * @param array $data
     * @return void
     */
    protected function sync(SalePrice $salePrice, array $data)
    {
        if ((int) $data['scope']) {
            $salePrice->userGroups()->sync($data['group_ids'] ?? []);
            $salePrice->memberLevels()->sync($data['level_ids'] ?? []);
            $salePrice->users()->sync($data['user_ids'] ?? []);
            $salePrice->userTags()->sync($data['tag_ids'] ?? []);
        } else {
            $salePrice->userGroups()->detach();
            $salePrice->memberLevels()->detach();
            $salePrice->users()->detach();
            $salePrice->userTags()->detach();
        }

        $salePrice->expressLines()->sync($data['express_line_ids'] ?? []);
    }

    /**
     * @param SalePrice $salePrice
     * @param array $data
     * @return void
     */
    protected function attach(SalePrice $salePrice, array $data)
    {
        if ((int) $data['scope']) {
            $salePrice->userGroups()->attach($data['group_ids'] ?? []);
            $salePrice->memberLevels()->attach($data['level_ids'] ?? []);
            $salePrice->users()->attach($data['user_ids'] ?? []);
            $salePrice->userTags()->attach($data['tag_ids'] ?? []);
        }

        $salePrice->expressLines()->attach($data['express_line_ids'] ?? []);
    }

    /**
     * @return string[]
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'scope' => 'required|integer|in:0,1,2,3',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'express_line_ids' => 'required|array',
            'discount' => 'required|numeric|gt:0',
            'discount_type' => 'required|in:1,2',
            'effect_at' => 'required|date',
            'expire_at' => 'required|date|before:2038-01-01',
            'index' => 'sometimes|nullable|integer|gte:0',
            'user_ids' => 'nullable|array',
            'group_ids' => 'nullable|array',
            'level_ids' => 'nullable|array',
            'tag_ids' => 'nullable|array',
        ];
    }

    /**
     * @return string[]
     */
    public function copyRule()
    {
        return [
            'name' => 'required|string|max:50',
        ];
    }
}
