<?php

namespace App\Services\Client;

use App\Models\ConsultContentModel;
use App\Models\ConsultModel;
use Exception;
use Illuminate\Support\Facades\DB;

class ConsultService extends BaseService
{
    public function __construct(ConsultModel $model)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['contents.user:id,username', 'contents.admin:id,name']);

        if (isset($this->formData['type']) && $this->formData['type'] != 'all') {
            $this->query->where('type', $this->formData['type']);
        }

        if (isset($this->formData['keyword'])) {
            $this->query->where('order_sn',  'like', '%'.$this->formData['keyword'].'%');
        }

        return parent::index();
    }

    public function show($order_id)
    {
        try {
            $this->query->with(['contents.user:id,username', 'contents.admin:id,name']);
            return $this->query->where('order_id', $order_id)->first();
        } catch (Exception $e) {
            info('获取内容报错：'.$e->getMessage());
        }
    }

    public function store()
    {
        validator($this->formData, [
            'order_id' => 'required',
            'content' => 'required'
        ], [], [
            'order_sn' => '订单id',
            'content' => '咨询内容'
        ])->validate();

        $consultData = [
            'order_id' => $this->formData['order_id'],
            'content' => $this->formData['content'],
        ];

        DB::beginTransaction();
        try {
            $consul = $this->model::where('order_id', $this->formData['order_id'])->first();
            if(!$consul) {
                $consul = $this->model::create($consultData);
            }

            $consultContentData = [
                'consult_id' => $consul->id,
                'user_id' => auth()->id(),
                'content' => $this->formData['content']
            ];

            ConsultContentModel::create($consultContentData);

            DB::commit();
        }catch (\Exception $e) {
            DB::rollBack();
            logger('内容发送失败：'.$e->getMessage());
            return false;
        }

        return true;
    }

    public function mark($id)
    {
        return ConsultContentModel::where('consult_id', $id)->where('customer_service_id', '>', 0)->update(['status' => 1]);
    }
}
