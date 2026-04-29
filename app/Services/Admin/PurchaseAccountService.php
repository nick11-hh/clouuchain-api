<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\PurchaseAccountModel;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class PurchaseAccountService extends BaseService
{
    public $filterRules = [
        'state'     => ['=', 'state'],
    ];

    public function __construct(PurchaseAccountModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        if($this->formData['type'] == 1) {
            $this->query->where('account_name', 'like', '%'.$this->formData['keyword'].'%');
        } elseif ($this->formData['type'] == 2) {
            $this->query->where('name', 'like', '%'.$this->formData['keyword'].'%');
        }
        return parent::index();
    }

    public function getEnableAll()
    {
        $this->query->where('enable', 1);

        return parent::index();
    }

    public function store()
    {
        validator($this->formData, [
            'account_name' => 'required',
            'name'         => 'required',
            'platform'     => 'required',
            'remark'       => 'max:50'
        ], [], [
            'account_name' => '账号名称',
            'name'         => '用户名',
            'platform'     => '平台',
            'remark'       => '备注'
        ])->validate();

        throw_if(
            $this->model::where(['account_name' => $this->formData['account_name'], 'platform' => $this->formData['platform']])->first(),
            new AccidentException('同一个平台的账号名称已经存在', Code::OPERATE_FAIL)
        );

        $data = $this->model::init($this->formData);
        $this->model::create($data);

        return true;
    }

    public function update($id)
    {
        validator($this->formData, [
            'account_name' => 'required',
            'name'         => 'required',
            'platform'     => 'required',
            'remark'       => 'max:50'
        ], [], [
            'account_name' => '账号名称',
            'name'         => '用户名',
            'platform'     => '平台',
            'remark'       => '备注'
        ])->validate();

        throw_if(
            $this->model::where(['account_name' => $this->formData['account_name'], 'platform' => $this->formData['platform']])->where('id', '<>', $id)->first(),
            new AccidentException('同一个平台的账号名称已经存在', Code::OPERATE_FAIL)
        );

        $data = $this->model::init($this->formData);

        return $this->model::where('id', $id)->update($data);
    }

    public function enable($id)
    {
        validator($this->formData, [
            'enable' => 'required'
        ], [], [
            'enable' => '状态码'
        ])->validate();

        DB::beginTransaction();
        try {
            if($this->formData['enable'] == 1) {
                $platform = $this->model::where('id', $id)->value('platform');
                $this->model::where('platform', $platform)->update(['enable' => 0]);

            }

            $this->model::where('id', $id)->update(['enable' => $this->formData['enable']]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    public function del($id)
    {
        return $this->model::where('id', $id)->delete();
    }

    public function cancel($id)
    {
        return $this->model::where('id', $id)->update(['state' => 0]);
    }
}
