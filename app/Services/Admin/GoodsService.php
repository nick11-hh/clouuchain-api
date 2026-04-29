<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\Goods;
use App\Models\GoodsAudit;
use App\Models\GoodsGroupItem;
use App\Models\GoodsSku;
use App\Models\GoodsSupplier;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\SkuQuotationGroupModel;
use App\Models\SystemConfig;
use App\Models\CustomsQuoteConfig;
use App\Services\Base\SystemConfigService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\Admin\GoodsImportMain;
use App\Jobs\PushGoodsToMabangJob;
use App\Exceptions\AccidentException;
use App\Helper\CurrencyConverter;


class GoodsService extends BaseService
{
    public $filterRules = [
        'goods_name' => ['like', 'goods_name'],
        'spu' => ['=', 'spu'],
        'goods_type' => ['=', 'goods_type'],
//        'category_id' => ['=', 'category_id'],
        'packing_materials_type' => ['=', 'packing_materials_type'],
//        'status'         => ['=', 'status'],
//        'is_hot'         => ['=', 'is_hot'],
        'created_at' => ['between', ['begin_date', 'end_date']],
        'goods_name,spu;skus:sku_id' => ['like', 'keyword'],
        'is_group' => ['=', 'is_group']
    ];

    private GoodsSku $skuModel;

    public function __construct()
    {
        $this->model = new Goods();
        $this->skuModel = new GoodsSku();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['developer'])->with('goodsAudit');
        $this->query->with(['category'])->withCount(['skus', 'skus as wait_push_to_mabang_count' => function (Builder $query) {
                    $query->where('mabang_stock_id', 0);
                },
            ]);
        if (isset($this->formData['is_hot']) && $this->formData['is_hot'] !== '') {
            $this->query->where('is_hot', $this->formData['is_hot']);
        }
        if (isset($this->formData['origin_type']) && $this->formData['origin_type'] !== '') {
            $this->query->where('origin_type', $this->formData['origin_type']);
        }
        if (isset($this->formData['status']) && $this->formData['status'] !== '') {
            $this->query->where('status', $this->formData['status']);
        }
        if (isset($this->formData['status_1688']) && $this->formData['status_1688'] !== '') {
            $this->query->where('status_1688', $this->formData['status_1688']);
        }
        if (isset($this->formData['audit_status']) && $this->formData['audit_status'] !== '') {
            $this->query->whereIn(
                'id',
                GoodsAudit::where('audit_status', $this->formData['audit_status'])
                    ->pluck('goods_id')
                    ->toArray()
            );
        }
        if (isset($this->formData['sku']) && $this->formData['sku'] !== '') {
            $this->query->whereIn(
                    'id',
                    GoodsSku::where('sku_id', 'like', '%'.$this->formData['sku'].'%')
                        ->pluck('goods_id')
                        ->toArray()
                    );
        }
        if (isset($this->formData['developer_id']) && is_numeric($this->formData['developer_id'])) {
            $this->query->where('developer_id', $this->formData['developer_id']);
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
        return $this->model::query()->with(['skus', 'category',
            'skus.goodsSuppliers.supplier:id,supplier_name',
            'skus.logistics',
            'skus.orderItemMapping.orderLineItem.shopOrder:id,shop_id',
            'skus.orderItemMapping.orderLineItem.shopOrder.shop:id,shop_name',
            'skus.groupItems.goodsSku.goods'
        ])->findOrFail($id);
    }

    public function skuShow($id)
    {
        return $this->skuModel::query()->with([
            'goods:id,goods_name,spu,category_id,unit,brand,developer_id,purchase_url',
            'goods.category:id,name',
            'goodsSuppliers.supplier:id,supplier_name',
            'logistics',
            'orderItemMapping.orderLineItem.shopOrder:id,shop_id',
            'orderItemMapping.orderLineItem.shopOrder.shop:id,shop_name'])->findOrFail($id);
    }



    public function skuList($params)
    {
        $query = $this->skuModel::query()->with([
            'goods:id,goods_name,spu,category_id,goods_type,packing_materials_type,purchase_url,cover_image',
            'goods.category:id,name',
            'logistics',
            'goodsSuppliers.supplier:id,supplier_name',
        ]);

        $keywordType = $params['keyword_type'] ?? 1;
        $keyword = $params['keyword'] ?? '';
        $categoryId = $params['category_id'] ?? '';
        $goodsId = $params['goods_id'] ?? '';

        if ($keyword) {
            switch ($keywordType) {
                case 1:
                    //SKU
                    $query->where('sku_id', 'like', '%'.$keyword.'%');
                    break;
                case 2:
                    //商品名称
                    $query->whereHas('goods', function ($query) use ($keyword) {
                        $query->where('goods_name', 'like', '%'.$keyword.'%');
                    });
                    break;
                case 3:
                    //规格名称
                    $query->where('spec_name', 'like', '%'.$keyword.'%');
                    break;
                case 4:
                    //1688商品链接
                    // 解析URL以获取路径
                    $path = parse_url($keyword, PHP_URL_PATH);
                    // 获取不带后缀的文件名 1688商品ID
                    $keyword = pathinfo($path, PATHINFO_FILENAME);

                    //查询采购链接、采购ID、spu
                    $query->whereHas('goods', function ($query) use ($keyword) {
                        $query->where('spu', 'like', '%'.$keyword.'%')
                              ->orWhere('purchase_url', 'like', '%'.$keyword.'%')
                              ->orWhere('purchase_product_id', 'like', '%'.$keyword.'%');
                    });
                    break;
            }
        }

        $query->when($categoryId, function ($query) use ($categoryId) {
            return $query->whereHas('goods', function ($query) use ($categoryId) {
                return $query->where('category_id', $categoryId);
            });
        });

        $query->when($goodsId, function ($query) use ($goodsId) {
            return $query->where('goods_id', $goodsId);
        });

        if (isset($params['is_group'])) {
            $query->where('is_group', $params['is_group']);
        }


        // 产品开发管理 sku列表
        $goodsName = $params['goods_name'] ?? '';
        $spu = $params['spu'] ?? '';
        $isHot = $params['is_hot'] ?? '';
        $beginDate = $params['begin_date'] ?? '';
        $endDate = $params['end_date'] ?? '';
        $goodsType = $params['goods_type'] ?? '';
        $packingMaterialsType = $params['packing_materials_type'] ?? '';

        $query->when($goodsName, function ($query) use ($goodsName) {
            return $query->where(function ($query) use ($goodsName) {
                return $query->where('sku_name', 'like', "%$goodsName%")
                    ->orWhereHas('goods', function ($query) use ($goodsName) {
                        return $query->where('goods_name', 'like', "%$goodsName%");
                    });
            });
        });

        $query->when($spu, function ($query) use ($spu) {
            return $query->where(function ($query) use ($spu) {
                return $query->where('sku_id', $spu)
                    ->orWhereHas('goods', function ($query) use ($spu) {
                        return $query->where('spu', $spu);
                    });
            });
        });

        if ($isHot !== '') {
            $query->whereHas('goods', function ($query) use ($isHot) {
                return $query->where('is_hot', $isHot);
            });
        }

        $query->when($beginDate, function ($query) use ($beginDate, $endDate) {
            return $query->whereBetween('updated_at', [$beginDate, $endDate]);
        });

        $query->when($goodsType, function ($query) use ($goodsType) {
            return $query->whereHas('goods', function ($query) use ($goodsType) {
                return $query->where('goods_type', $goodsType);
            });
        });

        $query->when($packingMaterialsType, function ($query) use ($packingMaterialsType) {
            return $query->whereHas('goods', function ($query) use ($packingMaterialsType) {
                return $query->where('packing_materials_type', $packingMaterialsType);
            });
        });


        return $query->latest('id')->paginate($params['size'] ?? 10);
    }

    public function skuQuotationList($params)
    {
        $keyword = $params['keyword'] ?? '';
        $categoryId = $params['category_id'] ?? '';
        $goodsType = $params['goods_type'] ?? '';
//        $skuQuotationData = SkuQuotationGroupModel::query()->select('sku_id')->get()->toArray();

        $query = $this->skuModel::query()->with('goods');

//        $query->whereNotIn('id', array_column($skuQuotationData, 'sku_id'));
        $query->when($keyword, function ($query) use ($keyword) {
            return $query->where(function ($query) use ($keyword) {
                return $query->where('spec_name', 'like', "%$keyword%")->orWhere('sku_id', 'like', "%$keyword%")
                    ->orWhereHas('goods', function ($query) use ($keyword) {
                        return $query->where('goods_name', 'like', "%$keyword%");
                    });
            });
        });
        $query->when($categoryId, function ($query) use ($categoryId) {
            return $query->whereHas('goods', function ($query) use ($categoryId) {
                return $query->where('category_id', $categoryId);
            });
        });
        $query->when($goodsType, function ($query) use ($goodsType) {
            return $query->whereHas('goods', function ($query) use ($goodsType) {
                return $query->where('goods_type', $goodsType);
            });
        });
        return $query->latest('id')->paginate($params['size'] ?? 10);
    }

    public function spuQuotationList($params)
    {
        if ($params['is_default'] == 'true') {
            return $this->skuModel::query()->select(['sku_id', 'goods_id', 'id'])->where('goods_id', $params['goods_id'])->get();
        } else {
            return $this->skuModel::query()->select(['sku_id', 'goods_id', 'id'])->where('id', $params['id'])->get();
        }
    }

    /**
     * 更新商品区间价格
     * **/
    public function updateMaxMinPrice(Goods $model) {

        $maxPurchasePrice = 0;//最大采购价
//        $minPurchasePrice = PHP_INT_MAX;//最小采购价
        $minPurchasePrice = 99999999.99;//最小采购价
        $maxSalePrice = 0;//最大销售价
//        $minSalePrice = PHP_INT_MAX;//最小销售价
        $minSalePrice = 99999999.99;//最小销售价

        $model->load('skus');
        if($model->skus) {
            foreach ($model->skus as $sku) {
                $maxPurchasePrice = max($maxPurchasePrice, $sku['purchase_price']);
                $minPurchasePrice = min($minPurchasePrice, $sku['purchase_price']);
                $maxSalePrice = max($maxSalePrice, $sku['sale_price']);
                $minSalePrice = min($minSalePrice, $sku['sale_price']);
            }
        }

        $model->max_purchase_price = $maxPurchasePrice;
        $model->min_purchase_price = $minPurchasePrice;
        $model->max_sale_price = $maxSalePrice;
        $model->min_sale_price = $minSalePrice;
        $model->save();

        return $model;
    }

    /**
     * @param $params
     * @return mixed
     */
    public function store($params, $isBatchClaim = 0)
    {
        validator($params, $this->rules())->validate();
        $params['developer_id'] = auth('admin')->id();

        return DB::transaction(function () use ($params, $isBatchClaim) {
            //校验sku是否重复
            $skuList = $params['skus'];
            $skuIdsAll = array_column($skuList, 'sku_id');
            $skuIds = array_unique($skuIdsAll);
            $duplicates = array_diff_assoc($skuIdsAll, $skuIds);
            if ($duplicates) {
                throw new AccidentException('SKU ID 存在重复值: ' . implode(',', $duplicates), Code::OPERATE_FAIL);
            }

            //校验sku是否存在
            $skuExist = $this->skuModel::query()->whereIn('sku_id', $skuIds)->pluck('sku_id')->toArray();
            if ($skuExist) {
                if (!$isBatchClaim) {
//                    throw new AccidentException('产品已存在', Code::OPERATE_FAIL);
                    throw new AccidentException('SKU ID 已存在：' . implode(',', $skuExist), Code::OPERATE_FAIL);
                } else {
                    return false;
                }
            }

            $goodsData = Goods::init($params);
            $goods = $this->model::query()->create($goodsData);
            $minPrice = 0;

            // 初始化 goodsSku 变量
            $goodsSku = null;

            if(is_array($params['skus']) && !empty($params['skus'])) {
                foreach ($params['skus'] as $sku) {
                    if ($minPrice == 0 || $minPrice > $sku['quote_price']) $minPrice = $sku['quote_price'];

                    $sku['goods_id'] = $goods->id;
                    $sku['is_group'] = $goods->is_group;
                    $skuData = GoodsSku::init($sku, goodsData: $goodsData);
                    $goodsSku = $this->skuModel::query()->create($skuData);

                    //更新sku报关信息、采购信息
                    $this->updateSkuExtend($goods->id, $goodsSku->id, $sku);

                    //这里推送给马帮
//                    PushGoodsToMabangJob::dispatch(PushGoodsToMabangJob::TYPE_10, $goodsSku, getAdminId());


                    // 组合 SKU item 保存
                    if ($goods->is_group) {
                        foreach ($params['group_items'] as $item) {
                            GoodsGroupItem::query()->create([
                                'group_sku_id' => $goodsSku->id,
                                'goods_sku_id' => $item['goods_sku_id'],
                                'quantity' => $item['quantity'],
                            ]);
                        }
                    }
                }
            } else {
                $params['goods_id'] = $goods->id;
                $params['is_group'] = $goods->is_group;
                $params['purchase_price'] = 10;
                $params['images'] = $params['cover_image'];
                $skuData = GoodsSku::init($params, goodsData: []);
                $goodsSku = $this->skuModel::query()->create($skuData);
                //更新sku报关信息、采购信息
                $this->updateSkuExtend($goods->id, $goodsSku->id, $params);
            }


            $goods->goods_lowest_price = $minPrice;
            $goods->save();
            $goodAudit = GoodsAudit::init($goods->id);
            GoodsAudit::insert($goodAudit);
            // 检查 goodsSku 是否存在
            if ($goodsSku) {
                $model = $goodsSku->goods->fresh();
            } else {
                // 如果没有 SKU，也更新商品信息
                $model = $goods->fresh();
            }
            $this->updateMaxMinPrice($model);
            /*if (!empty($params['logistics'])) {
                $logisticsData = LogisticsCustomsDeclarationModel::init($goods->id, $params['logistics']);
                LogisticsCustomsDeclarationModel::create($logisticsData);
            }*/
            return true;
        });
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
            $goods = $this->model::query()->with('skus')->findOrFail($id);
            $goodsData = $this->model::init($params, 2);
            $goods->update($goodsData);
            $minPrice = 0;

            $skuList = $params['skus'];
            $skuIdsAll = array_column($skuList, 'sku_id');
            $skuIds = array_unique($skuIdsAll);
            $duplicates = array_diff_assoc($skuIdsAll, $skuIds);
            if ($duplicates) {
                throw new AccidentException('SKU ID 存在重复值: ' . implode(',', $duplicates), Code::OPERATE_FAIL);
            }

            $type = PushGoodsToMabangJob::TYPE_13;
            foreach ($params['skus'] as $sku) {
                if ($minPrice == 0 || $minPrice > $sku['quote_price']) $minPrice = $sku['quote_price'];
                $sku['goods_id'] = $goods->id;
                $sku['is_group'] = $goods->is_group;
                if (!empty($sku['id'])) {
                    $skuData = $this->skuModel::init($sku, 2, $goodsData);
                    $goodsSku = $this->skuModel::query()->findOrFail($sku['id']);

                    if($goodsSku->sku_id != $skuData['sku_id']){
                        $type = PushGoodsToMabangJob::TYPE_12;
                    }

                    $goodsSku->update($skuData);
                    $skuIds[] = $sku['id'];
                } else {
                    $skuData['goods_id'] = $goods->id;
                    $skuData = $this->skuModel::init($sku, goodsData: $goodsData);
                    $goodsSku = $this->skuModel::query()->create($skuData);

                    $type = PushGoodsToMabangJob::TYPE_12;
                }

                //更新sku报关信息、采购信息
                $this->updateSkuExtend($goods->id, $goodsSku->id, $sku);

                // 组合 SKU item 保存
                if ($goods->is_group) {
                    if (empty($params['group_items'])) {
                        throw new AccidentException('请添加需要组合的商品');
                    }
                    foreach ($params['group_items'] as $item) {
                        GoodsGroupItem::query()->updateOrCreate([
                            'group_sku_id' => $goodsSku->id,
                            'goods_sku_id' => $item['goods_sku_id']
                        ], [
                            'quantity' => $item['quantity'],
                        ]);
                    }
                    $goodsSkuIds = array_column($params['group_items'], 'goods_sku_id');
                    GoodsGroupItem::query()->where('group_sku_id', $goodsSku->id)->whereNotIn('goods_sku_id', $goodsSkuIds)->delete();
                }

                //这里推送给马帮
//                PushGoodsToMabangJob::dispatch($type, $goodsSku, getAdminId());
            }
            // 删除多余产品sku
            $oldSkuIds = $goods->skus->pluck('id')->toArray();
            $deleteIds = array_diff($oldSkuIds, $skuIds);
            $this->skuModel::query()->whereIn('id', $deleteIds)->delete();
            $goods->goods_lowest_price = $minPrice;

            $res =  $goods->save();
            $model = $goods->fresh();
            $this->updateMaxMinPrice($model);

            return $res;
        });
    }

    /**
     * @param $id
     * @param $params
     * @return bool
     */
    public function skuUpdate($id, $params): bool
    {
        validator($params, $this->skuRules())->validate();

        return DB::transaction(function () use ($id, $params) {
            $goodsSku = $this->skuModel::query()->findOrFail($id);
            $type = ($goodsSku->sku_id != $params['sku_id']) ? PushGoodsToMabangJob::TYPE_12 : PushGoodsToMabangJob::TYPE_13;

            $skuData = $this->skuModel::init($params, 2);
            $goodsSku->update($skuData);

            //更新sku报关信息、采购信息
            $this->updateSkuExtend($goodsSku->goods_id, $goodsSku->id, $params);

            //这里推送给马帮
//            PushGoodsToMabangJob::dispatch($type, $goodsSku, getAdminId());

            $model = $goodsSku->goods->fresh();
            $this->updateMaxMinPrice($model);

            return true;
        });
    }

    /**
     * @desc 更新sku报关信息、采购信息
     */
    public function updateSkuExtend($goodsId, $skuId, $params):bool
    {
        //报关信息
        if (!empty($params['logistics'])) {
            $params['logistics']['goods_sku_id'] = $skuId;
            $logisticsData = LogisticsCustomsDeclarationModel::init($goodsId, $params['logistics']);
            $res = LogisticsCustomsDeclarationModel::query()->where('goods_sku_id', $skuId)->first();
            if ($res) {
                $res->update($logisticsData);
            } else {
                LogisticsCustomsDeclarationModel::query()->create($logisticsData);
            }
        }

        // 供应商采购信息
        if (!empty($params['goods_suppliers'])) {
            foreach ($params['goods_suppliers'] as $suppliers) {
                $suppliers['goods_sku_id'] = $skuId;
                $suppliersData = GoodsSupplier::init($suppliers);

                $res = GoodsSupplier::query()->where([
                    'goods_sku_id' => $suppliersData['goods_sku_id'],
                    'supplier_id' => $suppliersData['supplier_id'],
                    'purchase_spec_id' => $suppliersData['purchase_spec_id'],
                ])->first();

                if ($res) {
                    $res->update($suppliersData);
                } else {
                    GoodsSupplier::query()->create($suppliersData);
                }
            }
        }

        return true;
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
        return $this->model->whereIn('id', $params['ids'])->update($data);
    }

    /**
     * @param $params
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update1688Status($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int'
        ])->validate();
        $data = ['status_1688' => $params['status']];
        return $this->model->whereIn('id', $params['ids'])->update($data);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function updateHot($params)
    {
        $res = ['status' => true, 'msg' => 'success'];

        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int',
        ])->validate();

        $data = ['is_hot' => $params['status']];

        // Check if any goods have audit statuses waiting or rejected
        $auditStatuses = [GoodsAudit::GOOD_AUDIT_WAITING, GoodsAudit::GOOD_AUDIT_REJECTED];
        $auditCount = GoodsAudit::whereIn('goods_id', $params['ids'])
            ->whereIn('audit_status', $auditStatuses)
            ->count();

        if ($auditCount > 0) {
            return ['status' => false, 'msg' => '部分商品未审核通过'];
        }
        $updateStatus = $this->model->whereIn('id', $params['ids'])->update($data);
        if (!$updateStatus) {
            return ['status' => false, 'msg' => '更新失败'];
        }
        return $res;
    }

    /**
     * @param $params
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update1688($params)
    {
        $res = ['status' => true, 'msg' => 'success'];

        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int',
        ])->validate();

        $data = ['origin_type' => $params['status']];

        // Check if any goods have audit statuses waiting or rejected
        $auditStatuses = [GoodsAudit::GOOD_AUDIT_WAITING, GoodsAudit::GOOD_AUDIT_REJECTED];
        $auditCount = GoodsAudit::whereIn('goods_id', $params['ids'])
            ->whereIn('audit_status', $auditStatuses)
            ->count();

        if ($auditCount > 0) {
            return ['status' => false, 'msg' => '部分商品未审核通过'];
        }
        $updateStatus = $this->model->whereIn('id', $params['ids'])->update($data);
        if (!$updateStatus) {
            return ['status' => false, 'msg' => '更新失败'];
        }
        return $res;
    }

    /**
     * @param $params
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function markAsSelfOperated($params)
    {
        $res = ['status' => true, 'msg' => 'success'];

        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int',
        ])->validate();

        $data = ['self_goods' => $params['status']];

        // Check if any goods have audit statuses waiting or rejected
        $auditStatuses = [GoodsAudit::GOOD_AUDIT_WAITING, GoodsAudit::GOOD_AUDIT_REJECTED];
        $auditCount = GoodsAudit::whereIn('goods_id', $params['ids'])
            ->whereIn('audit_status', $auditStatuses)
            ->count();

        if ($auditCount > 0) {
            return ['status' => false, 'msg' => '部分商品未审核通过'];
        }
        $updateStatus = $this->model->whereIn('id', $params['ids'])->update($data);
        if (!$updateStatus) {
            return ['status' => false, 'msg' => '更新失败'];
        }
        return $res;
    }


    /**
     * 商品审核
     * @param $params
     * @return mixed
     */
    public function auditGoods($params)
    {
        $res = ['status' => true, 'msg' => 'success'];

        validator($params, [
            'ids'          => 'required|array',
            'audit_status' => 'required|int',
        ])->validate();
        $userId = auth('admin')->id();
        $data = [
            'audit_status'    => $params['audit_status'],
            'audit_user_id'   => auth('admin')->id(),
            'audit_user_name' => auth('admin')->user()->username,
            'audit_remark'    => $params['audit_remark'],
            'audit_time'      => Carbon::now()->toDateTimeString(),
        ];

        $total = count($params['ids']);
        $successCount = 0;
        $failCount = 0;
        $successIds = [];
        foreach ($params['ids'] as $goodsId) {
            $auditRecord = GoodsAudit::query()->with(['goods.skus'])->where('goods_id',$goodsId)->first();
            $goodsSpu = $auditRecord->goods->spu ?? $goodsId;

            if (!$auditRecord) {
                $failCount++;
                logger($goodsSpu . ': 商品还未提交审核，请先提交审核');
                continue;
            }
            if ($auditRecord->audit_status === GoodsAudit::GOOD_AUDIT_APPROVED) {
                $failCount++;
                logger($goodsSpu . ': 商品已审核通过，不要重复审核');
                continue;
            }
            if ($auditRecord->commit_status === GoodsAudit::GOOD_COMMIT_WAITING) {
                $failCount++;
                logger($goodsSpu . ': 待审核商品还未提交，请先提交审核');
                continue;
            }
            if($auditRecord->commit_user_id === $userId){
                $failCount++;
                logger($goodsSpu . ': 您已提交商品审核申请，不可同时审核商品');
                continue;
            }

            $error = [];
            //校验sku的规格尺寸
            $auditRecord->goods->skus->each(function ($sku) use ($goodsSpu, &$error) {
                if (empty($sku->weight)) {
                    $error[] = 'SPU：' . $goodsSpu . '，该产品缺少重量信息，请补充后再审核。';
                }
            });

            if (!empty($error)) {
                $failCount++;
                logger(implode(',', $error));
                continue;
            }

            $successIds[] = $goodsId;
            $successCount++;
        }

        // 更新成功商品的审核状态
        if (!empty($successIds)) {
            if ($data['audit_status'] === GoodsAudit::GOOD_AUDIT_REJECTED) {
                $data['commit_status'] = GoodsAudit::GOOD_COMMIT_WAITING;
            }
            GoodsAudit::query()->whereIn('goods_id', $successIds)->update($data);
        }

        $res['msg'] = "成功审核 {$successCount} 个商品，失败 {$failCount} 个商品，共 {$total} 个商品";
        return $res;
    }


    /**
     * 商品提交审核
     * @param $params
     * @return mixed
     */
    public function commitAuditGoods($params)
    {
        $res = ['status' => true, 'msg' => 'success'];

        validator($params, [
            'ids'          => 'required|array',
            'commit_status' => 'required|int',
        ])->validate();

        $data = [
            'commit_status'    => $params['commit_status'],
            'commit_user_id'   => auth('admin')->id(),
            'commit_user_name' => auth('admin')->user()->username,
            'commit_time'      => Carbon::now()->toDateTimeString(),
            'audit_status'     => GoodsAudit::GOOD_AUDIT_WAITING,
        ];

        foreach ($params['ids'] as $goodsId) {
            $auditRecord = GoodsAudit::where('goods_id',$goodsId)->first();
            //没有记录，默认新增一条记录
            if (!$auditRecord) {
                $initAudit=GoodsAudit::init($goodsId);
                GoodsAudit::query()->insert($initAudit);
                continue;
            }
            if ($auditRecord->audit_status === GoodsAudit::GOOD_AUDIT_APPROVED) {
                $res['status'] = false;
                $res['msg'] = "商品 {$goodsId} 已审核通过，不要重复提交审查";
                return $res;
            }
            if ($auditRecord->audit_status === GoodsAudit::GOOD_COMMIT_APPROVED) {
                $res['status'] = false;
                $res['msg'] = "商品 {$goodsId} 已提交审核，不用重复操作 ";
                return $res;
            }
        }
        GoodsAudit::query()->whereIn('goods_id', $params['ids'])->update($data);
        return $res;
    }
    public function deletes($params)
    {
        if (empty($params['ids'])) throw new AccidentException('请选择需要删除的产品', Code::OPERATE_FAIL);
        $list = $this->model::with('skus')->withSum('stocks', 'total_quantity')->whereIn('id', $params['ids'])->get();
        foreach ($list as $goods) {
            if ($goods->stocks_sum_total_quantity > 0) throw new AccidentException('产品 ' . $goods->goods_name . ' 有库存不能删除', Code::OPERATE_FAIL);

            foreach ($goods->skus as $kk => $skus) {
                //这里推送给马帮
//                PushGoodsToMabangJob::dispatch(PushGoodsToMabangJob::TYPE_14, $skus, getAdminId());
            }

            $goods->skus()->delete();
            $goods->delete();
        }
        return true;
    }

    public function batchUpdateDeclaration(): bool
    {
        validator($this->formData, [
            'goods_ids' => 'required|array',
            'cn_name' => 'required',
            'en_name' => 'required',
            'unit_price' => 'required',
            'weight' => 'required',
        ], [], [
                      'order_ids' => '产品ID',
                      'cn_name' => '中文名称',
                      'en_name' => '英文名称',
                      'unit_price' => '申报单价',
                      'weight' => '申报重量',
                  ])->validate();

        //根据产品ID查询skuID
        $goodsSkuIds = GoodsSku::query()->whereIn('goods_id', $this->formData['goods_ids'])->pluck('goods_id','id');

        if ($goodsSkuIds->isEmpty()) {
            return false;
        }

        $goodsSkuIds->each(function ($item, $key) {
            $this->updateSkuExtend($item, $key, ['logistics' => $this->formData]);
        });

        //上面更新后再推送马帮
        $goodsSkuIds->each(function ($goodsId, $goodsSkuId) {

            $userId = getAdminId();
            $goodsSku = GoodsSku::find($goodsSkuId);

            if($goodsSku){
//                PushGoodsToMabangJob::dispatch(PushGoodsToMabangJob::TYPE_30, $goodsSku, $userId);
            }

        });

        return true;
    }

    /**
     * 导入商品
     * @return mixed
     * @throws \Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/13 19:01
     */
    public function import()
    {
        $file = request()->file('file');

        if (empty($file)) {
            throw new AccidentException('导入文件不能为空', Code::OPERATE_FAIL);
        }

        $extension = $file->getClientOriginalExtension();

        if (!in_array($extension, ['xls', 'xlsx', 'csv'])) {
            throw new AccidentException('文件格式错误，请上传excel文件', Code::OPERATE_FAIL);
        }

        return DB::transaction(function () use ($file) {
            $import = new GoodsImportMain();
            Excel::import($import, $file);
        });
    }

    public function pushToMabang($id)
    {
        $goods = $this->model::find($id);
        if(!$goods){
            throw new AccidentException("产品信息不存在");
        }

        $goodsSkuData = $this->skuModel::where('goods_id', $id)
            ->where('mabang_stock_id', 0)
            ->get();

        if($goodsSkuData){

            $userId = getAdminId();

            foreach ($goodsSkuData as $key => $goodsSku) {

                PushGoodsToMabangJob::dispatch(PushGoodsToMabangJob::TYPE_11, $goodsSku, $userId);
            }
        }

        return true;

    }

    public function skuPushToMabang($id)
    {
        $goodsSku = GoodsSku::query()->findOrFail($id);
        PushGoodsToMabangJob::dispatch(PushGoodsToMabangJob::TYPE_11, $goodsSku, getAdminId());
    }

    //已废弃
    public function calculateQuotation()
    {
        validator($this->formData, [
            'purchase_costs' => 'required|numeric',
            'profit_rate' => 'nullable|numeric',
            'fixed_amount' => 'nullable|numeric',
        ], [], [
            'purchase_costs' => '采购成本',
        ])->validate();

        $purchaseCosts = $this->formData['purchase_costs'];
        $profitRate = $this->formData['profit_rate']??0;
        $fixedAmount = $this->formData['fixed_amount']??0;

        $productQuote = 0;

        //找出基础配置里的商品报价默认配置
        $keyList = [
            SystemConfig::PRODUCT_QUOTE_DEFAULT_PROFIT_RATE,
            SystemConfig::PRODUCT_QUOTE_DEFAULT_FIXED_AMOUNT,
            SystemConfig::PRODUCT_QUOTE_CALCULATE_METHOD,
        ];

        $systemConfig = SystemConfigService::getMultipleConfig($keyList);

        $currencyConverter = new CurrencyConverter();

        if($systemConfig['product_quote_calculate_method'] == CustomsQuoteConfig::PRODUCT_QUOTE_CALCULATE_METHOD_1){
            #按百分比计算：采购成本 / (1 - 利润率) / 汇率

            $productProfitRate = ($profitRate > 0) ? $profitRate : $systemConfig['product_quote_default_profit_rate'];

            $quote = $purchaseCosts / (1 - (float) $productProfitRate / 100);

            //报价转换成美元
            $productQuote = $currencyConverter->reversedCurrenciesExchange($quote);
        }

        if($systemConfig['product_quote_calculate_method'] == CustomsQuoteConfig::PRODUCT_QUOTE_CALCULATE_METHOD_2){
            #按固定金额计算：(物流成本 + 固定金额(人民币)) / 汇率

            $productFixedAmount = ($fixedAmount > 0) ? $fixedAmount : $systemConfig['product_quote_default_fixed_amount'];

            $quote = $purchaseCosts + (float) $productFixedAmount;

            //报价转换成美元
            $productQuote = $currencyConverter->reversedCurrenciesExchange($quote);
        }

        return number_format($productQuote, 2, '.', '');

    }

    public function rules()
    {
        return [
            'goods_name' => 'required|string',
            'goods_type' => 'required|int',//商品类型 1产品 2包材
            'category_id' => 'required_if:goods_type,1|nullable|int',//商品类型为产品时必填
            'packing_materials_type' => 'required_if:goods_type,2|nullable|int',//商品类型为包材时必填
            'cover_image' => 'required|string',
            'main_images' => 'required|array',
            'main_video' => 'sometimes|nullable|array',
            'detail' => 'required|string',
            'options' => 'sometimes|nullable|array',
            'brand' => 'sometimes|nullable|string',
            'unit' => 'sometimes|nullable|string',
            'purchase_price' => 'sometimes|nullable',
            'purchase_url' => 'sometimes|nullable|string',
            'is_group' => 'sometimes|nullable|int',

            'props' => 'sometimes|nullable|array',
            'props.sku_long' => 'sometimes|nullable',
            'props.sku_width' => 'sometimes|nullable',
            'props.sku_height' => 'sometimes|nullable',
            'props.sku_net_weight' => 'sometimes|nullable',
            'props.sku_gross_weight' => 'sometimes|nullable',
            'props.package_long' => 'sometimes|nullable',
            'props.package_width' => 'sometimes|nullable',
            'props.package_height' => 'sometimes|nullable',
            'props.box_long' => 'sometimes|nullable',
            'props.box_wight' => 'sometimes|nullable',
            'props.box_height' => 'sometimes|nullable',
            'props.box_net_weight' => 'sometimes|nullable',
            'props.box_gross_weight' => 'sometimes|nullable',
            'props.box_num' => 'sometimes|nullable',
            'developer_id' => 'sometimes|nullable|int',//开发员id

//            'skus' => 'required|array',
            'skus.*.sku_id' => 'required_if:status,1|nullable',
            'skus.*.spec_name' => 'required_if:status,1|nullable|string',
            'skus.*.images' => 'required_if:status,1|nullable|array',
            'skus.*.status' => 'required|int',
            'skus.*.spec_info' => 'sometimes|nullable|array',
            'skus.*.quantity' => 'sometimes|nullable|int',
            'skus.*.sku_name' => 'sometimes|nullable|string',
            'skus.*.length' => 'sometimes|nullable|numeric|min:0',
            'skus.*.width' => 'sometimes|nullable|numeric|min:0',
            'skus.*.height' => 'sometimes|nullable|numeric|min:0',
            'skus.*.weight' => 'sometimes|nullable|numeric|min:0',
            'skus.*.original_price' => 'sometimes|nullable|numeric|min:0',
            'skus.*.quote_price' => 'required_if:status,1|nullable|numeric|min:0',
            'skus.*.sale_price' => 'sometimes|nullable|numeric|min:0',
            'skus.*.purchase_price' => 'sometimes|nullable|numeric|min:0',
//            'skus.*.profit'  => 'sometimes|nullable|numeric|min:0',
            'skus.*.profit_margin'  => 'sometimes|nullable|numeric|min:5',
            'skus.*.sku_remark' => 'sometimes|nullable|string',
            'skus.*.purchase_days' => 'sometimes|int',
            'skus.*.min_purchase_quantity' => 'sometimes|int',
            'skus.*.purchase_buyer_id' => 'sometimes|int',
            'skus.*.purchase_remark' => 'sometimes|nullable|string|max:500',

            'skus.*.logistics' => 'sometimes|nullable|array',
            'skus.*.logistics.cn_name' => 'sometimes|nullable|string|max:50',
            'skus.*.logistics.en_name' => 'sometimes|nullable|string|max:250',
            'skus.*.logistics.unit_price' => 'sometimes|nullable|numeric|min:0',
            'skus.*.logistics.weight' => 'sometimes|nullable|numeric|min:0',
            'skus.*.logistics.code' => 'sometimes|nullable|string|max:20',
            'skus.*.logistics.attributes' => 'sometimes|nullable|array',
            'skus.*.logistics.address' => 'sometimes|nullable|string',
            'skus.*.logistics.material' => 'sometimes|nullable|string',
            'skus.*.logistics.use_to' => 'sometimes|nullable|string',

            'skus.*.goods_suppliers' => 'sometimes|nullable|array',
            'skus.*.goods_suppliers.*.supplier_id' => 'sometimes|nullable|int',
            'skus.*.goods_suppliers.*.price' => 'sometimes|nullable|numeric|min:0',
            'skus.*.goods_suppliers.*.purchase_type' => 'sometimes|nullable|int',
            'skus.*.goods_suppliers.*.purchase_url' => 'sometimes|nullable|string',
            'skus.*.goods_suppliers.*.purchase_goods_name' => 'sometimes|nullable|string',
            'skus.*.goods_suppliers.*.purchase_spec_image' => 'sometimes|nullable|string',
            'skus.*.goods_suppliers.*.purchase_spec_name' => 'sometimes|nullable|string',
            'skus.*.goods_suppliers.*.purchase_goods_id' => 'sometimes|nullable',
            'skus.*.goods_suppliers.*.purchase_sku_id' => 'sometimes|nullable',
            'skus.*.goods_suppliers.*.is_default' => 'sometimes|int',
            'skus.*.goods_suppliers.*.purchase_spec_id' => 'sometimes|nullable|string',
        ];
    }

    public function skuRules()
    {
        return [
            'sku_id' => 'required',
            'spec_name' => 'required|string',
            'images' => 'sometimes|nullable|array',
            'status' => 'required|int',
            'spec_info' => 'sometimes|nullable|array',
            'quantity' => 'sometimes|nullable|int',
            'sale_price' => 'required|numeric',
            'purchase_price' => 'sometimes|numeric',
            'original_price' => 'sometimes|nullable|numeric',
            'profit' => 'sometimes|numeric',
            'quote_price' => 'sometimes|nullable|numeric',
            'sku_name' => 'sometimes|nullable|string',
            'length' => 'sometimes|numeric',
            'width' => 'sometimes|numeric',
            'height' => 'sometimes|numeric',
            'weight' => 'required|numeric',
            'sku_remark' => 'sometimes|nullable|string',
            'purchase_days' => 'sometimes|int',
            'min_purchase_quantity' => 'sometimes|int',
            'purchase_buyer_id' => 'sometimes|int',
            'purchase_remark' => 'sometimes|nullable|string|max:500',
            'logistics' => 'sometimes|nullable|array',
            'logistics.cn_name' => 'sometimes|nullable|string|max:50',
            'logistics.en_name' => 'sometimes|nullable|string|max:250',
            'logistics.unit_price' => 'sometimes|nullable|numeric',
            'logistics.weight' => 'sometimes|nullable|numeric',
            'logistics.code' => 'sometimes|nullable|string|max:20',
            'logistics.attributes' => 'sometimes|nullable|array',
            'logistics.address' => 'sometimes|nullable|string',
            'logistics.material' => 'sometimes|nullable|string',
            'logistics.use_to' => 'sometimes|nullable|string',
            'goods_suppliers' => 'sometimes|nullable|array',
            'goods_suppliers.*.supplier_id' => 'sometimes|nullable|int',
            'goods_suppliers.*.price' => 'sometimes|nullable|numeric',
            'goods_suppliers.*.purchase_type' => 'sometimes|nullable|int',
            'goods_suppliers.*.purchase_url' => 'sometimes|nullable|string',
            'goods_suppliers.*.purchase_goods_name' => 'sometimes|nullable|string',
            'goods_suppliers.*.purchase_spec_image' => 'sometimes|nullable|string',
            'goods_suppliers.*.purchase_spec_name' => 'sometimes|nullable|string',
            'goods_suppliers.*.purchase_goods_id' => 'sometimes|nullable',
            'goods_suppliers.*.purchase_sku_id' => 'sometimes|nullable',
            'goods_suppliers.*.is_default' => 'sometimes|int',
            'goods_suppliers.*.purchase_spec_id' => 'sometimes|nullable|string',
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
