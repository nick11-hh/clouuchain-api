<?php

namespace App\Services\Client;

use App\Lib\Code;
use App\Lib\Platform;
use App\Models\Country;
use App\Models\Goods;
use App\Models\OrderItemMapping;
use App\Models\PlatformProduct;
use App\Models\PlatformProductSku;
use App\Models\ProductQuoteApply;
use App\Models\ProductQuoteApplyItem;
use App\Models\SkuQuotationGroupModel;
use App\Services\Admin\BaseService;
use App\Services\Base\ProductService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\Admin\ExpressLineService;
use App\Exceptions\AccidentException;

class ProductQuoteService extends BaseService
{
    public $filterRules = [
        'custom_id' => ['=', 'custom_id'],
        'shop_id' => ['=', 'shop_id'],
        'product_name' => ['like', 'product_name'],
        'product_id' => ['=', 'product_id'],
        'published_at' => ['between', ['begin_date', 'end_date']]
    ];


    public function __construct()
    {
        $this->model = new PlatformProduct();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }


    public function index()
    {
        $customId = getCustomId() ?? request()->get('custom_id');

        $this->query->with(['skus.mapping.goodsSku.goods', 'skus.applyMapping.goodsSku.goods', 'shop', 'logisticsChannel.regions', 'country']);

        $this->query->where('custom_id', $customId);

        if (isset($this->formData['status']) && $this->formData['status'] !== '') {
            $this->query->where('status', $this->formData['status']);
        }
        if (!empty($this->formData['quote_status'] !== '') && is_numeric($this->formData['quote_status'])) {
            /*if ($this->formData['quote_status'] == ProductQuoteApplyItem::QUOTE_STATUS_NONE) {
                $this->query->whereHas('skus.applyMapping', function ($query) {
                    $query->where('status', $this->formData['quote_status']);
                })->orWhereDoesntHave('skus.applyMapping');
            } else {
                $this->query->whereHas('skus.applyMapping', function ($query) {
                    $query->where('status', $this->formData['quote_status']);
                });
            }*/
            $this->query->where('quote_status', $this->formData['quote_status']);
        }
        if (!empty($this->formData['sku_id'])) {
            $this->query->whereHas('skus', function ($query) {
                $query->where('platform_sku_id', $this->formData['sku_id']);
            });
        }
        if (!empty($this->formData['quote_status']) && $this->formData['quote_status'] == ProductQuoteApplyItem::QUOTE_STATUS_QUOTING) {
            $this->query->latest('apply_quote_time');
        } else {
            $this->query->latest('published_at');
        }

        $list = parent::index();

        $list->map(function ($item) {
            $priceArray = $item->skus->pluck('price')->toArray();
            if (!empty($priceArray)) {
                $item->min_price = min($priceArray);
                $item->max_price = max($priceArray);
            } else {
                $item->min_price = 0;
                $item->max_price = 0;
            }
        });
        return $list;
    }

    public function show($id)
    {
        return $this->model::query()->with(['skus.mapping.goodsSku.goods', 'shop', 'logisticsChannel'])->findOrFail($id);
    }

    public function count()
    {
        $this->setFilter();

        $this->query->with(['skus.mapping.goodsSku.goods', 'skus.applyMapping.goodsSku.goods', 'shop', 'logisticsChannel.regions', 'country']);

        if (isset($this->formData['status']) && $this->formData['status'] !== '') {
            $this->query->where('status', $this->formData['status']);
        }
        /*if (!empty($this->formData['quote_status'])) {
            $this->query->where('quote_status', $this->formData['quote_status']);
        }*/
        if (!empty($this->formData['sku_id'])) {
            $this->query->whereHas('skus', function ($query) {
                $query->where('platform_sku_id', $this->formData['sku_id']);
            });
        }

        return $this->query->where('custom_id', getCustomId())->groupBy('quote_status')->selectRaw('quote_status, count(*) as num')->get();
    }

    public function getProductList($params)
    {
        $query = Goods::query()->with('skus');
        if (!empty($params['keyword'])) {
            $query->where(function ($query) use ($params) {
                $query->where('goods_name', 'like', "%{$params['keyword']}%")->orWhere('spu', $params['keyword']);
            });
        }
        return $query->latest()->get();
    }

    public function saveQuote($id, $params)
    {
        validator($params, $this->rules())->validate();
        $platformProduct = $this->model::query()->findOrFail($id);
        return DB::transaction(function () use ($platformProduct, $params) {
            $productQuoteApply = ProductQuoteApply::query()->create([
                'platform' => $platformProduct->shop_type,
                'product_id' => $platformProduct->product_id,
                'quote_remark' => $params['quote_remark'] ?? '',
            ]);
            $insertData = [];
            foreach ($params['mapping_list'] as $mapping) {
                if (!empty($mapping['goods_sku_id'])) {
                    $insertData[] = [
                        'apply_id' => $productQuoteApply->id,
                        'platform' => $platformProduct->shop_type,
                        'platform_variant_id' => $mapping['platform_variant_id'],
                        'goods_sku_id' => $mapping['goods_sku_id']
                    ];
                }
            }
            ProductQuoteApplyItem::query()->insert($insertData);
            $platformProduct->quote_status = PlatformProduct::QUOTE_STATUS_QUOTING;
            $platformProduct->save();
            return true;
        });
    }

    public function saveLogisticsChannel($id, $params)
    {
        validator($params, [
            'logistics_channel_id' => 'required|int',
            'country_id' => 'required|int',
            'reference_time' => 'required',
        ])->validate();
        $platformProduct = $this->model::query()->findOrFail($id);
        return DB::transaction(function () use ($platformProduct, $params) {
            $platformProduct->logistics_channel_id = $params['logistics_channel_id'];
            $platformProduct->country_id = $params['country_id'];
            $platformProduct->reference_time = $params['reference_time'];
            return $platformProduct->save();
        });
    }

    public function requestQuote($params)
    {
        validator($params, [
            'id' => 'required|int',
            'apply_country_ids' => 'required|array',
        ])->validate();

        $id = $params['id'];

        $platformProduct = $this->model::query()->with('skus')->findOrFail($id);

        return DB::transaction(function () use ($platformProduct, $params) {
            $platformProduct->quote_status = PlatformProduct::QUOTE_STATUS_QUOTING;
            $platformProduct->apply_country_ids = $params['apply_country_ids'];
            $platformProduct->apply_quote_time = now();

            $platformProduct->skus->each(function ($item) use ($platformProduct) {
                $mappingData = [
                    'platform' => $platformProduct->shop_type,
                    'platform_variant_id' => $item->platform_sku_id,
                    'goods_sku_id' => 0,
                ];

                ProductQuoteApplyItem::query()->create($mappingData);
            });

            return $platformProduct->save();
        });

    }

    public function batchRequestQuote($params)
    {
        validator($params, [
            'id' => 'required|array',
            'apply_country_ids' => 'required|array',
        ])->validate();

        $idArr = $params['id'];

        $platformProducts = $this->model::query()->with('skus')
            ->whereIn('id', $idArr)
            ->where('quote_status', PlatformProduct::QUOTE_STATUS_NONE)
            ->get();
        if($platformProducts->isEmpty()){

            throw new AccidentException(__('产品不存在'), Code::OPERATE_FAIL);
        }

        return DB::transaction(function () use ($platformProducts, $params) {

            PlatformProduct::whereIn('id', $params['id'])
                ->where('quote_status', PlatformProduct::QUOTE_STATUS_NONE)
                ->update([
                    'quote_status' => PlatformProduct::QUOTE_STATUS_QUOTING,
                    'apply_country_ids' => $params['apply_country_ids'],
                    'apply_quote_time' => now()
                ]);

            foreach ($platformProducts as $key => $platformProduct) {

                $platformProduct->skus->each(function ($item) use ($platformProduct) {
                    $mappingData = [
                        'platform' => $platformProduct->shop_type,
                        'platform_variant_id' => $item->platform_sku_id,
                        'goods_sku_id' => 0,
                    ];

                    ProductQuoteApplyItem::query()->create($mappingData);
                });
            }

            return true;
        });

    }

    public function getSkuQuotePrice($params)
    {
        validator($params, $this->getSkuQuotePriceRule())->validate();
        $country = Country::query()->when($params['country_id'] ?? 0, function ($query) use ($params) {
            return $query->where('id', $params['country_id']);
        })->first();
        $productService = new ProductService();
        return $productService->getSkuQuote($params['goods_sku_id'], auth('client')->id(), $country, 1);
    }

    /**
     * 确认报价(接受或拒绝)
     * @param array $params
     * @return true
     * @throws Exception
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/15 11:57
     */
    public function confirmQuote(array $params)
    {
        validator($params, [
            'id' => 'required|int',
            'type' => 'required|int|in:1,2',
            'item_ids' => 'sometimes|nullable|array',
        ])->validate();


        $platformProduct = $this->model::query()
            ->with(['skus'])
            ->where('quote_status', '>', $this->model::QUOTE_STATUS_NONE)
            ->findOrFail($params['id']);

        if (empty($platformProduct)) {
            throw new AccidentException('报价产品不存在', Code::OPERATE_FAIL);
        }

        DB::beginTransaction();
        try {
            $confirmQuoteTimes = 0;
            $quoteTimes = 0;
            $platformProduct->skus->each(function ($item) use ($params, &$confirmQuoteTimes, &$quoteTimes) {
                $mapping = $item->mapping;
                // 操作类型 1接受 2拒绝
                $type = $params['type'];
                if ($mapping) {
                    $quoteTimes ++;
                    if ($type == 1 && in_array($item->id, $params['item_ids'] ?? [])) {
                        $mapping->status = 1;
                        $mapping->save();
                        $confirmQuoteTimes ++;
                    } else {
                        $mapping->status = 2;
                        $mapping->save();
                    }
                }
            });

            //区分全部接受/部分接受
            $platformProduct->quote_status = $params['type'] == 1
                ? ($confirmQuoteTimes == $quoteTimes ? PlatformProduct::QUOTE_STATUS_QUOTED : PlatformProduct::QUOTE_PART_CONFIRM)
                : PlatformProduct::QUOTE_STATUS_FAIL;
            $platformProduct->quote_remark = $params['quote_remark'] ?? '';

            if ($params['type'] == 2) {
                $platformProduct->reject_reason = $params['reject_reason'] ?? PlatformProduct::REJECT_REASON_NO_AVAILABLE_ITEM;
            }

            $platformProduct->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            info('confirm-quote-error', ['params' => $params, 'file' => $e->getFile(), 'line' => $e->getLine(), 'message' => $e->getMessage()]);
            throw new AccidentException('确认失败', Code::OPERATE_FAIL);

        }
        return true;
    }

    /**
     * 处理确认报价后的订单商品映射表操作
     * @param $applyItem
     * @param $type 1接受 2拒绝
     * @return void
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/2/13 19:18
     */
    public function handleOrderItemMapping($applyItem, $type = 1)
    {
        $where = [
            'platform' => $applyItem->platform,
            'platform_variant_id' => $applyItem->platform_variant_id,
        ];

        $orderItemMapping = OrderItemMapping::query()->where($where)->first();
        if ($type == 1) {
            if (!empty($orderItemMapping)) {
                $mappingData = [
                    'goods_sku_id' => $applyItem->goods_sku_id,
                ];

                $orderItemMapping->update($mappingData);
            } else {
                //写入订单商品映射
                $mappingData = [
                    'platform' => $applyItem->platform,
                    'platform_variant_id' => $applyItem->platform_variant_id,
                    'goods_sku_id' => $applyItem->goods_sku_id,
                ];

                OrderItemMapping::query()->create($mappingData);
            }
        }/* else {
            //删除映射关系
            if (!empty($orderItemMapping)) {
                $orderItemMapping->delete();
            }
        }*/
    }


    /**
     * 查询报价详情
     * @param $id
     * @return Collection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/2/13 17:44
     */
    public function getQuoteDetail($id)
    {
        $product = $this->model::query()
            ->with(['skus.goodsSku.goods', 'skus.mapping'])
            ->findOrFail($id);
        //获取报价国家列表
        $expressLineService = new ExpressLineService();
        $countryList = $expressLineService->getCountryList(['country_ids' => $product->apply_country_ids]);
        $product->country_list = $countryList;

        //取SKU的一客一价
        $product->skus->map(function ($item) use ($product) {
            if ($item->goodsSku) {
                $skuQuotationGroupModel = new SkuQuotationGroupModel();
                $skuQuotationList = $skuQuotationGroupModel::query()
                    ->with(['skuQuotationGroupAttr' => function ($query) {
                        $query->where('is_new', 1);
                    }])
                    ->where('custom_id', $product->custom_id)
                    ->where('sku_id', $item->goodsSku->id)
                    ->whereIn('country_id', $product->apply_country_ids)
                    ->get();

                $item->sku_quotation_group = $skuQuotationList;
                $item->quote_price = $item->goodsSku->quote_price;
            }
            $statusList = [
                0 => '待接受',
                1 => '已接受',
                2 => '拒绝'
            ];
            $item->status = $item->mapping->status ?? 0;
            $item->quote_status_name = $statusList[$item->mapping->status ?? 0] ?? '--';
        });

        return $product;
    }


    protected function rules()
    {
        return [
            'mapping_list' => 'required|array',
            'mapping_list.*.platform_variant_id' => 'required|string',
            'mapping_list.*.goods_sku_id' => 'sometimes|nullable|int',
            'quote_remark' => 'sometimes|nullable|string'
        ];
    }

    public function getSkuQuotePriceRule()
    {
        return [
            'goods_sku_id' => 'required|int',
            'country_id' => 'sometimes|nullable|int'
        ];
    }


}
