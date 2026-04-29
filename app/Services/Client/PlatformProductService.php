<?php

namespace App\Services\Client;


use App\Jobs\SyncPlatformProductJob;
use App\Models\PlatformProduct;
use App\Models\ShopModel;
use App\Services\PlatformShop\PlatformShopService;
use App\Services\Shopify\ProductService;

class PlatformProductService extends BaseService
{
    public $filterRules = [
        'shop_id'           => ['=', 'shop_id'],
        'product_name'      => ['like', 'product_name'],
        'product_id'        => ['=', 'product_id'],
        'status'            => ['=', 'status'],
        'published_at'      => ['between', ['begin_date', 'end_date']]
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
        $this->query->where('custom_id', getCustomId());
        $this->query->with(['skus', 'shop']);
        if (isset($this->formData['status']) && $this->formData['status'] !== '') {
            $this->query->where('status', $this->formData['status']);
        }
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


//    public function show($id)
//    {
//        return $this->model::query()->with('skus')->findOrFail($id);
//    }

    /**
     * 同步平台店铺产品
     * @return true
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/18 17:32
     */
    public function syncPlatformProductAll()
    {
        $shopList = ShopModel::query()->where(['status' => ShopModel::STATUS_AUTH, 'enable' => ShopModel::ENABLE, 'customer_id' => getCustomId()])->get();

        if (empty($shopList)) {
            return true;
        }

        //异步执行同步平台店铺产品
        $shopList->each(function ($shop) {
            dispatch(new SyncPlatformProductJob($shop));
        });
        return true;
    }

    /**
     * @param $shopId
     * @return bool
     * @throws \Exception
     */
    public function syncShopProduct($shopId)
    {
        $shop =  ShopModel::query()->where(['status' => ShopModel::STATUS_AUTH, 'enable' => ShopModel::ENABLE])->findOrFail($shopId);

        if(empty($shop)) {
            return true;
        }

        $platformService = new PlatformShopService($shop);
        $platformService->syncProductList();
        return true;
    }

    /**
     * @param $productId
     * @return bool
     * @throws \Exception
     */
    public function syncOneProduct($productId)
    {
        $platformProduct = PlatformProduct::query()->with('shop')->findOrFail($productId);
        $platformShopService = new PlatformShopService($platformProduct->shop);
        $platformShopService->syncProductDetail($platformProduct->product_id,);
        return true;
    }

    /**
     * @param $productId
     * @return bool
     * @throws \Exception
     */
    public function deleteProduct($productId)
    {
        $platformProduct = PlatformProduct::query()->findOrFail($productId);
        $productService = new ProductService();
        $productService->deleteProduct($platformProduct);
        return true;
    }

}
