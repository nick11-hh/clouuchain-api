<?php

namespace App\Services\Client;

use App\Jobs\AutoPullOrderJob;
use App\Jobs\CreateShopifyWebhooks;
use App\Jobs\DeleteShopDataJob;
use App\Lib\Code;
use App\Lib\Platform;
use App\Models\Order;
use App\Models\ShopModel;
use App\Models\ShopOrderLogs;
use App\Models\ShopPlatformConfig;
use App\Services\PlatformShop\Platform\Woocommerce\WoocommerceService;
use App\Services\Shopify\AuthorizeShop;
use App\Services\Shopify\RequestService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Exceptions\AccidentException;
use Illuminate\Validation\ValidationException;

class ShopService extends BaseService
{
    protected $filterRules = [
        'shop_name' => ['like', 'keyword'],
        'status'    => ['=', 'status'],
        'enable'    => ['=', 'enable'],
        'platform'  => ['=', 'platform']
    ];

    public function __construct(ShopModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $custom_id = request()->get('custom_id') ?? auth()->user()->custom_id;
        $this->query->with('customer:id,custom_name')->where('customer_id', $custom_id);

        if (isset($this->formData['platform_neq']) && $this->formData['platform_neq']) {
            $this->query->whereNot('platform', $this->formData['platform_neq']);
        }

        return parent::index();
    }

    public function show(int $id)
    {
        return $this->model::query()->findOrFail($id);
    }

    /**
     * @throws AccidentException
     * @throws ValidationException
     */
    public function store()
    {
        // 客户id
        $custom_id = getCustomId();
        validator($this->formData, [
            'shop_name' => 'required',
            'platform'  => 'required',
            // 'shop_url'  => 'required'
        ], [], [
            'shop_name' => '店铺名称',
            'platform'  => '店铺类型',
            // 'shop_url'  => '店铺地址'
        ])->validate();
        $platform = $this->formData['platform'];

        $where = [
            'platform' => $platform,
            // 'customer_id' => $custom_id
        ];

        $data = [
            'shop_name' => $this->formData['shop_name'],
            'platform'  => $platform,
            'customer_id' => $custom_id,
            'platform_shop_id' => 0,
            'shop_url' => trim($this->formData['shop_url'] ?? '', '/'),
            'access_token' => $this->formData['secret'] ?? '',
            'access_key' => $this->formData['key'] ?? '',
            'authorization_type' => (int)($this->formData['authorization_type'] ?? 1),
        ];

        $exist = ShopModel::query()->where('shop_name', $this->formData['shop_name'])->where('status', '!=', ShopModel::STATUS_AUTH_CANCEL)->first();
        if (!empty($exist)) {
            throw new AccidentException('店铺名称已存在');
        }
        //针对shopify平台删除店铺，如果重复授权则判断是否需要新添加
        if ($platform === Platform::SHOPIFY) {
            $where['shop_url'] = $data['shop_url'] = $this->getShopifyStoreUrl($data['shop_url']);

            if ($data['authorization_type'] === ShopModel::AUTHORIZATION_TYPE_PRIVATE_APP) {
                $data['status'] = ShopModel::STATUS_AUTH;
                $data['authorize_at'] = now();
            }

            // ✅ 核心修复：一个 Shopify 店铺只能成功授权一次
            // 检查该店铺网址是否已被任何客户授权成功（状态为 AUTH）
            $authorizedShop = $this->model::query()
                ->where('shop_url', $data['shop_url'])
                ->where('status', ShopModel::STATUS_AUTH)
                ->first();

            if (!empty($authorizedShop)) {
                // 店铺已被其他账户授权成功
                throw new AccidentException(__('店铺已被授权给其它帐户'), Code::OPERATE_FAIL);
            }

            // 检查当前客户是否已经创建过该店铺的授权记录（不论状态）
            $existingShop = $this->model::query()
                ->where('shop_url', $data['shop_url'])
                ->where('customer_id', $custom_id)
                ->first();

            if (!empty($existingShop)) {
                // 已存在该店铺的授权记录
                throw new AccidentException(__('不能重复授权店铺'), Code::OPERATE_FAIL);
            }

            // 无冲突，允许创建新店铺
            return $this->model::query()->create($data);

        } else {
//            $where['shop_name'] = $this->formData['shop_name'];
            $where['shop_url'] = $data['shop_url'];
            if ($platform === Platform::LOCAL) {
                $where['shop_name'] = $data['shop_name'];
            }

            $shop = $this->model::query()->where($where)->first();
            if($shop){

                if($shop->customer_id == $custom_id){

                    //未被删除
                    throw new AccidentException(__('不能重复授权店铺'), Code::OPERATE_FAIL);
                }else{

                    //未被删除
                    throw new AccidentException(__('店铺已被授权给其它帐户'), Code::OPERATE_FAIL);
                }
            }

            if ($data['platform'] === Platform::WOOCOMMERCE) {
                $data['status'] = ShopModel::STATUS_AUTH;
                $data['authorize_at'] = now();

                $authStatus = (new WoocommerceService(new ShopModel()))->authorize($data);
                if ($authStatus != 200) {
                    if ($authStatus == 404) {
                        throw new AccidentException('店铺地址错误');
                    } else {
                        throw new AccidentException('店铺授权信息错误');
                    }
                }

            }

            return $this->model::query()->create($data);
        }
    }

    /**
     * @param $id
     * @return mixed
     */
    public function update($id)
    {
        validator($this->formData, [
            'shop_name' => 'required',
        ], [], [
            'shop_name' => 'Shop name',
        ])->validate();

        // $verifyShopName = $this->model::query()->where('shop_name', $this->formData['shop_name'])->whereNot('id', $id)->exists();
        //
        // throw_if(
        //     $verifyShopName,
        //     new AccidentException('Operation failed. The store name already exists', Code::OPERATE_FAIL)
        // );

        $data = [
            'shop_name' => $this->formData['shop_name'],
            'tax' => $this->formData['tax'] ?? ''
        ];

        $platform = $this->formData['platform'] ?? '';
        $authorizationType = (int)($this->formData['authorization_type'] ?? 1);
        if ($platform === Platform::SHOPIFY && $authorizationType === ShopModel::AUTHORIZATION_TYPE_PRIVATE_APP) {
            $shopUrl = $this->formData['shop_url'] ?? '';
            $secret = $this->formData['secret'] ?? '';
            if ($shopUrl) {
                $data['shop_url'] = $this->getShopifyStoreUrl($shopUrl);
            }

            if ($secret) {
                $data['access_key'] = $this->formData['key'] ?? '';
                $data['access_token'] = $secret;
            }

            if ($shopUrl && $secret) {
                $data['authorization_type'] = ShopModel::AUTHORIZATION_TYPE_PRIVATE_APP;
                $data['status'] = 1;
                $data['authorize_at'] = now();
            }
        }

        if ($platform === Platform::WOOCOMMERCE) {
            $data = [
                'shop_url' => $this->formData['shop_url'],
                'access_key' => $this->formData['key'] ?? '',
                'access_token' => $this->formData['secret'],
                'authorization_type' => ShopModel::AUTHORIZATION_TYPE_PRIVATE_APP,
                'status' => 1,
                'authorize_at' => now()
            ];
            $authStatus = (new WoocommerceService(new ShopModel()))->authorize($data);
            if ($authStatus != 200) {
                if ($authStatus == 404) {
                    throw new AccidentException('店铺地址错误');
                } else {
                    throw new AccidentException('店铺授权信息错误');
                }
            }
        }

        return $this->model::query()->where('id', $id)->update($data);
    }

    /**
     * 启用禁用
     * @return void
     */
    public function enable()
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'enable' => [
                'required',
                Rule::in([0, 1])
            ]
        ], [], [
            'ids' => '店铺id',
            'enable' => '启用禁用状态'
        ])->validate();

        return $this->model::whereIn('id', $this->formData['ids'])->update(['enable' => $this->formData['enable']]);
    }

    /**
     * 删除店铺
     * @return void
     */
    public function destroy()
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '店铺id',
        ])->validate();

        return DB::transaction(function () {
            foreach ($this->formData['ids'] as $id) {
                $shop = $this->model::query()->findOrFail($id);

                //删除店铺关联的数据
                dispatch(new DeleteShopDataJob($shop));

                $shop->delete();
            }

            return true;
        });
    }

    public function deleteShopOrders($shop, $revokeAuthorization = false)
    {
        return DB::transaction(function () use ($shop, $revokeAuthorization) {
            //删除关联的店铺订单
            $orderStatus = [
                Order::STATUS_QUOTE_NO, //未报价
                Order::STATUS_QUOTE_ASK, //报价中
                Order::STATUS_QUOTED, //已报价
                Order::STATUS_CANCELLED, //已取消
                Order::STATUS_NOT_SHIPPING, //不发货
            ];

            $orders = Order::query()->where('shop_id', $shop->id)
                ->whereIn('order_status', $orderStatus)
                ->get();
            if($orders->isNotEmpty()){
                $orderIdArr = $orders->modelKeys();

                $logsData = [];

                $content = $revokeAuthorization ? '客户取消店铺授权后，删除订单' : '客户操作删除店铺，同时删除订单';

                $orderIdChunkResult = array_chunk($orderIdArr, 2000);

                foreach ($orderIdChunkResult as $k => $d) {
                    foreach ($d as $key => $orderId) {

                        $logs['order_id'] = $orderId;
                        $logs['operator_type'] = ShopOrderLogs::OPERATOR_TYPE_DELETE_ORDER;
                        $logs['content'] = $content;
                        $logs['order_id'] = $orderId;
                        $logs['created_at'] = now();
                        $logsData[] = $logs;
                    }
                }

                $orders->each(function ($order) {
                    //删除商品
                    $order->lineItems()->delete();
                    //删除收件人
                    $order->shippingAddress()->delete();
                    //删除订单
                    $order->delete();
                });

                $chunkResult = array_chunk($logsData, 2000);
                foreach ($chunkResult as $k => $d) {
                    ShopOrderLogs::insert($d);
                }
            }

            if($revokeAuthorization){

                //修改店铺
                $shop->status = ShopModel::STATUS_AUTH_CANCEL;
                $shop->save();
            }

            return true;
        });
    }

    /**
     * 取消店铺授权
     *
     * @param  int  $id
     */
    public function revokeAuthorization($id)
    {
        $customerId = getCustomId();

        $shop = $this->model::query()->where('id', $id)
            ->where('customer_id', $customerId)->first();
        if (empty($shop)) throw new AccidentException(__('数据不存在'), Code::COMMAN_DATA_NOT_FOUND);

        $this->deleteShopOrders($shop, true);

        if ($shop->platform === Platform::LOCAL) {
            $shop->delete();
        }

        return true;
    }

    /**
     * 店铺授权绑定
     * @param AuthorizeShop $authShop
     * @return mixed
     * @throws \Exception
     */
    public function authenticate(AuthorizeShop $authShop)
    {
        $this->setShopifyKeySecret();
        validator($this->formData, [
            'shop' => 'required',
            'code' => 'required',
        ])->validate();

        try {
            $result = $authShop($this->formData['shop'], $this->formData['code']);

            if (empty($result->access_token)) return false;

            // ✅ 核心修复：检查该店铺是否已被其他客户授权成功
            $existingAuthorizedShop = $this->model::query()
                ->where('shop_url', $this->formData['shop'])
                ->where('status', ShopModel::STATUS_AUTH)
                ->first();

            if (!empty($existingAuthorizedShop) && $existingAuthorizedShop->customer_id != getCustomId()) {
                throw new AccidentException(__('店铺已被授权给其它帐户'), Code::OPERATE_FAIL);
            }

            //查询包含已删除的店铺信息
            $shopifyShop = $this->model::withTrashed()->where('customer_id', getCustomId())->where('shop_url', $this->formData['shop'])->first();
            if (empty($shopifyShop)) {
                $shopifyShop = new $this->model();
                $shopifyShop->customer_id = getCustomId();
                $shopifyShop->shop_name = str_replace('.myshopify.com', '', $this->formData['shop']);
                $shopifyShop->platform = 'shopify';
            } else {
                //如果有软删除记录，恢复此记录
                if ($shopifyShop->trashed()) {
                    $shopifyShop->restore();
                }
            }

            $shopifyShop->access_token = $result->access_token;
            $shopifyShop->status = ShopModel::STATUS_AUTH;
            $shopifyShop->shop_url = $this->formData['shop'];
            $shopifyShop->authorize_at = now();
            $shopifyShop->save();

//            $uData = [
//                'access_token' => $result->access_token,
//                'status' => 1,
//                'shop_url' => $this->formData['shop'],
//                'authorize_at' => now(),
////                'shop_name' => str_replace('.myshopify.com', '', $this->formData['shop']),
//            ];

//            $this->model::query()->where('shop_url', $this->formData['shop'])->update($uData);

            $applicationName = ShopPlatformConfig::getPlatformApplicationName('shopify');

            $shopifyService = new RequestService($shopifyShop);
            $shopifyService->fulfillmentServices($applicationName, false, true);
//            $shopifyService->carrierServices($applicationName);
            dispatch(new CreateShopifyWebhooks($shopifyShop));
            # 同步订单并优先执行
            dispatch(new AutoPullOrderJob($shopifyShop))->onQueue('sync_order_high');
            return $shopifyShop;
        }catch (\Exception $e) {
            logger('授权失败：', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'msg' => $e->getMessage()
            ]);
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    public function setShopifyKeySecret()
    {
        $shopifyConfig = ShopPlatformConfig::query()->where('platform', Platform::SHOPIFY)->first();
        if (!empty($shopifyConfig)) {
            Config::set('shopify-app.api_key', $shopifyConfig->app_key);
            Config::set('shopify-app.api_secret', $shopifyConfig->app_secret);
        }
    }

    public function getShopifyStoreUrl($name): string
    {
        if (empty($name)) return $name;

        //存在域名直接返回
        if (str_contains($name, '.myshopify.com')) {
            return $name;
        }

        return $name . '.myshopify.com';
    }

}
