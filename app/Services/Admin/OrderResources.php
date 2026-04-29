<?php

namespace App\Services\Admin;

use App\Helper\CurrencyConverter;
use App\Jobs\SendEmailJob;
use App\Lib\Code;
use App\Mail\MailConfig;
use App\Models\EmailTemplate;
use App\Models\OrderResourcesModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Exceptions\AccidentException;

class OrderResources extends BaseService
{
    protected $filterRules = [
        'status' => ['=', 'status'],
    ];
    protected $orderBy = [
        'id' => 'desc',
    ];
    public function __construct(OrderResourcesModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['customer:id,custom_name as name', 'procure:id,name', 'product.goods', 'consult.contents'])
            ->where('status', '<>', 5);

        if(isset($this->formData['keyword'])) {
            $this->query->when($this->formData['type'] == 1, function ($query) {
                $query->whereHas('customer', function($qy) {
                    $qy->where('custom_name', 'like', '%'.$this->formData['keyword'].'%');
                })->orWhere('product_name', 'like', '%'.$this->formData['keyword'].'%');
            });

            $this->query->when($this->formData['type'] == 2, function ($query) {
                $id = (int) $this->formData['keyword'];
                $query->whereHas('customer', function ($qy) use($id){
                    $qy->where('id', $id);
                })->orWhere('id', $id);
            });

            $this->query->when($this->formData['type'] == 3, function ($query) {
                $query->whereHas('procure', function ($qy) {
                    $qy->where('name', 'like', '%'.$this->formData['keyword'].'%');
                });
            });

            $this->query->when($this->formData['type'] == 4, function ($query) {
                $query->whereHas('product', function ($qy) {
                    $qy->where('sku_id', 'like', '%'.$this->formData['keyword'].'%');
                });
            });
        }

        $res = parent::index();

        $res->each(function ($item) {
            if(!empty($item->consult)) {
                $contents = array_filter($item->consult->contents->toArray(), function($content) {
                    return $content['status'] === 0 && empty($content['customer_service_id']);
                });
                $item->unread = count($contents);
            } else {
                $item->unread = 0;
            }
        });

        return $res;
    }

    public function store()
    {
        validator($this->formData, [
            'product_name' => 'required',
            'customer_id'  => 'required',
            'target_price' => 'numeric'
        ])->validate();

        $data = $this->model::init($this->formData, $this->formData['customer_id']);

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
            'customer_id'  => 'required',
            'target_price' => 'numeric'
        ])->validate();

        $data = $this->model::init($this->formData, $this->formData['customer_id']);

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

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('status', '<>', 0)->first(),
            new AccidentException('操作失败，只能删除待认领的订单', Code::OPERATE_FAIL)
        );

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
        $counts = $this->query->select('status', DB::raw('count(*) as count'))->groupBy('status')->get();

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

    /**
     * 认领
     * @return bool
     * @throws ValidationException
     * @throws Throwable
     */
    public function claim(): bool
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('status', '<>', 0)->first(),
            new AccidentException('操作失败，只有待认领的订单才能操作', Code::OPERATE_FAIL)
        );

        return $this->model::whereIn('id', $this->formData['ids'])->update(['purchaser' => auth()->id(), 'status' => $this->model::STATUS_QUOTATION]);
    }

    /**
     * 询价分配
     * @return void
     * @throws Throwable
     * @throws ValidationException
     */
    public function allocation()
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'user_id' => 'required'
        ], [], [
            'ids' => '订单id',
            'user_id' => '员工id'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('status', '<>', 0)->first(),
            new AccidentException('操作失败，只有待认领的订单才能操作', Code::OPERATE_FAIL)
        );

        return $this->model::whereIn('id', $this->formData['ids'])->update(['purchaser' => $this->formData['user_id'], 'status' => $this->model::STATUS_QUOTATION]);
    }

    /**
     * 标记订单状态
     */
    public function markStatus()
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'status' => [
                'required',
                Rule::in([0,1,2,3,4,5])
            ]
        ], [], [
            'ids' => '订单id',
            'status' => '状态'
        ])->validate();

        return $this->model::whereIn('id', $this->formData['ids'])->update(['status' => $this->formData['status']]);
    }

    /**
     * 报价
     * @return bool
     * @throws ValidationException
     */
    public function quotation()
    {
        validator($this->formData, [
            'params' => 'required|array',
            'params.*.id' => 'required',
            'params.*.product_id' => 'required',
            'params.*.price' => 'required',
        ], [], [
            'params' => '参数',
            'params.*.id' => '订单id',
            'params.*.product_id' => '产品id',
            'params.*.price' => '价格',
        ])->validate();

        DB::beginTransaction();
        try{
            foreach($this->formData['params'] as $item) {
                $this->model::where('id', $item['id'])->update(['product_id' => $item['product_id'], 'price' => $item['price']]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger('拒绝报价：'.$e->getMessage());
            throw new AccidentException('拒绝报价', Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 提交报价
     * @throws Throwable
     */
    public function submitQuotation()
    {
        validator($this->formData, [
            'ids' => 'required'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('status', '<>', $this->model::STATUS_QUOTATION)->first(),
            new AccidentException('操作失败，只有报价中的订单才能操作', Code::OPERATE_FAIL)
        );

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('product_id', 0)->first(),
            new AccidentException('操作失败，请选择设置报价', Code::OPERATE_FAIL)
        );

        //产品报价-发送邮件通知
        $emailTemplate = EmailTemplate::query()->where('type', EmailTemplate::PRODUCT_QUOTATION)->where('enabled', 1)->first();
        if ($emailTemplate) {
            $list = $this->model::with(['customer:id,custom_email,main_user_id', 'customer.mainUser:id,custom_id,username'])->whereIn('id', $this->formData['ids'])->get();

            MailConfig::getEmailConfig();
            $currencyConverter = new CurrencyConverter();

            foreach ($list as $v) {
                $toEmail = $v->customer->custom_email ?? '';
                if (empty($toEmail)) {
                    continue;
                }

                try {
                    $emailParams = [
                        'user_name' => $v->customer->mainUser->username ?? '',
                        'goods_name' => $v->product_name,
                        'quoted_amount' => $v->price,
                        'time' => (string)$v->created_at,
                    ];

                    dispatch(new SendEmailJob('ProductQuotationEmail', $toEmail, $emailParams));
                } catch (\Exception $e) {
                    info('产品报价-发送邮件通知失败', [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'msg' => $e->getMessage()
                    ]);
                }
            }
        }

        return $this->model::whereIn('id', $this->formData['ids'])->update(['status' => $this->model::STATUS_WAIT_CONFIRMED]);
    }
}
