<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:40
 */

namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\PackageProp;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PackagePropService extends BaseService
{
    use HasNameUniqueValidation;

    protected $orderBy = [
        'index' => 'asc',
    ];

    public function __construct(PackageProp $packageProp)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $packageProp;
        $this->query = $packageProp->newQuery();
        $this->setFilterRules();
    }

    /**
     * @return Builder[]|Collection
     */
    public function index(): Collection|array
    {
        $this->setFilter()->setOrderBy();

        return parent::all();
    }

    /**
     * 新建
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    public function add(array $data): bool
    {
        validator($data, $this->rules())->validate();

        $data['name'] = $data['name'] ?? $data['cn_name'];

        throw_if(
            $this->model::query()->where('name->zh_CN', $data['name'])->count('id'),
            new AccidentException('属性已存在', Code::OPERATE_FAIL)
        );

        $prop = new $this->model(
            [
                'cn_name' => $data['name'],
                'en_name' => $data['name'],
                'name' => $data['name'],
                'color' => $data['color'] ?? '',
                'font_color' => $data['font_color'] ?? '',
            ]
        );

        return $prop->save();
    }

    /**
     * 新建
     * @param  int  $id
     * @param  array  $data
     * @return bool
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(int $id, array $data): bool
    {
        validator($data, $this->rules())->validate();

        $prop = $this->model::query()->findOrFail($id);

        $data['name'] = $data['name'] ?? $data['cn_name'];

        throw_if(
            $this->model::query()
                ->whereKeyNot($id)
                ->where('name->zh_CN', $data['name'])
                ->count('id'),
            new AccidentException('属性已存在', Code::OPERATE_FAIL)
        );

       return $prop->update(
            [
                'cn_name' => $data['name'],
                'en_name' => $data['name'],
                'name' => $data['name'],
                'color' => $data['color'] ?? '',
                'font_color' => $data['font_color'] ?? '',
            ]
        ) !== false;
    }

    /**
     * @param $data
     * @return mixed
     * @throws ValidationException
     */
    public function sort($data): mixed
    {
        validator($data, $this->sortrules())->validate();

        return DB::transaction(function () use ($data) {
            foreach ($data as $datum) {
                $this->model::query()
                    ->findOrFail($datum['id'])
                    ->update(['index' => $datum['index']]);
            }

            return true;
        });
    }

    /**
     * @param array $id
     * @return bool
     * @throws Exception
     */
    public function delete(array $id): bool
    {
        $props = PackageProp::query()->with('expressLines')->whereKey($id)->get();

        foreach ($props as $prop) {
            if ($prop->expressLines->count()) {
                throw new AccidentException(sprintf(
                    '属性 %s 已被渠道 %s使用，请先取消关联',
                    $prop->name,
                    $prop->expressLines->pluck('name')->implode(',')
                ), Code::OPERATE_FAIL);
            }
        }

        info('删除属性', ['props' => $props->pluck('name')->flatten()->toArray(), 'user' => auth()->id()]);

        return parent::delete($id);
    }


    /**
     * 更新翻译字段
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function updateTranslateData(int $id, array $data): bool
    {
        validator($data, $this->translateRules())->validate();

        /** @var PackageProp $packageProp */
        $packageProp = $this->model::query()->findOrFail($id);

        $packageProp->setTranslation('cn_name', $data['language'], $data['name']);
        $packageProp->setTranslation('en_name', $data['language'], $data['name']);
        $packageProp->setTranslation('name', $data['language'], $data['name']);

        return $packageProp->save();
    }

    protected function translateRules(): array
    {
        return parent::translateRules() + [
            'props' => 'array',
            'props.*.id' => 'integer',
            'props.*.name' => 'string',
        ];
    }

    private function rules(): array
    {
        return [
            'cn_name' => 'required|string|max:25',
            'name' => 'sometimes|string|max:25',
            'color' => 'sometimes|string|max:50',
            'font_color' => 'sometimes|string|max:50',
        ];
    }
    public function sortrules(): array
    {
        return [
            '*.id' => 'required',
            '*.index' => 'required|integer',
        ];
    }
}
