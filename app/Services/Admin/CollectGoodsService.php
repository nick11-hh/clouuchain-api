<?php

namespace App\Services\Admin;


use App\Helper\CurrencyConverter;
use App\Lib\Code;
use App\Models\AdminCollectGoods;
use App\Models\AdminCollectGoodsSku;
use App\Models\Goods;
use App\Models\Supplier;
use App\Services\Collect\CollectService;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class CollectGoodsService extends BaseService
{
    public $filterRules = [
        'goods_name'             => ['like', 'goods_name'],
        'spu'                    => ['=', 'spu'],
//        'category_id'            => ['=', 'category_id'],
        'status'                 => ['=', 'status'],
        'created_at'             => ['between', ['begin_date', 'end_date']],
        'goods_type'             => ['=', 'goods_type'],
        'packing_materials_type' => ['=', 'packing_materials_type'],
    ];

    private AdminCollectGoodsSku $skuModel;

    public function __construct()
    {
        $this->model = new AdminCollectGoods();
        $this->skuModel = new AdminCollectGoodsSku();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['category', 'supplier']);

        if (isset($this->formData['supplier_id']) && is_numeric($this->formData['supplier_id'])) {
            $this->query->where('supplier_id', $this->formData['supplier_id']);
        }

        // 处理分类筛选逻辑
        $categoryId = $this->formData['category_id'] ?? '';
        if ($categoryId) {
            $category = \App\Models\GoodsCategory::find($categoryId);
            if ($category) {
                if ($category->parent_id == 0) {
                    // 如果是一级分类，获取该分类及其所有子集分类下的商品
                    $categoryIds = $this->getAllChildCategoryIds($categoryId);
                    $categoryIds[] = (int)$categoryId;

                    // 确保 categoryIds 不为空且包含有效数据
                    if (!empty($categoryIds)) {
                        $this->query->whereIn('category_id', $categoryIds);
                    } else {
                        // 如果没有子分类，只查询当前一级分类
                        $this->query->where('category_id', $categoryId);
                    }
                } else {
                    // 如果是子集分类，只获取该子分类下的商品
                    $this->query->where('category_id', $categoryId);
                }
            }
        }

        $this->query->latest();
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['skus', 'category', 'supplier'])->findOrFail($id);
    }

    /**
     * @param $params
     * @return array
     * @throws \Exception
     */
    public function collect($params)
    {
        validator($params, $this->collectRule())->validate();
        $urlList = explode("\n", $params['url']);
        if (count($urlList) > 20) throw new AccidentException('最多同时支持20个链接采集');
        $successCount = 0;
        $errorCount = 0;
        $errorList = [];
        foreach ($urlList as $url) {
            try {
                $collectService = new CollectService();
                $productData = $collectService->getProductDataByUrl($url, $params['language']);
                $product = $this->model::query()->where('spu', 'like', $productData['goods_id'] . '%')->latest()->first();

                if ($product && empty($params['repeat_collect'])) {
                    throw new AccidentException('该商品已采集，请在采集产品中查看', Code::CUSTOM_ERROR);
                }

                if ($product) {
                    $spuIndex = explode('_', $product->spu)[1] ?? 0;
                    $productData['goods_id'] = $product->spu . '_' . $spuIndex + 1;
                }

                $wangwangNick = '';
                if($productData['shop_id']){

                    try {
                        $res = $collectService->getWangWangNick($productData['shop_id']);

                        $wangwangNick = $res['wangwangNick'];

                    } catch (Exception $e) {
                        $wangwangNick = '';
                    }

                }

                DB::transaction(function () use ($params, $productData, $wangwangNick) {

                    //添加供应商
                    if($wangwangNick){

                        $supplier = Supplier::where('supplier_name', $wangwangNick)
                            ->where('type', Supplier::TYPE_ALIBABA)
                            ->first();
                        if(!$supplier){

                            $supplier = Supplier::query()->create([
                                'supplier_name' => $wangwangNick,
                                'supplier_code' => $productData['shop_id'],
                                'type' => Supplier::TYPE_ALIBABA,
                            ]);
                        }

                        $productData['supplier_id'] = $supplier->id;

                    }

                    $data = $this->model::init($productData);
                    $product = $this->model::query()->create($data);

                    foreach ($productData['sku_list'] as $sku) {
                        $sku['goods_id'] = $product->id;
                        $sku['prop_id'] = 1;

                        $skuData = $this->skuModel::init($sku);
                        $goodsSku = $this->skuModel::query()->create($skuData);
                    }

                    $product->purchase_price = min(array_column($productData['sku_list'], 'sale_price'));
                    $product->save();
                });
                $successCount ++;
            } catch (\Exception $exception) {
                $errorCount ++;
                $errorList[] = $exception->getMessage();
            }
        }

        return [
            'success' => $successCount,
            'error' => $errorCount,
            'error_list' => $errorList,
        ];
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        validator($params, $this->rules(), [
            'category_id.required_if' => '商品尚未分类，请选择分类！',
            'packing_materials_type.required_if' => '请选择包材类型',
            'supplier_id.required' => '请选择供应商',
        ])->validate();

        return DB::transaction(function () use ($id, $params) {
            $goods = $this->model::query()->with('skus')->findOrFail($id);
            $goodsData = [
                'goods_name' => $params['goods_name'],
                'category_id' => $params['category_id'] ?? 0,
                'category_name' => $params['category_name'] ?? '',
                'brand' => $params['brand'] ?? '',
                'unit' => $params['unit'] ?? '',
                'purchase_price' => $params['purchase_price'] ?? 0,
                'collect_platform' => $params['collect_platform'] ?? '',
                'cover_image' => $params['cover_image'],
                'main_images' => $params['main_images'] ?? [],
                'options' => $params['options'] ?? [],
                'detail' => $params['detail'] ?? '',
                'goods_type' => $params['goods_type'] ?? 1,
                'packing_materials_type' => $params['packing_materials_type'] ?? 0,
                'supplier_id' => $params['supplier_id'] ?? 0,
            ];
            $goods->update($goodsData);
            $minPrice = 0;
            $skuIds = [];
            foreach ($params['skus'] as $sku) {
                $purchase_price = round(bcdiv($sku['purchase_price'], 6.9, 4), 2);
                if ($minPrice == 0 || $minPrice > $purchase_price) $minPrice = $purchase_price;
//                if ($minPrice == 0 || $minPrice > $sku['sale_price']) $minPrice = $sku['sale_price'];
                $skuData = [
                    'sku_id' => $sku['sku_id'],
                    'prop_id' => $sku['prop_id'],
                    'spec_name' => $sku['spec_name'] ?? '',
                    'spec_name_cn' => $sku['spec_name_cn'] ?? '',
                    'spec_info' => $sku['spec_info'] ?? [],
                    'sale_price' => $sku['sale_price'],
                    //'compare_price' => $sku['compare_price'],
                    'images' => !empty($sku['images']) ? $sku['images'] : [$params['cover_image'] ?? ''],
                    'status' => $sku['status'] ?? 1,
                    'length' => $sku['length'] ?? 0,
                    'width' => $sku['width'] ?? 0,
                    'height' => $sku['height'] ?? 0,
                    'weight' => $sku['weight'] ?? 0,
                    'purchase_price' => $sku['purchase_price'] ?? 0,
                    'profit_margin' => $sku['profit_margin'] ?? 20,
                ];


                $skuData['quote_price'] = $sku['quote_price'];
//                $skuData['sale_price'] = $sku['quote_price'];
//                $skuData['sale_price'] = $purchase_price;
                $skuData['profit'] = 0;
//                $skuData['profit'] = $sku['profit'] ?? 0;//直接保存前端传来的利润

                //报价价格计算设置方式 1利润=产品报价-采购价 2报价=采购价+利润
//                $quotePriceSettingType = $sku['quote_price_setting_type'] ?? 1;
//                if ($quotePriceSettingType == 1) {
//                    $skuData['quote_price'] = $sku['quote_price'];
//                    $skuData['sale_price'] = $sku['quote_price'];
//                    //利润=产品报价-采购价
//                    $skuData['profit'] = $sku['quote_price'] - $sku['purchase_price'];
//                } else {
//                    //报价=采购价+利润
//                    $skuData['quote_price'] = $sku['purchase_price'] + $sku['profit'];
//                    $skuData['sale_price'] = $sku['purchase_price'] + $sku['profit'];
//                    $skuData['profit'] = $sku['profit'];
//                }



                if (!empty($sku['id'])) {
                    $goodsSku = $this->skuModel::query()->findOrFail($sku['id']);
                    $goodsSku->update($skuData);
                    $skuIds[] = $sku['id'];
                } else {
                    $skuData['goods_id'] = $goods->id;
                    $this->skuModel::query()->create($skuData);
                }
            }
            // 删除多余产品sku
            $oldSkuIds = $goods->skus->pluck('id')->toArray();
            $deleteIds = array_diff($oldSkuIds, $skuIds);
            $this->skuModel::query()->whereIn('id', $deleteIds)->delete();

            $goods->purchase_price = $minPrice;
            $goods->save();
            return true;
        });
    }

    /**
     * @param $params
     * @return mixed
     */
    public function claim($params)
    {
        return DB::transaction(function () use ($params) {
            $collectGoods = $this->model::query()->with('skus')->whereIn('id', $params['ids'])->get();
            $service = new GoodsService();
            foreach ($collectGoods as $goods) {
                $isBatchClaim = $params['is_batch_claim'] ?? 0;
                if ($isBatchClaim) {
                    if (empty($goods->supplier_id)) {
                        continue;
                    }

                    $hasInvalidSku = false;
                    $goods->skus->each(function ($sku) use ($goods, &$hasInvalidSku) {
                        if (empty($sku->weight)) {
//                        if (empty($sku->length) || empty($sku->width) || empty($sku->height) || empty($sku->weight)) {
                            $hasInvalidSku = true;
                        }
                    });

                    if ($hasInvalidSku) {
                        continue;
                    }
                } else {
                    //校验供应商
                    if (empty($goods->supplier_id)) {
                        throw new AccidentException($goods->spu . ': 该产品未选择供应商，请选择供应商后再认领。', Code::OPERATE_FAIL);
                    }

                    //校验sku的规格尺寸
                    $goods->skus->each(function ($sku) use ($goods) {
                        if (empty($sku->weight)) {
//                        if (empty($sku->length) || empty($sku->width) || empty($sku->height) || empty($sku->weight)) {
                            throw new AccidentException($goods->spu . ': 该产品缺少重量信息，请补充后再认领。', Code::OPERATE_FAIL);
                        }
                    });
                }

                $data = $this->transformGoods($goods);
                $result = $service->store($data, $isBatchClaim == 1);
                if ($result !== false) {
                    $goods->status = $this->model::STATUS_CLAIM;
                    $goods->save();
                }
            }
            return true;
        });
    }


    public function deletes($params)
    {
        $ids = $params['ids'];
        if (empty($ids)) throw new AccidentException('请选择需要删除的产品', Code::OPERATE_FAIL);
        $list = $this->model::with('skus')->whereIn('id', $params['ids'])->get();
        foreach ($list as $goods) {
            $goods->skus()->delete();
            $goods->delete();
        }
        return true;
    }

    /**
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    public function getDetail($params)
    {
        $collectService = new CollectService(1688, $params['language']);
        return $collectService->getProductDataByUrl($params['url'], $params['language']);
    }

    /**
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public function collectAndClaim($params)
    {
        validator($params, [
            'url' => 'required|string',
            'language' => 'required|string',
            'weight' => 'required|numeric',
            'category_id' => 'required|int',
            'supplier_id' => 'required|int',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $productData = (new CollectService())->getProductDataByUrl($params['url'], $params['language']);

            $product = $this->model::query()->where('spu', $productData['goods_id'])->first();

            if ($product) {
                if ($product->status === AdminCollectGoods::STATUS_DEFAULT) {
                    throw new AccidentException('采集管理里已存在该产品，请去认领', Code::OPERATE_FAIL);
                }

                return true;
            }

            //分类跟供应商
            $productData['category_id'] = $params['category_id'];
            $productData['supplier_id'] = $params['supplier_id'];
            $productData['purchase_price'] = min(array_column($productData['sku_list'], 'sale_price'));

            $data = $this->model::init($productData);
            $product = $this->model::query()->create($data);

            foreach ($productData['sku_list'] as $sku) {
                $sku['goods_id'] = $product->id;
                $sku['weight'] = $params['weight'] * 1000;
                $skuData = $this->skuModel::init($sku);
                $this->skuModel::query()->create($skuData);
            }

            $goods = $product->load(['skus']);

            //认领到产品库
            $data = $this->transformGoods($goods);
            (new GoodsService())->store($data);

            //更新认领状态
            $product->status = AdminCollectGoods::STATUS_CLAIM;
            $product->save();

            return true;
        });
    }

    public function rules()
    {
        return [
            'goods_name' => 'required|string',
            'goods_type' => 'required|int',//商品类型 1产品 2包材
            'category_id' => 'required_if:goods_type,1|nullable|int',//商品类型为产品时必填
            'packing_materials_type' => 'required_if:goods_type,2|nullable|int',//商品类型为包材时必填
            'supplier_id' => 'required|int',//供应商ID
            'cover_image' => 'required|string',
            'main_images' => 'required|array',
            'detail' => 'required|string',
            'options' => 'sometimes|nullable|array',
            'brand' => 'sometimes|nullable|string',
            'unit' => 'sometimes|nullable|string',
            'purchase_price' => 'sometimes|nullable',
            'collect_url' => 'sometimes|nullable|string',
            'props' => 'sometimes|nullable|array',
//            'skus' => 'required|array',
            'skus.*.sku_id' => 'required|string',
            'skus.*.spec_name' => 'required|string',
            'skus.*.sale_price' => 'sometimes|nullable|string',
            'skus.*.compare_price' => 'sometimes|nullable|string',
            'skus.*.images' => 'sometimes|nullable|array',
            'skus.*.status' => 'required|int',
            'skus.*.spec_info' => 'sometimes|nullable|array',
            'skus.*.purchase_price' => 'sometimes|nullable|numeric',
            'skus.*.profit' => 'sometimes|nullable|numeric',
            'skus.*.profit_margin' => 'sometimes|nullable|numeric|min:5',
            'skus.*.quote_price' => 'sometimes|nullable|numeric',

        ];
    }

    public function collectRule()
    {
        return [
            'url' => 'required|string',
            'language' => 'required|string'
        ];
    }

    public function claimRule()
    {
        return [
            'ids' => 'required|array',
            'category_id' => 'sometimes|nullable|int'
        ];
    }

    public function transformGoods($goods)
    {
        $skuList = [];
        foreach ($goods->skus as $sku) {
            //添加供货关系 供应商、spu、采购规格
            $goodsSuppliers = [];
            if ($goods->supplier_id && $goods->spu && $sku->purchase_spec_id) {
                $goodsSuppliers[] = [
                    'supplier_id' => $goods->supplier_id,//供应商ID
                    'price' => $sku->compare_price ?? 0,//采购价格
                    'currency' => 'CNY',//币种
                    'purchase_type' => 1,//采购类型 1 1688 2 线下
                    'purchase_url' => $goods->collect_url ?? '',//采购链接
                    'purchase_goods_name' => $goods->goods_name ?? '',//商品名称
                    'purchase_spec_image' => current($sku->images),//商品图片
                    'purchase_spec_name' =>  $sku->spec_name ?? '',//规格名称
                    'purchase_goods_id' => $goods->spu,//商品ID 1688采购下单时的 offerID
                    'purchase_sku_id' => $sku->sku_id ?? '',//商品skuID
                    'purchase_spec_id' => $sku->purchase_spec_id,//采购规格ID 1688采购下单时的 specID
                    'is_default' => 1,
                ];
            }

            $quote_price = $sku->quote_price;
            if ($quote_price < 0) {
                $quote_price = 0;
            }
            $skuList[] = [
                'sku_id' => $sku->sku_id,
                'spec_name' => $sku->spec_name,
                'spec_name_cn' => $sku->spec_name_cn,
                'sale_price' => $sku->sale_price ?? 0,//售价
                'original_price' => $sku->compare_price ?? 0,//原价
                'purchase_price' => $sku->purchase_price ?? 0,//采购价
                'profit' => $sku->profit ?? 0,//利润
                'profit_margin' => $sku->profit_margin ?? 0,//加价比例
                'quote_price' => $quote_price,//报价
                'images' => $sku->images,
                'spec_info' => $sku->spec_info,
                'status' => 1,
                'purchase_spec_id' => $sku->purchase_spec_id,
                'length' => $sku->length ?? 0,
                'width' => $sku->width ?? 0,
                'height' => $sku->height ?? 0,
                'weight' => $sku->weight ?? 0,
                'goods_suppliers' => $goodsSuppliers,//供货关系
            ];
        }
        return [
            'spu' => $goods->spu,
            'goods_name' => $goods->goods_name,
            'category_id' => $goods->category_id ?? 0,
            'cover_image' => $goods->cover_image,
            'main_images' => $goods->main_images,
            'detail' => $goods->detail,
            'options' => $goods->options,
            'purchase_url' => $goods->collect_url,
            'purchase_price' => $goods->purchase_price,
            'purchase_platform' => $goods->collect_platform,
            'purchase_product_id' => $goods->purchase_product_id,
            'skus' => $skuList,
            'goods_type' => $goods->goods_type ?? 1,
            'packing_materials_type' => $goods->packing_materials_type ?? 0,
        ];
    }

    /**
     * 递归获取所有子分类 ID
     */
    private function getAllChildCategoryIds($categoryId)
    {
        $childCategories = \App\Models\GoodsCategory::where('parent_id', $categoryId)->get();
        $categoryIds = [];

        foreach ($childCategories as $child) {
            $categoryIds[] = $child->id;
            // 递归获取子分类的子分类
            $childCategoryIds = $this->getAllChildCategoryIds($child->id);
            $categoryIds = array_merge($categoryIds, $childCategoryIds);
        }

        return $categoryIds;
    }

}
