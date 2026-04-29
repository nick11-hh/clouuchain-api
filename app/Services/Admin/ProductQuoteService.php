<?php

namespace App\Services\Admin;

use App\Helper\CurrencyConverter;
use App\Http\Controllers\Client\ExpressPriceController;
use App\Lib\Code;
use App\Models\Country;
use App\Models\Goods;
use App\Models\OrderItemMapping;
use App\Models\PlatformProduct;
use App\Models\PlatformProductSku;
use App\Models\ProductQuoteApply;
use App\Models\ProductQuoteApplyItem;
use App\Models\SkuQuotationGroupAttrModel;
use App\Models\SkuQuotationGroupModel;
use App\Services\Base\ProductService;
use App\Services\Base\SystemConfigService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class ProductQuoteService extends BaseService
{
    public $filterRules = [
        'custom_id' => ['=', 'custom_id'],
        'shop_id' => ['=', 'shop_id'],
        'product_name' => ['like', 'product_name'],
        'product_id' => ['=', 'product_id'],
        'published_at' => ['between', ['begin_date', 'end_date']],
        'status' => ['=', 'status'],
        'quote_status' => ['=', 'quote_status'],
        'skus:platform_sku_id' => ['=', 'sku_id'],
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
        $this->query->with(['skus.mapping.goodsSku.goods', 'shop', 'skus.applyMapping.goodsSku.goods', 'logisticsChannel', 'custom']);

        $this->productListFilter();

        /*if (isset($this->formData['status']) && $this->formData['status'] !== '') {
            $this->query->where('status', $this->formData['status']);
        }
        if (!empty($this->formData['quote_status'])) {
            $this->query->where('quote_status', $this->formData['quote_status']);
        }
        if (!empty($this->formData['sku_id'])) {
            $this->query->whereHas('skus', function ($query) {
                $query->where('platform_sku_id', $this->formData['sku_id']);
            });
        }*/
        $this->query->latest('published_at');
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
        return $this->model::query()
            ->with(['skus.mapping.goodsSku.goods', 'shop', 'logisticsChannel.regions', 'country', 'custom'])
            ->findOrFail($id);
    }

    public function count()
    {
        $this->productListFilter();
        //首次加载列表页时会传状态值，导致其他状态的统计数量都为0 所以暂时先过滤状态查询条件
        unset($this->filters['quote_status']);
        $this->setFilter();

        return $this->query->groupBy('quote_status')->selectRaw('quote_status, count(*) as num')->get();
    }

    /**
     * 产品列表过滤器
     * @return void
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/23 15:45
     */
    public function productListFilter()
    {
        if (SystemConfigService::getConfigValue('show_not_quoted_shop_product') != 1) {
            $this->query->whereNot('quote_status', $this->model::QUOTE_STATUS_NONE);
        }
    }

    public function getProductList($params)
    {
        $query = Goods::query()->with('skus');
        if (!empty($params['keyword'])) {
            $query->where(function ($query) use ($params) {
                $query->where('goods_name', 'like', "%{$params['keyword']}%")->orWhere('spu', $params['keyword']);
            });
        }
        return $query->latest()->paginate();
    }

    public function saveQuote($id, $params, $type = 'relate')
    {
        validator($params, $this->rules())->validate();
        $platformProduct = $this->model::query()->with(['skus.mapping.goodsSku.goods'])->findOrFail($id);
        /*if ($type === 'relate' && $platformProduct->quote_status = PlatformProduct::QUOTE_STATUS_QUOTING) {
            throw new AccidentException('报价中商品不允许再报价', Code::OPERATE_FAIL);
        }*/
        $result = DB::transaction(function () use ($platformProduct, $params) {
            foreach ($params['mapping_list'] as $mapping) {
                $orderItemMapping = OrderItemMapping::query()->where('platform_variant_id', $mapping['platform_variant_id'],)->first();
                if (empty($mapping['goods_sku_id'])) {
                    if (!empty($orderItemMapping)) $orderItemMapping->delete();
                } else {
                    if (empty($orderItemMapping)) {
                        $orderItemMapping = new OrderItemMapping();
                        $orderItemMapping->platform = $platformProduct->shop_type;
                        $orderItemMapping->platform_variant_id = $mapping['platform_variant_id'];
                    }
                    $orderItemMapping->goods_sku_id = $mapping['goods_sku_id'];
                    $orderItemMapping->save();
                }
            }

            $platformProduct->quote_remark = $params['quote_remark'] ?? '';
            //客户端接受报价后才改为报价中
            $platformProduct->quote_status = PlatformProduct::QUOTE_WAIT_CONFIRM;

            if ($params['logistics_channel_id'] ?? 0) {
                $platformProduct->logistics_channel_id = $params['logistics_channel_id'];
            }
            if ($params['country_id'] ?? 0) {
                $platformProduct->country_id = $params['country_id'];
            }
            if ($params['reference_time'] ?? '') {
                $platformProduct->reference_time = $params['reference_time'];
            }

            $platformProduct->save();

            return true;
        });

        if ($result) {
            $newPlatformProduct = $this->model::query()->with(['skus.mapping.goodsSku.goods'])->findOrFail($id);

            //如果存在物流渠道则更新SKU物流运费
            if ($newPlatformProduct->logistics_channel_id > 0 && $newPlatformProduct->country_id > 0) {
                $shippingFeeParams = [
                    'logistics_channel_id' => $newPlatformProduct->logistics_channel_id,
                    'country_id' => $newPlatformProduct->country_id,
                ];
                $this->updateSkuShippingFee($shippingFeeParams, $newPlatformProduct->skus);
            }
        }

        return true;
    }

    /**
     * 关联本地商品SKU
     * @param $params
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/2/14 0:34
     */
    public function associationGoodsSku($params)
    {
        validator($params, [
            'id' => 'required|int',
            'goods_sku_id' => 'required|int',
        ])->validate();

        $productSku = PlatformProductSku::query()->with('platformProduct')->findOrFail($params['id']);
        return OrderItemMapping::query()->updateOrCreate([
            'platform' => $productSku->platformProduct->shop_type,
            'platform_variant_id' => $productSku->platform_sku_id,
        ], ['goods_sku_id' => $params['goods_sku_id']]);
    }

    /**
     * 提交报价
     * @param $params
     * @return bool|int
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/2/14 15:22
     */
    public function submitQuote($params)
    {
        validator($params, [
            'id' => 'required|int',
            'quote_remark' => 'sometimes|nullable|string',
        ])->validate();

        $platformProduct = $this->model::query()->with(['skus.applyMapping'])->findOrFail($params['id']);

        $updateData = [
            'quote_status' => PlatformProduct::QUOTE_WAIT_CONFIRM,
            'quote_remark' => $params['quote_remark'] ?? '',
        ];

        //更新SKU的报价状态
//        $platformProduct->skus->each(function ($item) {
//            $updateItemData = ['status' => ProductQuoteApplyItem::QUOTE_STATUS_QUOTING];
//            $item->applyMapping->update($updateItemData);
//        });

        return $platformProduct->update($updateData);
    }

    public function reviewSuccess($id, $params = [])
    {
        $platformProduct = $this->model::query()->with('skus')->findOrFail($id);
        return DB::transaction(function () use ($platformProduct, $params) {
            $apply = ProductQuoteApply::query()->with('items')->where('product_id', $platformProduct['product_id'])->where('status', 1)->first();
            if (empty($apply)) throw new AccidentException('当前没有申请请求', Code::OPERATE_FAIL);
            $applyItem = $apply->items->keyBy('platform_variant_id');
            $apply->status = 2;
            $apply->save();
            $apply->items()->update(['status' => 2]);

            $mappingList = [];
            foreach ($platformProduct->skus as $sku) {
                $mappingList[] = [
                    'platform_variant_id' => $sku->platform_sku_id,
                    'goods_sku_id' => $applyItem[$sku->platform_sku_id]->goods_sku_id ?? 0,
                ];
            }
            $saveParams['mapping_list'] = $mappingList;
            if (!empty($apply->quote_remark)) $saveParams['quote_remark'] = $apply->quote_remark;
            return $this->saveQuote($platformProduct->id, $saveParams, 'review');
        });
    }

    /**
     * 拒绝报价
     * @param $params
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/28 19:20
     */
    public function reviewReject($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'reject_reason' => 'required|int',
            'review_desc' => 'sometimes|nullable|string',
        ])->validate();

        $clientService = new \App\Services\Client\ProductQuoteService();
        $ids = $params['ids'];

        foreach ($ids as $id) {
            $confirmQuoteParams = [
                'id' => $id,
                'type' => 2,
                'reject_reason' => $params['reject_reason'] ?? $this->model::REJECT_REASON_NO_AVAILABLE_ITEM,
                'quote_remark' => $params['review_desc'] ?? '',
            ];

            $clientService->confirmQuote($confirmQuoteParams);
        }

        return true;

        /*return DB::transaction(function () use ($params) {
            $ids = $params['ids'];
            $platformProductList = $this->model::query()->with('skus.mapping')->whereIn('id', $ids)->get();

            foreach ($platformProductList as $platformProduct) {
                $platformProduct->quote_status = PlatformProduct::QUOTE_STATUS_FAIL;
                $platformProduct->reject_reason = $params['reject_reason'] ?? 1;
                $platformProduct->quote_remark = $params['review_desc'] ?? '';

                $platformProduct->save();
            }

            return true;
        });*/
    }

    /**
     * 保存物流渠道信息
     * @param $id
     * @param $params
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/6 11:59
     */
    public function saveLogisticsChannel($id, $params)
    {
        validator($params, [
            'logistics_channel_id' => 'required|int',
            'country_id' => 'required|int',
            'reference_time' => 'required',
            'warehouse_id' => 'sometimes|nullable|int',
        ])->validate();
        $params['warehouse_id'] = $params['warehouse_id'] ?? 0;
        $platformProduct = $this->model::query()->with(['skus.mapping.goodsSku.goods'])->findOrFail($id);

        return DB::transaction(function () use ($platformProduct, $params) {
            $platformProduct->logistics_channel_id = $params['logistics_channel_id'];
            $platformProduct->country_id = $params['country_id'];
            $platformProduct->reference_time = $params['reference_time'];

            //更新SKU的物流费用
            $this->updateSkuShippingFee($params, $platformProduct->skus);

            return $platformProduct->save();
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
     * 更新SKU运费
     * @param $params
     * @param $skuList
     * @return bool
     * @throws \Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/6 15:12
     */
    public function updateSkuShippingFee($params, $skuList)
    {
        foreach ($skuList as $sku) {
            $goodsSkuWeight = $sku->mapping->goodsSku->weight ?? 0;

            $queryParams = [
                'logistics_channel_id' => $params['logistics_channel_id'],
                'country_id' => $params['country_id'],
                'weight' => $goodsSkuWeight,
            ];

            $logisticsFee = $this->getLogisticsFee($queryParams);

            if ($logisticsFee <= 0) {
                info('没有查询到物流费用信息', ['query_params' => $queryParams, 'sku' => $sku->sku]);
                break;
            }

            $data = ['shipping_fee' => $logisticsFee];
            PlatformProductSku::query()->where('id', $sku->id)->update($data);
        }

        return true;
    }

    /**
     * 获取物流费用
     * @param $params
     * @return float|int
     * @throws \Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/6 15:10
     */
    public function getLogisticsFee($params)
    {
        $expressPriceController = new ExpressPriceController();

        $shippingFee = 0;
        $expressPriceList = $expressPriceController->query($params, false, false);
        if (empty($expressPriceList)) {
            return $shippingFee;
        }

        foreach ($expressPriceList as $expressPrice) {
            if ($params['logistics_channel_id'] == $expressPrice['id']) {
                $shippingFee = $expressPrice['expire_fee'] / 100;
                break;
            }
        }

        return $shippingFee;
    }


    public function customerQuote($id)
    {
        $product = PlatformProduct::query()->findOrFail($id);
        $countryList = Country::query()->whereIn('id', $product->apply_country_ids ?:[])->get();
        $skuList = PlatformProductSku::query()->with(['goodsSku.skuQuotationGroup.skuQuotationGroupAttr', 'goodsSku.skuQuotationGroup.expressLine'])->where('product_id', $id)->whereHas('goodsSku')->get();
        foreach ($skuList as $sku) {
            $skuQuotation = [];
            $skuQuotationGroup = !empty($sku->goodsSku->skuQuotationGroup) ? $sku->goodsSku->skuQuotationGroup->keyBy('country_id') : [];
            foreach ($countryList as $country) {
                $countryQuote = $skuQuotationGroup[$country->id] ?? null;
                $countryData = $country->toArray();
                $countryData['express_line_id'] = ($countryQuote->express_line_id ?? '') ?: '';
                $countryData['quotation'] = $countryQuote->skuQuotationGroupAttr ?? [];
                $countryData['express_line'] = $countryQuote->expressLine ?? [];
                $countryData['express_line_info'] = $countryQuote->express_line_info ?? [];
                $skuQuotation[] = $countryData;
            }
            $sku->skuQuotation = $skuQuotation;
        }
        return $skuList;
    }

    public function saveCustomerQuote($id, $params)
    {
        validator($params, $this->saveCustomerQuoteRule())->validate();
        $platformProduct = PlatformProduct::query()->findOrFail($id);
        return DB::transaction(function () use ($platformProduct, $params) {
            foreach ($params['sku_list'] as $sku) {
                $productSku = PlatformProductSku::query()->with('goodsSku')->findOrFail($sku['id']);
                if (empty($productSku->goodsSku)) throw new AccidentException('商品未关联本地品');
                foreach ($sku['country_quote'] as $countryQuote) {
                    // 去除空数据
                    $countryQuote['attr_list'] = $this->removeEmptyField($countryQuote['attr_list'], 'quantity');
                    //去重
                    $countryQuote['attr_list'] = $this->uniqueByField($countryQuote['attr_list'], 'quantity');
                    // 为空则删除并跳过
                    if (empty($countryQuote['attr_list'])) {
                        SkuQuotationGroupModel::query()->where([
                            'country_id' => $countryQuote['country_id'],
                            'custom_id' => $platformProduct->custom_id,
                            'sku_id' => $productSku->goodsSku->id,
                        ])->delete();
                        continue;
                    }

                    $quotationGroup = SkuQuotationGroupModel::query()->updateOrCreate([
                        'country_id' => $countryQuote['country_id'],
                        'custom_id' => $platformProduct->custom_id,
                        'sku_id' => $productSku->goodsSku->id,
                    ], [
                        'express_line_id' => $countryQuote['express_line_id'] ?? 0,
                        'express_line_info' => $countryQuote['express_line_info'] ?? 0
                    ]);
                    SkuQuotationGroupAttrModel::query()->where('parent_id', $quotationGroup->id)->update(['is_new' => 0]);
                    foreach ($countryQuote['attr_list'] as $attrQuote) {
                        if (empty($attrQuote['quantity'])) continue;
                        SkuQuotationGroupAttrModel::query()->create([
                            'parent_id' => $quotationGroup->id,
                            'quantity' => $attrQuote['quantity'],
                            'price' => $attrQuote['price'],
                        ]);
                    }
                }
            }
            return true;
        });
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
            'country_id' => 'required|sometimes|int'
        ];
    }

    public function saveCustomerQuoteRule()
    {
        return [
            'sku_list' => 'required|array',
            'sku_list.*.id' => 'required|int',
            'sku_list.*.goods_sku_id' => 'required|int',
            'sku_list.*.country_quote' => 'required|array',
            'sku_list.*.country_quote.*.country_id' => 'required|int',
            'sku_list.*.country_quote.*.logistics_channel_id' => 'sometimes|nullable|int',
            'sku_list.*.country_quote.*.attr_list' => 'sometimes|nullable|array',
            'sku_list.*.country_quote.*.attr_list.*quantity' => 'sometimes|int',
            'sku_list.*.country_quote.*.attr_list.*price' => 'required_with:quantity|numeric',
        ];
    }

    function uniqueByField($array, $field) {
        $fieldValues = array_unique(array_column($array, $field));
        return array_intersect_key($array, $fieldValues);
    }

    function removeEmptyField($array, $field) {
        return array_filter($array, function ($item) use ($field) {
            return !empty($item[$field]); // 只保留字段不为空的项
        });
    }

}
