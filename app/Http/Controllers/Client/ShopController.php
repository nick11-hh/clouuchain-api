<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\AccidentException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ShopInfo;
use App\Http\Resources\ShopList as ListResource;
use App\Lib\Code;
use App\Lib\Platform;
use App\Models\Landlord\Tenant;
use App\Models\Order;
use App\Models\ShopModel;
use App\Models\ShopPlatformConfig;
use App\Models\User;
use App\Services\ApiResponseService;
use App\Services\Client\ShopService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use App\Services\PlatformShop\Platform\Shopify\ShopifyService;
use App\Services\PlatformShop\Platform\Tiktok\RequestApi;
use App\Services\PlatformShop\PlatformShopService;
use App\Services\Shopify\AuthorizeShop;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    public $service;
    public function __construct(ShopService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        return ListResource::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        return ApiResponseService::success($this->service->store());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return ShopInfo
     */
    public function show($id)
    {
        return ShopInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     */
    public function update(Request $request, int $id)
    {
        return ApiResponseService::success($this->service->update($id));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     */
    public function destroy()
    {
        if($this->service->destroy()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 取消店铺授权
     *
     * @param  int  $id
     */
    public function revokeAuthorization($id)
    {
        if($this->service->revokeAuthorization($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function enable()
    {
        if($this->service->enable()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function authenticate(AuthorizeShop $authShop)
    {
        if($this->service->authenticate($authShop)) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function oauth(Request $request, AuthorizeShop $authShop): mixed
    {
        $request->validate([
            'shop' => 'required|string',
            'id'    => 'required',
            'source' => 'sometimes|nullable|string',
        ]);

        $payload = $request->all();

        $shop = ShopModel::query()
            ->where('id', $payload['id'])
            ->first();

        throw_if(
            !$shop,
            new AccidentException('请先在当前系统创建店铺', Code::OPERATE_FAIL)
        );

        $shopUrl = $payload['shop'];
        throw_if(
            ShopModel::query()->where('shop_url', $shopUrl)->where('customer_id', $shop->customer_id)->whereNot('id', $payload['id'])->exists(),
            new AccidentException('此店铺网址已被其他店铺占用', Code::OPERATE_FAIL)
        );

        //多个账号可以授权同一个店铺，此店铺只能在一个账号里授权成功，其他账号此店铺不能授权成功
        throw_if(
            ShopModel::query()->where('customer_id', '<>', $shop->customer_id)->where('shop_url', $shopUrl)->where('status', ShopModel::STATUS_AUTH)->exists(),
            new AccidentException(__('店铺已被授权给其它帐户'), Code::OPERATE_FAIL)
        );

        ShopModel::where('id', $payload['id'])->update([
            'shop_url' => $payload['shop'],
            'authorization_type' => ShopModel::AUTHORIZATION_TYPE_OAUTH,
        ]);

        // 获取凭证信息
        try {
            Config::set('shopify-app.api_redirect', config('app.url') . '/api/client/shopify/redirect/' . getCurrentUuid());
            (new ShopService(new ShopModel()))->setShopifyKeySecret();
            $result = $authShop($payload['shop'], null);
        } catch (\Exception $e) {
            logger('店铺授权失败：', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'msg' => $e->getMessage()
            ]);
            return ApiResponseService::error(Code::OPERATE_FAIL, '店铺授权失败');
        }
        info('店铺授权', [
            'payload' => $payload
        ]);
        if(!empty($payload['token'])) {
            ShopModel::query()->where('id', $payload['id'])->update(['status' => 1, 'access_token' => $payload['token'], 'authorize_at' => now()]);
            return ApiResponseService::success();
        }

        //区分授权来源
        $state = Str::random(32);
        $source = $payload['source'] ?? '';
        Cache::set($state, ['source' => $source], 300);

        $result->url .= "&state=$state";
        return ApiResponseService::success($result);
    }

    /**
     * 商店数据删除 Webhook 端点
     * @return array
     */
    public function delShop()
    {
        return ApiResponseService::success();
    }

    /**
     * 客户数据删除 Webhook 端点
     * @return array
     */
    public function clearCustomerData()
    {
        return ApiResponseService::success();
    }

    /**
     * 客户数据请求 Webhook 端点
     * @return array
     */
    public function customerData()
    {
        return ApiResponseService::success();
    }

    /**
     * @return array
     */
    public function destroyCustomer()
    {
        return ApiResponseService::success();
    }

    public function shopifyWebhook(Request $request)
    {
        $payload = $request->all();
        $event = $request->header('x-shopify-topic');

        info('shopifyWebhook', [
            'X-Shopify-Webhook-Id' => $request->header('X-Shopify-Webhook-Id'),
            'X-Shopify-Request-Id' => $request->header('X-Shopify-Request-Id'),
            'event' => $event,
            'payload' => $payload
        ]);

        try {

            switch ($event) {
                case 'orders/updated':
                case 'orders/create':
                    //根据头部请求的返回的店铺域名查询店铺
                    $shopDomain = $request->header('x-shopify-shop-domain') ?: '';
                    $shopifyShop = ShopModel::query()->where('platform', Platform::SHOPIFY)->where('status', '==', ShopModel::STATUS_AUTH)->where('shop_url', $shopDomain)->first();

                    //没有从订单里面找到店铺的标识，只能根据订单状态url匹配出店铺域名，再根据店铺域名找到对应的店铺
                    if (empty($shopifyShop)) {
                        $orderStatusUrl = $payload['order_status_url'] ?? '';
                        $url = parse_url($orderStatusUrl)['host'] ?? '';

                        $shopifyShop = ShopModel::query()->where('platform', Platform::SHOPIFY)->where('status', '==', ShopModel::STATUS_AUTH)->where('shop_url', $url)->first();
                    }

                    if ($shopifyShop) {
                        info('shopifyWebhook-saveOrder', [$shopifyShop, $payload['id']]);
                        (new ShopifyService($shopifyShop))->saveOrder($payload, Order::ORDER_SOURCE_WEBHOOK);
                    }
                    break;
                default:
                    info('不处理的webhook事假', [$event]);
            }
        } catch (\Exception $e) {
            info('shopifyWebhook error：', [$e->getMessage(), $e->getFile(), $e->getLine()]);
        }

        return ApiResponseService::success();
    }

    public function installAuth(Request $request,  AuthorizeShop $authShop)
    {
        $payload = $request->all();
        //上虞盛诺回调地址
        Config::set('shopify-app.api_redirect', getClientDomain() . '/api/client/shopify/redirect');
        (new ShopService(new ShopModel()))->setShopifyKeySecret();
        try {
            $result = $authShop($payload['shop'], null);
            return redirect($result->url);
        } catch (\Exception $e) {
            logger('店铺授权失败2：', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'msg' => $e->getMessage()
            ]);
            return ApiResponseService::error(Code::OPERATE_FAIL, '店铺授权失败');
        }
    }

    /** shopify 授权回调，转发到对应的前端页面处理
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function redirect(Request $request)
    {
        $query = $request->getQueryString();
        $host = getClientDomain();
        $url = $host . '/shop/authorize/shopify?' . $query;

        //app跳转链接
        $app = env('CELLPHONE_APPLICATION', 'JfFulfillment');
        $stateKey = $request->get('state');
        $state = Cache::get($stateKey);
        $source = $state['source'] ?? '';
        if ($source === 'app') {
            $url = "$app://authorize/shopify?" . $query;
            return view('open-app', ['url' => $url]);
        }

        return redirect($url);
    }

    /**
     * @param Request $request
     * @return array[]
     */
    public function carrierServiceCallback(Request $request)
    {
        info('shopify 运费回调参数', $request->all());
        return [
            'rates' => [
                'service_name' => ShopPlatformConfig::getPlatformApplicationName('shopify'),
                'description' => 'delivery free',
                'service_code' => getUuid(),
                'currency' => 'USD',
                'total_price' => 0,
                'phone_required' => false,
                'min_delivery_date' => Carbon::now()->addDays(5),
                'max_delivery_date' => Carbon::now()->addDays(15),
            ]
        ];
    }

    public function getTiktokAuthUrl(Request $request)
    {
        $data = ['url' => 'https://services.us.tiktokshop.com/open/authorize?service_id=7325708066249787141&state=' . auth('client')->id()];
        return ApiResponseService::success($data);
    }

    /** tiktok 授权回调
     * @param Request $request
     * @return Application|RedirectResponse|Redirector
     */
    public function tiktokRedirect(Request $request)
    {
        info('tiktok 回调参数', $request->all());
        $query = $request->getQueryString();
        $host = getClientDomain();
        $url = $host . '/shop/authorize/tiktok?' . $query;
        return redirect($url);

    }

    /** 获取店铺平台列表
     * @return array
     */
    public function getPlatformList()
    {
        $data = transformArray(ShopModel::PLATFORM_LIST);
        return ApiResponseService::success($data);
    }

    /** tiktok 授权接口
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function tiktokAuthenticate(Request $request)
    {
        $service = new PlatformShopService(new ShopModel());
        $service->setPlatform(Platform::TIKTOK);
        $service->authorize($request->all());
        return ApiResponseService::success();
    }

    public function sallaAuthenticate(Request $request)
    {

        return ApiResponseService::successMessage('success');
    }
    public function sallaRedirect(Request $request)
    {

    }

    public function sallaWebhook(Request $request)
    {

    }

    /**
     * zid 跳转授权
     * @param Request $request
     * @return JsonResponse|array
     * @throws \Exception|\Throwable
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/4 11:49
     */
    public function zidOAuth(Request $request)
    {
        $payload = $request->all();

        validator($payload, [
            'shop_url' => 'required|string',
            'id' => 'required'
        ])->validate();

        $shop = ShopModel::query()
            ->where('id', $payload['id'])
            ->toSql();

        throw_if(
            !$shop,
            new AccidentException('请先在当前系统创建店铺', Code::OPERATE_FAIL)
        );

        ShopModel::where('id', $payload['id'])->update([
            'shop_url'              => $payload['shop_url'],
            'access_token'          => $payload['token'] ?? '',
            'authorization_type'    => ShopModel::AUTHORIZATION_TYPE_OAUTH,
        ]);

        if(!empty($payload['token'])) {
            ShopModel::where('id', $payload['id'])->update(['status' => 1, 'authorize_at' => now()]);
            return ApiResponseService::success();
        }

        $config = config('zid-app');
        if (empty($config)) {
            return ApiResponseService::errorMessage('请先设置ZID配置');
        }

        Cache::set('custom_zid_shop_authing_' . getCustomId(), $payload['id']);
        $queries = http_build_query([
            'store_id'      => $payload['id'],
            'client_id'     => $config['client_id'],
            'redirect_url'  => env('APP_URL') . 'api/client/zid/redirect',
            'response_type' => 'code'
        ]);

        $url = $config['oauth_url'] . '/oauth/authorize?'. $queries;

        return ApiResponseService::success($url);
    }

    /**
     * zid 授权回调URL
     * @param Request $request
     * @return Application|RedirectResponse|Redirector
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/5 19:12
     */
    public function zidRedirect(Request $request)
    {
        info('zid 跳转的回调参数', $request->all());

        $clientDomain = getClientDomain();
        $queries = $request->getQueryString();

        $url = $clientDomain. '/shop/authorize/zid?'. $queries;
        return redirect($url);
    }

    /**
     * zid 发起授权
     * @param Request $request
     * @return array
     * @throws \Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/4 11:49
     */
    public function zidAuthenticate(Request $request)
    {
        info('zid 授权参数', $request->all());
        $shopId = Cache::get('custom_zid_shop_authing_'. getCustomId());
        throw_if(
            empty($shopId),
            new AccidentException('请先选择授权的店铺')
        );

        $shop = ShopModel::query()->findOrFail($shopId);
        if ($shop->status == ShopModel::STATUS_AUTH) {
            return ApiResponseService::success('授权成功');
        }

        $service = new PlatformShopService($shop);
        $service->setPlatform(Platform::ZID);
        $service->authorize($request->all());

        return ApiResponseService::success();
    }

    /**
     * zid webhook
     * @param Request $request
     * @return void
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/4 11:48
     */
    public function zidWebhook(Request $request)
    {

    }

    /**
     * shopify通知回调
     * @param Request $request
     * @return string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/6 13:54
     */
    public function fulfillmentOrderNotification(Request $request)
    {
        info('shopify_fulfillment_order_notification', [$request->headers, $request->all()]);
        return 'Success';
    }

}
