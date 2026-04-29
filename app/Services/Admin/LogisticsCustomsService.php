<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\LogisticsCustomsModel;
use Exception;
use App\Exceptions\AccidentException;

class LogisticsCustomsService extends BaseService
{
    public function __construct(LogisticsCustomsModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        return parent::index();
    }

    public function store()
    {
        validator($this->formData, $this->rules(), [], [
            'name'       => '自定义名称',
            'cn_name'    => '中文名称',
            'en_name'    => '英文名称',
            'unit_price' => '申报金额',
            'weight'     => '申报重量',
        ])->validate();

        throw_if(
            $this->model::where('name', $this->formData['name'])->first(),
            new AccidentException('操作失败，自定义名称已存在', Code::OPERATE_FAIL)
        );

        $data = $this->model::init($this->formData);
        try {
            $this->model::create($data);
        } catch (Exception $e) {
            logger('创建报关信息失败：'.$e->getMessage());
            throw new AccidentException('创建报关信息失败', Code::OPERATE_FAIL);
        }

        return true;
    }

    public function update($id)
    {
        validator($this->formData, $this->rules(), [], [
            'name'       => '自定义名称',
            'cn_name'    => '中文名称',
            'en_name'    => '英文名称',
            'unit_price' => '申报金额',
            'weight'     => '申报重量',
        ])->validate();

        throw_if(
            $this->model::where('name', $this->formData['name'])->where('id', '<>', $id)->first(),
            new AccidentException('操作失败，自定义名称已存在', Code::OPERATE_FAIL)
        );

        $data = $this->model::init($this->formData);

        return $this->model::where('id', $id)->update($data);
    }

    public function del($id)
    {
        return $this->model::where('id', $id)->delete();
    }

    public function rules()
    {
        return [
            'name'       => 'required',
            'cn_name'    => 'required',
            'en_name'    => 'required',
            'unit_price' => 'required',
            'weight'     => 'required',
        ];
    }
}
