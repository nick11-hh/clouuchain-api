<?php


namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\AgentCommission;
use App\Models\CommissionWithdraw;
use App\Models\Custom;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class MarketingPromotionService extends BaseService
{
    public function __construct(Custom $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
    }

    public function index()
    {
        ini_set('memory_limit', '256M');
        $this->query->with(['promotion', 'agent'])
            ->whereHas('promotion', function ($query) {
                $query->whereNotNull('id');
            });

        if (isset($this->formData['keyword']) && $this->formData['keyword']) {
            $this->query->where(function ($query) {
                $query->where('custom_name', $this->formData['keyword'])
                    ->orWhere('id', $this->formData['keyword']);
            });
        }

        return parent::index();
    }

    public function record()
    {
        validator($this->formData, [
            'agent_id' => 'required|int'
        ], [], [
            'agent_id' => '客户编号'
        ])->validate();

        $record = AgentCommission::query()->with('custom')
            ->where('agent_id', $this->formData['agent_id'])
            ->orderBy('status', 'asc');
        return $record->paginate($this->formData['size'] ?? 10);
    }

    public function withdrawTypeList()
    {
        return transformArray(CommissionWithdraw::withdrawTypeList());
    }

    public function withdraw($customId, $params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($customId, $params) {
            $commissionQuery = AgentCommission::query()
                ->where('agent_id', $customId)
                ->where('withdraw_id', 0);
            $params['withdraw_amount'] = $commissionQuery->sum('commission_amount');
            if (empty($params['withdraw_amount'])) throw new AccidentException('当前没有可提现返佣', Code::OPERATE_FAIL);
            $params['custom_id'] = $customId;
            $data = CommissionWithdraw::init($params);
            $withdraw = CommissionWithdraw::query()->create($data);
            $commissionQuery->update(['withdraw_id' => $withdraw->id]);
            return $withdraw;
        });
    }

    public function rules()
    {
        return [
            'withdraw_type' => 'required|int',
            'withdraw_account' => 'required|string',
            'custom_remark' => 'sometimes|nullable|string',
            'custom_images' => 'sometimes|nullable|array',
        ];
    }
}
