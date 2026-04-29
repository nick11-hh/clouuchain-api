<?php

namespace App\Services\Client;


use App\Helper\CurrencyConverter;
use App\Jobs\PublishGoodsJob;
use App\Lib\Code;
use App\Models\ClientGoods;
use App\Models\ClientGoodsSku;
use App\Models\GoodsSku;
use App\Models\ShopModel;
use App\Services\PlatformShop\PlatformShopService;
use App\Services\Shopify\ProductService;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class ClientGoodsService extends BaseService
{
    public $filterRules = [
        'goods_name'     => ['like', 'goods_name'],
        'spu'            => ['=', 'spu'],
        'category_id'    => ['=', 'category_id'],
        'created_at'     => ['between', ['begin_date', 'end_date']],
        'goods_type'     => ['=', 'goods_type'],
    ];

    private ClientGoodsSku $skuModel;

    public function __construct()
    {
        $this->model = new ClientGoods();
        $this->skuModel = new ClientGoodsSku();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->where('custom_id', getCustomId());
        $this->query->with(['skus', 'category', 'publishLog']);
        if (isset($this->formData['status']) && $this->formData['status'] !== '') {
            if ($this->formData['status'] == $this->model::STATUS_DEFAULT) {
                $this->query->whereIn('status', [$this->model::STATUS_DEFAULT, $this->model::STATUS_PUBLISHING]);
            } else {
                $this->query->where('status', $this->formData['status']);
            }
        }

        $this->query->latest();
        $res =  parent::index();
//        $currencyConverter = new CurrencyConverter();
//        $res->each(function ($item) use($currencyConverter){
//            $item->goods_lowest_price = $currencyConverter->reversedCurrenciesExchange($item->goods_lowest_price);
//        });

        return $res;
    }


    public function show($id)
    {
        $res = $this->model::query()->with(['skus', 'category'])->findOrFail($id);

//        $currencyConverter = new CurrencyConverter();
//        $res->skus->each(function ($item) use($currencyConverter) {
//            $item->original_price = $currencyConverter->reversedCurrenciesExchange($item->original_price);
//            $item->sale_price = $currencyConverter->reversedCurrenciesExchange($item->sale_price);
//        });

        return $res;
    }

    /**
     * @param $params
     * @return mixed
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($params) {
            $goodsData = ClientGoods::init($params);
            $goods = $this->model::query()->create($goodsData);
            $minPrice = 0;
            foreach ($params['skus'] as $sku) {
                if ($minPrice == 0 || $minPrice > $sku['sale_price']) $minPrice = $sku['sale_price'];
                $sku['goods_id'] = $goods->id;
                $skuData = ClientGoodsSku::init($sku);
                $this->skuModel::query()->create($skuData);
            }
            $goods->goods_lowest_price = $minPrice;
            $goods->save();
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
            $goodsData = [
                'goods_name' => $params['goods_name'],
                'category_id' => $params['category_id'] ?? 0,
                'category_name' => $params['category_name'] ?? '',
                'brand' => $params['brand'] ?? '',
                'unit' => $params['unit'] ?? '',
                'cover_image' => $params['cover_image'],
                'main_images' => $params['main_images'] ?? [],
                'options' => $params['options'] ?? [],
                'props' => $params['props'] ?? [],
                'detail' => $params['detail'] ?? '',
            ];
            $goods->update($goodsData);
            $minPrice = 0;
            $skuIds = [];
            foreach ($params['skus'] as $sku) {
                if ($minPrice == 0 || $minPrice > $sku['sale_price']) $minPrice = $sku['sale_price'];
                $skuData = [
                    'sku_id' => $sku['sku_id'],
                    'spec_name' => $sku['spec_name'] ?? '',
                    'spec_info' => $sku['spec_info'] ?? [],
                    'sale_price' => $sku['sale_price'],
                    'original_price' => $sku['original_price'],
                    'images' => $sku['images'] ?? [],
                    'quantity' => $sku['quantity'] ?? 0,
                    'status' => $sku['status'] ?? 1
                ];
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
            $goods->goods_lowest_price = $minPrice;
            $goods->save();
            // 同步刊登
            if (!empty($params['is_publish'])) {
                if (empty($params['shop_id'])) throw new AccidentException('请选择刊登店铺', Code::OPERATE_FAIL);
                $shop = ShopModel::query()->findOrFail($params['shop_id']);
                $platformService = new PlatformShopService($shop);
                $goods = $goods->fresh('skus');
                $platformService->productPublish($goods);
                $goods->status = ClientGoods::STATUS_PUBLISHED;
                $goods->save();
            }
            return $goods;
        });
    }


    public function deletes($params)
    {
        $ids = $params['ids'];
        if (empty($ids)) throw new AccidentException('请选择需要删除的产品', Code::OPERATE_FAIL);
        $list = $this->model::with('skus')->whereIn('id', $ids)->get();
        foreach ($list as $goods) {
            $goods->skus()->delete();
            $goods->delete();
        }
        return true;
    }

    /**
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public function publish($params)
    {
        validator($params, [
            'shop_id' => 'required|int',
            'goods_id' => 'required|int',
        ])->validate();

        $goods = $this->model::query()->with('skus')->findOrFail($params['goods_id']);
        $goods->status = $this->model::STATUS_PUBLISHING;
        $goods->save();

        dispatch(new PublishGoodsJob([$params['shop_id']], [$params['goods_id']]))->onQueue('product_publish');
        return true;
    }

    /**
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public function publishBatch($params)
    {
        validator($params, [
            'shop_ids' => 'required|array',
            'goods_ids' => 'required|array',
        ])->validate();

        $goodsList = $this->model::query()->with('skus')->whereIn('id', $params['goods_ids'])->get();
        $goodsList->each(function ($goods) {
            $goods->status = $this->model::STATUS_PUBLISHING;
            $goods->save();
        });

        dispatch(new PublishGoodsJob($params['shop_ids'], $params['goods_ids']))->onQueue('product_publish');
        return true;
    }

    public function rules()
    {
        return [
            'goods_name' => 'required|string',
//            'category_id' => 'required|int',
//            'category_name' => 'required|string',
            'cover_image' => 'required|string',
            'main_images' => 'required|array',
            'detail' => 'required|string',
            'options' => 'sometimes|nullable|array',
            'brand' => 'sometimes|nullable|string',
            'unit' => 'sometimes|nullable|string',
            'source_url' => 'sometimes|nullable|string',
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
            'skus' => 'required|array',
            'skus.*.sku_id' => 'required|string',
            'skus.*.spec_name' => 'required|string',
            'skus.*.sale_price' => 'required|string',
            'skus.*.original_price' => 'required|string',
            'skus.*.images' => 'required|array',
            'skus.*.status' => 'required|int',
            'skus.*.spec_info' => 'sometimes|nullable|array',
            'skus.*.quantity' => 'sometimes|nullable|int',
        ];
    }

}
