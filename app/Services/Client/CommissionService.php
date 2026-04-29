<?php

namespace App\Services\Client;


use App\Lib\Code;
use App\Models\AgentCommission;
use App\Models\CommissionWithdraw;
use App\Models\Custom;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class CommissionService extends BaseService
{
    public $filterRules = [
        'created_at'     => ['between', ['begin_date', 'end_date']]
    ];


    public function __construct()
    {
        $this->model = new CommissionWithdraw();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /**
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function withdrawList()
    {
        if (isset($this->formData['status']) && $this->formData['status'] !== '') {
            $this->query->where('status', $this->formData['status']);
        }
        $this->query->where('custom_id', getCustomId())->latest();
        return parent::index();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function commissionList()
    {
        $status = $this->formData['status'] ?? '';
        $pageSize = $this->formData['size'] ?? 10;
        $this->query = AgentCommission::query()->with('custom')
            ->where('agent_id', getCustomId());
        $this->query->when($status, function ($query) use ($status) {
            return $query->where('status', $status);
        });
        return $this->query->latest()->paginate($pageSize);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function inviteList()
    {
        $pageSize = $this->formData['size'] ?? 10;

        $this->query = Custom::query()->where('invite_id', getCustomId());

        return $this->query->latest()->paginate($pageSize);
    }

    public function withdrawTypeList()
    {
        return transformArray($this->model::withdrawTypeList());
    }

    /**
     * @param $params
     * @return mixed
     */
    public function withdrawApply($params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($params) {
            $commissionQuery = AgentCommission::query()
                ->where('agent_id', getCustomId())
                ->where('status', AgentCommission::STATUS_NOT_WITHDRAW)
                ->where('withdraw_id', 0);
            $params['withdraw_amount'] = $commissionQuery->sum('commission_amount');
            if (empty($params['withdraw_amount'])) throw new AccidentException('There is currently no commission payable', Code::OPERATE_FAIL);
            $data = CommissionWithdraw::init($params);
            $withdraw = $this->model->query()->create($data);
            $commissionQuery->update(['withdraw_id' => $withdraw->id, 'status' => AgentCommission::STATUS_WITHDRAWING]);
            return $withdraw;
        });
    }


    public function rules()
    {
        return [
            'withdraw_type' => 'required|int',
            'withdraw_account' => 'sometimes|nullable|string',
            'custom_remark' => 'sometimes|nullable|string',
            'custom_images' => 'sometimes|nullable|array',
        ];
    }

}
