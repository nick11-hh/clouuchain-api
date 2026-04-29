<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\Admin;
use App\Models\Custom;
use App\Models\Goods;
use App\Models\GoodsDiscountRule;
use App\Models\GoodsDiscountRuleItem;
use App\Models\GoodsSku;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Exceptions\AccidentException;


class GoodsDiscountRuleService extends BaseService
{
    public $filterRules = [
        'staff_id' => ['=', 'staff_id'],
        'customer_id' => ['=', 'customer_id']
    ];

    public function __construct()
    {
        $this->model = new GoodsDiscountRule();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['items.goods', 'custom', 'staff']);
        $this->query->latest();
        if (!empty($this->formData['time_range'])) {
            $start = $this->formData['time_range'][0] ?? '';
            $end = Carbon::parse($this->formData['time_range'][1] ?? '')->addDay()->toDateString();
            $this->query->whereBetween($this->formData['time_type'], [$start, $end]);
        }
        if (!empty($this->formData['goods_name']) || !empty($this->formData['spu'])) {
            $this->query->whereHas('items', function ($query) {
               $query->whereHas('goods', function ($query) {
                   if (!empty($this->formData['goods_name'])) {
                       $query->where('goods_name', 'like', "%" . $this->formData['goods_name'] . "%");
                   }
                   if (!empty($this->formData['spu'])) {
                       $query->where('spu', $this->formData['spu']);
                   }
               });
            });
        }
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['items.goods', 'custom', 'staff'])->findOrFail($id);
    }


    /**
     * @param $params
     * @return mixed
     * @throws ValidationException
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($params) {
            $this->verifyData($params);
            $this->checkoutEexist($params, 0);
            $customer = Custom::query()->findOrFail($params['customer_id']);
            $staff = Admin::query()->findOrFail(getAdminId());
            $ruleData = $this->model::init($params);
            $rule = $this->model::query()->create($ruleData);
            $customerNo = $customer->customer_number ?: $customer->id;
            $ruleName = "{$staff->name} - {$customerNo}";
            foreach ($params['items'] as $item) {
                $goods = Goods::query()->findOrFail($item['goods_id']);
                $ruleName .= "-{$goods->spu}*{$item['quantity']}";
                GoodsDiscountRuleItem::query()->create([
                    'rule_id' => $rule->id,
                    'goods_id' => $item['goods_id'],
                    'quantity' => $item['quantity']
                ]);
            }
            $ruleName .= "-优惠";
            $ruleName .= $rule->discount_type == GoodsDiscountRule::DISCOUNT_TYPE_PERCENTAGE ? "{$rule->discount_value}%" : "\${$rule->discount_value}";
            $rule->name = $ruleName;
            $rule->code = 'R-' . str_pad($rule->id, 6, '0', STR_PAD_LEFT);
            $rule->save();
            return $rule;
        });
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     * @throws ValidationException
     */
    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($id, $params) {
            $this->verifyData($params);
            $this->checkoutEexist($params, $id);
            $rule = $this->model::query()->with('items')->findOrFail($id);
            $customer = Custom::query()->findOrFail($params['customer_id']);
            $staff = Admin::query()->findOrFail($rule->staff_id);
            $ruleData = $this->model::init($params, 'update');
            $rule->update($ruleData);
            $rule->items()->delete();
            $customerNo = $customer->customer_number ?: $customer->id;
            $ruleName = "{$staff->name} - {$customerNo}";
            foreach ($params['items'] as $item) {
                $goods = Goods::query()->findOrFail($item['goods_id']);
                $ruleName .= " - {$goods->spu}*{$item['quantity']}";
                GoodsDiscountRuleItem::query()->create([
                    'rule_id' => $rule->id,
                    'goods_id' => $item['goods_id'],
                    'quantity' => $item['quantity']
                ]);
            }
            $ruleName .= " - 优惠";
            $ruleName .= $rule->discount_type == GoodsDiscountRule::DISCOUNT_TYPE_PERCENTAGE ? "{$rule->discount_value}%" : "\${$rule->discount_value}";
            $rule->name = $ruleName;
            $rule->save();
            return $rule;
        });
    }

    public function deletes($params)
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();
        return DB::transaction(function () use ($params) {
            $rules = $this->model::query()->whereIn('id', $params['ids'])->get();
            foreach ($rules as $rule) {
                $rule->items()->delete();
                $rule->delete();
            }
            return true;
        });
    }

    protected function checkoutEexist($params, $id)
    {
        $query = $this->model::query()->where('customer_id', $params['customer_id']);

        $query->when($id, function ($query) use ($id) {
            $query->where('id', '!=', $id);
        });

        $exist = $query->has('items', '=', count($params['items']))
            ->whereDoesntHave('items', function ($query) use ($params) {
                $goodsIds = collect($params['items'])->pluck('goods_id');
                $query->whereNotIn('goods_id', $goodsIds);
            })->whereHas('items', function ($q) use ($params) {
                $q->where(function ($q) use ($params) {
                    foreach ($params['items'] as $item) {
                        $q->orWhere(function ($q) use ($item) {
                            $q->where('goods_id', $item['goods_id'])->where('quantity', $item['quantity']);
                        });
                    }
                });
            },  '=', count($params['items']))->first();

        if ($exist) {
            throw new AccidentException('已有相同的组合规则，规则编码为 ' . $exist->code, Code::OPERATE_FAIL);
        }

    }

    /**
     * @param $data
     * @return void
     * @throws \Exception
     */
    protected function verifyData($data)
    {
        $items = collect($data['items']);
        if ($items->sum('quantity') < 2) {
            throw new AccidentException('组合商品总数量必须大于1', Code::OPERATE_FAIL);
        }
    }


    public function rules()
    {
        return [
            'customer_id'      => 'required|int',
            'discount_type'    => 'required|string',
            'discount_value'   => 'required',
            'items'            => 'required|array',
            'items.*.goods_id' => 'required|int',
            'items.*.quantity' => 'required|int',
        ];
    }
}
