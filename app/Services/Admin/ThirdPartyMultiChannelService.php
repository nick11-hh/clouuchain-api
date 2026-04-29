<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:40
 */

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\ExpressLineModel;
use App\Models\ExpressLineThirdPartyMultiChannel;
use App\Models\OrderDockingType;
use Exception;
use App\Exceptions\AccidentException;

class ThirdPartyMultiChannelService extends BaseService
{
    protected $filterRules = [
        'express_line_id' => ['=', 'express_line_id']
    ];

    protected $orderBy = ['id' => 'desc'];

    public function __construct(ExpressLineThirdPartyMultiChannel $article)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $article;
        $this->query = $article->newQuery();
        $this->setFilterRules();
    }

    /**
     * 列表
     * @return mixed
     */
    public function index()
    {
        $this->query->with(['expressLine:id,name', 'dockingCompany']);

        return $this->setFilter()->setOrderBy()->pageSize();
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

    public function store($data)
    {
        $payload = validator($data, $this->rules())->validate();

        throw_if(
            !ExpressLineModel::query()->whereKey($data['express_line_id'])->first(),
            new AccidentException('路线渠道不存在', Code::OPERATE_FAIL)
        );

        throw_if(
            !OrderDockingType::query()->where('type', $data['docking_type'])->first(),
            new AccidentException('落地配配置不存在', Code::OPERATE_FAIL)
        );

        return $this->model::query()->create($payload);
    }

    public function update($id, $data)
    {
        $payload = validator($data, $this->updateRules())->validate();

        $channel = $this->query->findOrFail($id);

        throw_if(
            !OrderDockingType::query()->where('type', $data['docking_type'])->first(),
            new AccidentException('落地配配置不存在', Code::OPERATE_FAIL)
        );

        return $channel->update($payload);
    }

    /**
     * 删除
     * @param array $id
     * @return bool
     */
    public function delete($id): bool
    {
        return $this->model::where('id', $id)->delete();
    }

    public function rules()
    {
        return [
            'express_line_id' => 'required|int',
            'docking_type' => 'required|int',
            'channel_code' => 'sometimes|nullable|string',
            'first_num' => 'required|int',
            'first_condition' => 'required|string|in:<,<=',
            'type' => 'required|int|in:1,2',
            'second_condition' => 'required|string|in:<,<=',
            'second_num' => 'required|int',
        ];
    }

    public function updateRules()
    {
        return [
            'docking_type' => 'required|int',
            'channel_code' => 'sometimes|nullable|string',
            'first_num' => 'required|int',
            'first_condition' => 'required|string|in:<,<=',
            'type' => 'required|int|in:1,2',
            'second_condition' => 'required|string|in:<,<=',
            'second_num' => 'required|int',
        ];
    }

}
