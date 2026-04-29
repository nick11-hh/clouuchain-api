<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\GoodsSku;
use App\Models\GoodsSupplier;
use App\Models\Order;
use App\Models\OrderItemMapping;
use App\Models\PlanShopOrderRelationModel;
use App\Models\PurchasePlan;
use App\Models\PurchasePlanItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class PurchasePlanService extends BaseService
{
    public $filterRules = [
        'goods_name'     => ['like', 'goods_name'],
        'spu'            => ['=', 'spu'],
        'create_user_id' => ['=', 'user_id'],
        'created_at'     => ['between', ['begin_date', 'end_date']],
        'items:shop_id'  => ['=', 'shop_id'],
    ];
    private PurchasePlanItem $itemModel;

    public function __construct()
    {
        $this->model = new PurchasePlan();
        $this->itemModel = new PurchasePlanItem();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with([
            'items.supplier:id,supplier_name',
            'items.shop:id,shop_name',
            'items.warehouse:id,warehouse_name',
            'user:id,name',
            'items.goodsSku:id,sku_id',
            'order:id,order_id'
        ]);

        if(isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            if($this->formData['keyword_type'] === '1') {
                $this->query->whereHas('items', function($query) {
                    $query->where('goods_name', 'like', '%'.$this->formData['keyword'].'%');
                });
            } elseif ($this->formData['keyword_type'] === '2') {
                $this->query->whereHas('items.goodsSku', function($query) {
                    $query->where('sku_id', $this->formData['keyword']);
                });
            } elseif ($this->formData['keyword_type'] === '3') {
                $this->query->where('plan_sn', 'like',  '%' . $this->formData['keyword'] . '%');
            } elseif ($this->formData['keyword_type'] === '4') {
                $this->query->whereHas('order', function($query) {
                    $query->where('dsp_shop_order.order_id', 'like',  '%' . $this->formData['keyword'] . '%');
                });
            }
        }

        if(isset($this->formData['status'])) {
            $this->query->where('status', $this->formData['status']);
        }

        $this->query->latest();
        $res = parent::index();

        $res->each(function ($item) {
            if(!empty($item->order)) {
                $item->order_ids = implode(',', array_unique(array_column($item->order->toArray(), 'order_id')));
            }
        });

        return $res;
    }

    public function show($id)
    {
        return $this->model::query()->with(['items'])->findOrFail($id);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function store($params, $orderInfo = null)
    {
        validator($params, $this->rules(), [], [
            'items.*.plan_qty' => '计划数量'
        ])->validate();

        if(!isset($params['id'])) {
            $params['id'] = 0;
        }
        return DB::transaction(function () use ($params, $orderInfo) {
            $planData = $this->model::init($params);
            $plan = $this->model::where('id', $params['id'])->first();
            if(!empty($plan)) {
                $this->model::where('id', $params['id'])->update($planData);

                $itemIds = array_column($params['items'], 'item_id');
                $pItems = $this->itemModel::where('plan_id', $params['id'])->select('id')->get()->toArray();
                $oItemIds = array_column($pItems, 'id');
                $diff = array_diff($oItemIds, $itemIds);
                $this->itemModel::whereIn('id', $diff)->delete();
            } else {
                $plan = $this->model::query()->create($planData);
            }


            $planShopOrderRelation = [];
            foreach ($params['items'] as $key => $item) {
                $goodsSku = GoodsSku::with('goods')->findOrFail($item['sku_id']);
                $itemData = $this->transformGoodsSku($goodsSku->goods, $goodsSku);
                $itemData['plan_id'] = $plan->id;
                $itemData['item_sn'] = $plan->plan_sn . '_' . ($key + 1);

                $itemData['plan_qty'] = $item['plan_qty'];
                $itemData['supplier_id'] = $item['supplier_id'];
                $itemData['shop_id'] = $item['shop_id'];
                $itemData['warehouse_id'] = $item['warehouse_id'];
                $itemData['plan_procurement_time'] = $item['plan_procurement_time'] ?? null;

                $skuData = $this->itemModel::init($itemData);
                if(!isset($item['item_id'])) {
                    $planItem = $this->itemModel::query()->create($skuData);
                    $item['item_id'] = $planItem->id;
                } else {
                    $this->itemModel::where('id', $item['item_id'])->update($skuData);
                }


                if($orderInfo) {
                    $planShopOrderRelation[] = [
                        'plan_id' => $plan->id,
                        'plan_item_id' => $item['item_id'],
                        'order_id' => $orderInfo['goods'][$key]['order_items'][0]['order_id'],
                        'order_item_id' => $orderInfo['goods'][$key]['order_items'][0]['order_item_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }


            // 添加订单和采购计划关系
            if(!empty($planShopOrderRelation)) {
                PlanShopOrderRelationModel::insert($planShopOrderRelation);
            }

            return true;
        });
    }

    /**
     * 状态统计
     */
    public function statusCount()
    {
        //首次加载列表页时会传状态值，导致其他状态的统计数量都为0 所以暂时先过滤状态查询条件
        unset($this->filters['status']);
        $this->setFilter();

        if(isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            if($this->formData['keyword_type'] === '1') {
                $this->query->whereHas('items', function($query) {
                    $query->where('goods_name', 'like', '%'.$this->formData['keyword'].'%');
                });
            } elseif ($this->formData['keyword_type'] === '2') {
                $this->query->whereHas('items.goodsSku', function($query) {
                    $query->where('sku_id', $this->formData['keyword']);
                });
            } elseif ($this->formData['keyword_type'] === '3') {
                $this->query->where('plan_sn', 'like',  '%' . $this->formData['keyword'] . '%');
            } elseif ($this->formData['keyword_type'] === '4') {
                $this->query->whereHas('order', function($query) {
                    $query->where('dsp_shop_order.order_id', 'like',  '%' . $this->formData['keyword'] . '%');
                });
            }
        }


        // if(isset($this->formData['status'])) {
        //     $this->query->where('status', $this->formData['status']);
        // }

        $counts = $this->query->select('status', DB::raw('count(*) as count'))->groupBy('status')->get();

        $data = [];
        $status = ['0', '1', '2'];
        $counts->each(function ($item) use (&$data) {
            $data[$item->status] = $item->count;
        });

        foreach ($status as $item) {
            if (!isset($data[$item])) {
                $data[$item] = 0;
            }
        }

        ksort($data);

        return $data;
    }

    public function getPlanSku()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '采购计划id'
        ])->validate();

        throw_if(
          $this->model::whereIn('id', $this->formData['ids'])->where('status', 0)->first(),
            new AccidentException('操作失败，草稿中的订单不能生成采购单', Code::OPERATE_FAIL)
        );

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('status', 2)->first(),
            new AccidentException('操作失败，已完成订单不能生成采购单', Code::OPERATE_FAIL)
        );

        return $this->itemModel->with(['supplier:id,supplier_name', 'warehouse:id,warehouse_name', 'goodsSku:id,sku_id'])
            ->whereHas('plan', function ($query) {
            $query->whereIn('id', $this->formData['ids']);
        })
            ->get();
    }

    public function getSuppliersByGoodsSkuId($sku_id)
    {
        return Supplier::whereHas('goodsSupplier', function ($query) use($sku_id) {
            $query->where('goods_sku_id', $sku_id);
        })
            ->select('id', 'supplier_name')
            ->get();
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($id, $params) {

        });
    }

    /**
     * @param $params
     * @return mixed
     */
    public function updateStatus($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int'
        ])->validate();
        $data = ['status' => $params['status']];
        return $this->model::query()->whereIn('id', $params['ids'])->update($data);
    }

    public function createByOrder($id)
    {
        $order = Order::query()->with('lineItems')->findOrFail($id);
        return DB::transaction(function () use ($order) {
            $planData = $this->model::init([]);
            $plan = $this->model::query()->create($planData);
            $order->lineItems->each(function ($item, $key) use ($order, $plan) {
                $mapping = OrderItemMapping::query()->with('goodsSku', 'goodSku.goods')
                    ->where('platform_variant_id', $item->variant_id)->first();

                if (empty($mapping)) throw new AccidentException('没有匹配商品', Code::OPERATE_FAIL);

                $itemData = $this->transformGoodsSku($mapping->goodsSku->goods, $mapping->goodsSku);
                $itemData['order_sn'] = $order->order_id;
                $itemData['order_item_id'] = $item->id;
                $itemData['plan_id'] = $plan->id;
                $itemData['item_sn'] = $plan->plan_sn . '_' . ($key + 1);
                $skuData = $this->itemModel::init($itemData);
                $this->itemModel::query()->create($skuData);
            });
            return true;
        });

    }

    public function createPurchaseByPlanItem($params)
    {

    }


    //作废采购计划单
    public function cancel()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ])->validate();


        $this->model->whereIn('id', $this->formData['ids'])->delete();
        $this->itemModel->whereIn('plan_id', $this->formData['ids'])->delete();

        return true;
    }

    protected function transformGoodsSku($goods, $sku)
    {
        return [
            'images' => $sku->images ?? [],
            'goods_name' => $goods->goods_name,
            'spec_name' => $sku->spec_name,
            'goods_sku_id' => $sku->id,
        ];
    }

    protected  function rules()
    {
        return [
            'items.*.plan_qty' => 'required'
        ];
    }

}
