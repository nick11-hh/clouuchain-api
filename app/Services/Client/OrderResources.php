<?php

namespace App\Services\Client;

use App\Helper\CurrencyConverter;
use App\Lib\Code;
use App\Models\OrderResourcesModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Exceptions\AccidentException;

class OrderResources extends BaseService
{
    protected $filterRules = [
        'status' => ['=', 'status'],
        'product_name' => ['like', 'keyword']
    ];
    protected $orderBy = [
        'id' => 'desc'
    ];
    public function __construct(OrderResourcesModel $model)
    {
        $this->model = $model;
        $this->formData = request()->all();
        $this->query = $model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with('consult.contents')->where('customer_id', getCustomId());

        $res = parent::index();

        $res->each(function ($item) {
            if(!empty($item->consult)) {
                $contents = array_filter($item->consult->contents->toArray(), function($content) {
                    return $content['status'] === 0 && empty($content['user_id']);
                });
                $item->unread = count($contents);
            } else {
                $item->unread = 0;
            }
//            $item->price = $currency->reversedCurrenciesExchange($item->price);
        });

        return $res;
    }

    public function store()
    {
        validator($this->formData, [
            'product_name' => 'required',
            'target_price' => 'numeric'
        ])->validate();

        $data = $this->model::init($this->formData, auth()->user()->custom_id);

        try {
            $this->model::create($data);
        } catch (Exception $e) {
            logger($e->getMessage());
            throw new AccidentException('创建失败', Code::OPERATE_FAIL);
        }

        return true;
    }

    public function update($id)
    {
        validator($this->formData, [
            'product_name' => 'required',
            'target_price' => 'numeric'
        ])->validate();

        $data = $this->model::init($this->formData, auth()->user()->custom_id);

        try {
            $this->model::where('id', $id)->update($data);
        } catch (Exception $e) {
            logger($e->getMessage());
            throw new AccidentException('修改失败', Code::OPERATE_FAIL);
        }

        return true;
    }

    public function del()
    {
        validator($this->formData, [
            'ids' => 'required',
        ])->validate();

        return $this->model::whereIn('id', $this->formData['ids'])->delete();
    }

    public function submit($id)
    {
        return $this->model::where('id', $id)->update(['status' => $this->model::STATUS_CLAIM]);
    }

    /**
     * 状态统计
     * @return void
     */
    public function statusCount()
    {
        $counts = $this->query->where('customer_id', getCustomId())
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        $data = [];
        $status = ['0', '1', '2', '3', '4', '5'];
        $counts->each(function($item) use(&$data){
            $data[$item->status] = $item->count;
        });

        foreach($status as $item) {
            if(!isset($data[$item])) {
                $data[$item] = 0;
            }
        }

        ksort($data);

        return $data;
    }

    public function accept($id)
    {
        validator($this->formData, [
            'status' => [
                'required',
                Rule::in([1,3,4])
            ]
        ])->validate();

        return $this->model::where('id', $id)->update(['status' => $this->formData['status']]);
    }

    public function batchChangeStatus()
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'status' => [
                'required',
                Rule::in([1,3,4])
            ]
        ])->validate();

        return $this->model::query()->whereIn('id', $this->formData['ids'])->update(['status' => $this->formData['status']]);
    }

}
