<?php

/**
 * @Author: h9471
 */

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\ExpressLineIcon;
use App\Models\ExpressLineModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Exceptions\AccidentException;

class ExpressLineIconService extends BaseService
{
    public function __construct(ExpressLineIcon $expressLineIcon)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $expressLineIcon;
        $this->query = $expressLineIcon->newQuery();
        $this->setFilterRules();
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
        validator($data, $this->rules())->validate();
        /** @var ExpressLineIcon $icon */
        $icon = new $this->model(
            [
                'name' => $data['name'],
                'icon' => $data['icon'],
            ]
        );

        return $icon->save();
    }

    /**
     * 简单列表
     */
    public function getSimpleExpressLineIconList()
    {
        return $this->model::select(['id', 'name', 'icon'])->get();
    }

    public function update(int $id, array $data)
    {
        validator($data, $this->rules())->validate();
        /** @var ExpressLineIcon $icon */
        $icon = $this->model::findOrFail($id);

        return $icon->update(
            [
                'name' => $data['name'],
                'icon' => $data['icon'],
            ]
        ) !== false;
    }

    /**
     * 设置成默认
     *
     * @param  int  $id
     * @return mixed
     */
    public function setDefault(int $id): mixed
    {
        /** @var ExpressLineIcon $icon */
        $icon = $this->model::findOrFail($id);

        return DB::transaction(function () use ($icon) {
            $this->model::where('is_default', 1)
                ->update(['is_default' => 0]);

            //将系统默认的切换成用户默认的
            ExpressLineModel::query()
                ->where('icon_id', 0)
                ->update(
                    ['icon_id' => $icon->id]
                );

            return $icon->update(
                [
                    'is_default' => true,
                ]
            ) !== false;
        });
    }

    /**
     * 删除
     *
     * @param  array  $id
     * @return bool
     * @throws Throwable
     */
    public function delete(array $id): bool
    {
        $icons = ExpressLineIcon::whereIn('id', $id)
            ->select('is_default')
            ->get()
            ->filter(function ($cost) {
                return $cost->is_default === 1;
            })->count();

        throw_if($icons, new AccidentException('默认图标不能被删除', Code::OPERATE_FAIL));

        return parent::delete($id);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'icon' => 'required|string|max:250',
        ];
    }
}
